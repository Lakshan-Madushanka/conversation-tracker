<?php

declare(strict_types=1);

use App\Actions\CustomReviews\AddCustomReviewItems;
use App\Actions\CustomReviews\SetCustomReviewStatus;
use App\Actions\PracticeQuestions\SavePracticeQuestions;
use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use App\Models\CustomReviewItem;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Support\CustomReviewDeletion;
use App\Support\CustomReviewPractice;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session holding the given items, ready to give a review something
 * to show — live, or parked in an archived session.
 *
 * @param  list<string>  $texts
 * @return array{session: PracticeSession, identities: list<string>}
 */
function stepFiveSource(
    array $texts,
    bool $archived = false,
    FeedbackType $kind = FeedbackType::Vocabulary,
): array {
    $at = CarbonImmutable::now();

    $session = PracticeSession::factory()
        ->finished()
        ->for(Topic::factory())
        ->create([
            'created_at' => $at,
            'updated_at' => $at,
            'finished_at' => $at,
            'last_changed_at' => $at,
            'archived_at' => $archived ? $at : null,
        ]);

    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => 1,
    ]);

    $identities = [];

    foreach ($texts as $position => $text) {
        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => $kind->value,
            'content_text' => $text,
            'correction_text' => 'the meaning of '.$text,
            'correction_html' => '<p>the meaning of '.e($text).'</p>',
            'client_id' => (string) Str::uuid(),
            'position' => $position,
            'is_split' => true,
        ]);

        $identities[] = SearchText::normalize($text);
    }

    return ['session' => $session, 'identities' => $identities];
}

/**
 * A review holding the given words.
 *
 * @param  list<string>  $identities
 */
function stepFiveReview(
    string $name,
    CustomReviewStatus $status,
    array $identities,
): CustomReview {
    $review = CustomReview::factory()->create([
        'name' => $name,
        'status' => CustomReviewStatus::Empty,
    ]);

    if ($identities !== []) {
        app(AddCustomReviewItems::class)->handle(
            $review,
            FeedbackType::Vocabulary,
            $identities,
        );
    }

    if ($status === CustomReviewStatus::Finished) {
        app(SetCustomReviewStatus::class)->handle(
            $review,
            CustomReviewStatus::Finished,
        );
    }

    return $review->refresh();
}

/** Deleting a session the way the library does, with the token it hands out. */
function stepFiveDeleteSession(PracticeSession $session): void
{
    $token = test()
        ->getJson(route('sessions.deletePreview', ['session' => $session]))
        ->assertOk()
        ->json('token');

    test()
        ->deleteJson(route('sessions.deletePermanently', ['sessionId' => $session->id]), [
            'token' => $token,
            'tz' => 'UTC',
        ])
        ->assertOk();
}

/** Starting an attempt at a Finished review, and returning it. */
function stepFiveStarted(CustomReview $review): CustomReviewAttempt
{
    test()->post(route('customReviews.attempts.store', $review))->assertRedirect();

    return CustomReviewAttempt::query()->sole();
}

/** Rating one item of a running attempt. */
function stepFiveRate(CustomReview $review, CustomReviewAttempt $attempt, string $word, ReviewRating $rating): void
{
    test()->post(route('customReviews.attempts.rate', [
        'customReview' => $review->id,
        'attempt' => $attempt->id,
    ]), [
        'kind' => FeedbackType::Vocabulary->value,
        'item_identity' => SearchText::normalize($word),
        'rating' => $rating->value,
    ])->assertRedirect();
}

describe('a deleted item', function (): void {
    it('leaves every review that held it the moment its session is deleted', function (): void {
        $source = stepFiveSource(['apple', 'banana']);
        $first = stepFiveReview('Fruit words', CustomReviewStatus::Draft, $source['identities']);
        $second = stepFiveReview('One more', CustomReviewStatus::Draft, [$source['identities'][0]]);

        stepFiveDeleteSession($source['session']);

        expect(CustomReviewItem::query()->count())->toBe(0)
            ->and($first->refresh()->status)->toBe(CustomReviewStatus::Empty)
            ->and($second->refresh()->status)->toBe(CustomReviewStatus::Empty);

        $this->get(route('customReviews.show', $first))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('review.item_count', 0)
                ->where('review.status', 'empty')
                ->where('groups', []));
    });

    it('stays where another session still holds the same item', function (): void {
        $leaving = stepFiveSource(['apple']);
        $staying = stepFiveSource(['apple']);
        $review = stepFiveReview('Kept', CustomReviewStatus::Draft, [$leaving['identities'][0]]);

        stepFiveDeleteSession($leaving['session']);

        expect(CustomReviewItem::query()->count())->toBe(1)
            ->and(CustomReviewItem::query()->sole()->item_identity)
            ->toBe($staying['identities'][0])
            ->and($review->refresh()->status)->toBe(CustomReviewStatus::Draft);
    });

    it('leaves a finished review that loses its last item, without a way to practise', function (): void {
        $source = stepFiveSource(['apple']);
        $review = stepFiveReview('Ready set', CustomReviewStatus::Finished, $source['identities']);

        stepFiveDeleteSession($source['session']);

        expect($review->refresh()->status)->toBe(CustomReviewStatus::Empty);

        $this->post(route('customReviews.attempts.store', $review))
            ->assertRedirect(route('customReviews.show', $review));

        expect(CustomReviewAttempt::query()->count())->toBe(0);
    });

    it('comes back as a draft when it is filled again, never finished', function (): void {
        $source = stepFiveSource(['apple']);
        $review = stepFiveReview('Ready set', CustomReviewStatus::Finished, $source['identities']);

        stepFiveDeleteSession($source['session']);

        expect($review->refresh()->status)->toBe(CustomReviewStatus::Empty);

        $again = stepFiveSource(['apple']);
        app(AddCustomReviewItems::class)->handle($review, FeedbackType::Vocabulary, $again['identities']);

        expect($review->refresh()->status)->toBe(CustomReviewStatus::Draft);
    });

    it('is gone for good, even when the same words come back', function (): void {
        $source = stepFiveSource(['apple']);
        $review = stepFiveReview('Fruit words', CustomReviewStatus::Draft, $source['identities']);

        stepFiveDeleteSession($source['session']);

        $again = stepFiveSource(['apple']);

        expect(CustomReviewItem::query()->count())->toBe(0)
            ->and($again['session']->id)->not->toBe($source['session']->id);

        $this->get(route('customReviews.show', $review))
            ->assertInertia(fn (Assert $page): Assert => $page->where('groups', []));
    });
});

describe('a deleted item in a past attempt', function (): void {
    it('keeps its snapshot and its rating, and says it was deleted', function (): void {
        $source = stepFiveSource(['apple', 'banana', 'cherry']);
        $review = stepFiveReview('Ready set', CustomReviewStatus::Finished, $source['identities']);
        $attempt = stepFiveStarted($review);

        stepFiveRate($review, $attempt, 'apple', ReviewRating::Easy);
        stepFiveRate($review, $attempt, 'banana', ReviewRating::Hard);
        stepFiveRate($review, $attempt, 'cherry', ReviewRating::Okay);

        stepFiveDeleteSession($source['session']);

        expect(CustomReviewAttemptItem::query()->count())->toBe(3)
            ->and(CustomReviewAttemptItem::query()->where('is_deleted', true)->count())->toBe(3)
            ->and($attempt->refresh()->status->value)->toBe('completed')
            ->and($attempt->number)->toBe(1);

        $this->get(route('customReviews.show', $review))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('attempts.0.number_label', 'Attempt 1')
                ->where('attempts.0.status', 'completed')
                ->where('attempts.0.counts.rated', 3)
                ->where('attempts.0.counts.easy', 1)
                ->where('attempts.0.counts.hard', 1)
                ->where('attempts.0.counts.okay', 1)
                ->where('attempts.0.items.0.text', 'cherry')
                ->where('attempts.0.items.0.rating', 'okay')
                ->where('attempts.0.items.0.deleted', true)
                ->whereNull('attempts.0.items.0.href'));
    });

    it('stays unmarked while its session is only archived', function (): void {
        $source = stepFiveSource(['apple', 'banana']);
        $review = stepFiveReview('Ready set', CustomReviewStatus::Finished, $source['identities']);
        $attempt = stepFiveStarted($review);

        stepFiveRate($review, $attempt, 'apple', ReviewRating::Easy);
        stepFiveRate($review, $attempt, 'banana', ReviewRating::Hard);

        $this->postJson(route('sessions.archive', $source['session']))->assertOk();

        expect(CustomReviewAttemptItem::query()->where('is_deleted', true)->count())->toBe(0);

        $this->get(route('customReviews.show', $review))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('attempts.0.counts.rated', 2)
                ->where('attempts.0.items.0.deleted', false)
                ->where('attempts.0.items.0.href', fn (mixed $href): bool => is_string($href) && $href !== ''));

        $this->postJson(route('sessions.restore', $source['session']))->assertOk();

        $this->get(route('customReviews.show', $review))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('attempts.0.counts.rated', 2)
                ->where('attempts.0.items.0.deleted', false));
    });
});

describe('a kind left out of an attempt', function (): void {
    it('says which kind the attempt started without', function (): void {
        $words = stepFiveSource(['apple', 'banana']);
        $phrases = stepFiveSource(['see you soon', 'take care'], archived: true, kind: FeedbackType::Phrase);

        $review = CustomReview::factory()->create([
            'name' => 'Mixed set',
            'status' => CustomReviewStatus::Empty,
        ]);
        app(AddCustomReviewItems::class)->handle($review, FeedbackType::Vocabulary, $words['identities']);
        app(AddCustomReviewItems::class)->handle($review, FeedbackType::Phrase, $phrases['identities']);
        app(SetCustomReviewStatus::class)->handle($review, CustomReviewStatus::Finished);

        $attempt = stepFiveStarted($review);

        expect(CustomReviewAttemptItem::query()->count())->toBe(2)
            ->and(CustomReviewAttemptItem::query()->where('kind', 'phrase')->count())->toBe(0);

        $this->get(route('customReviews.attempts.show', [
            'customReview' => $review->id,
            'attempt' => $attempt->id,
        ]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('notices', 1)
                ->where('notices.0', CustomReviewPractice::leftOut(FeedbackType::Phrase))
                ->where('position.total', 2)
                ->where('finished', false));
    });

    it('says it once, and not again on the next visit', function (): void {
        $words = stepFiveSource(['apple']);
        $phrases = stepFiveSource(['see you soon'], archived: true, kind: FeedbackType::Phrase);

        $review = CustomReview::factory()->create([
            'name' => 'Mixed set',
            'status' => CustomReviewStatus::Empty,
        ]);
        app(AddCustomReviewItems::class)->handle($review, FeedbackType::Vocabulary, $words['identities']);
        app(AddCustomReviewItems::class)->handle($review, FeedbackType::Phrase, $phrases['identities']);
        app(SetCustomReviewStatus::class)->handle($review, CustomReviewStatus::Finished);

        $attempt = stepFiveStarted($review);
        $url = route('customReviews.attempts.show', [
            'customReview' => $review->id,
            'attempt' => $attempt->id,
        ]);

        $this->get($url)->assertInertia(fn (Assert $page): Assert => $page->has('notices', 1));

        $this->get($url)
            ->assertInertia(fn (Assert $page): Assert => $page->where('notices', []));
    });
});

describe('an item archived during an attempt', function (): void {
    it('is skipped for the rest of the walk while what was rated stays', function (): void {
        $first = stepFiveSource(['apple', 'banana']);
        $second = stepFiveSource(['cherry', 'dates']);
        $review = stepFiveReview(
            'Ready set',
            CustomReviewStatus::Finished,
            [...$first['identities'], ...$second['identities']],
        );
        $attempt = stepFiveStarted($review);

        stepFiveRate($review, $attempt, 'apple', ReviewRating::Easy);
        stepFiveRate($review, $attempt, 'banana', ReviewRating::Hard);

        $this->postJson(route('sessions.archive', $second['session']))->assertOk();

        expect(CustomReviewAttemptItem::query()->count())->toBe(4)
            ->and(CustomReviewAttemptItem::query()->whereNotNull('rating')->count())->toBe(2)
            ->and(CustomReviewAttemptItem::query()->where('is_deleted', true)->count())->toBe(0);

        $this->get(route('customReviews.attempts.show', [
            'customReview' => $review->id,
            'attempt' => $attempt->id,
        ]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('finished', true)
                ->where('position', ['current' => 2, 'total' => 2])
                ->where('attempt.rated', 2)
                ->where('attempt.total', 2)
                ->where('counts.easy', 1)
                ->where('counts.hard', 1));
    });

    it('comes back in full when the session is restored', function (): void {
        $first = stepFiveSource(['apple']);
        $second = stepFiveSource(['banana']);
        $third = stepFiveSource(['cherry']);
        $review = stepFiveReview(
            'Ready set',
            CustomReviewStatus::Finished,
            [...$first['identities'], ...$second['identities'], ...$third['identities']],
        );
        $attempt = stepFiveStarted($review);

        stepFiveRate($review, $attempt, 'apple', ReviewRating::Easy);

        // The newest session is met first, so the walk is on its item when a
        // session behind it is archived; that item is skipped and the walk
        // carries on to the next one it can still show.
        $this->postJson(route('sessions.archive', $second['session']))->assertOk();

        $this->get(route('customReviews.attempts.show', [
            'customReview' => $review->id,
            'attempt' => $attempt->id,
        ]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('finished', false)
                ->where('position', ['current' => 1, 'total' => 2])
                ->where('current.card.front', 'cherry')
                ->where('notices', []));

        $this->postJson(route('sessions.restore', $second['session']))->assertOk();

        $this->get(route('customReviews.attempts.show', [
            'customReview' => $review->id,
            'attempt' => $attempt->id,
        ]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('finished', false)
                ->where('position', ['current' => 1, 'total' => 3]));
    });
});

describe('an item removed in the editor', function (): void {
    it('leaves the reviews that held it and keeps the rows beside it', function (): void {
        $source = stepFiveSource(['apple', 'banana']);
        $review = stepFiveReview('Fruit words', CustomReviewStatus::Draft, $source['identities']);

        /** @var PracticeQuestion $question */
        $question = $source['session']->questions()->sole();

        $kept = $question->feedbackEntries()->where('content_text', 'banana')->sole();

        DB::transaction(fn (): mixed => app(SavePracticeQuestions::class)->handle(
            $source['session'],
            [[
                'question_html' => '<p>Where did you go?</p>',
                'answer_html' => '<p>A sentence.</p>',
                'feedback' => [
                    'vocabulary' => [[
                        'id' => $kept->client_id,
                        'text' => 'banana',
                        'meaning' => 'the meaning of banana',
                    ]],
                ],
            ]],
        ));

        expect(CustomReviewItem::query()->count())->toBe(1)
            ->and(CustomReviewItem::query()->sole()->item_identity)
            ->toBe($source['identities'][1])
            ->and($review->refresh()->status)->toBe(CustomReviewStatus::Draft);
    });
});

describe('deleting a review', function (): void {
    it('takes the review, its items and its attempts in one go', function (): void {
        $source = stepFiveSource(['apple', 'banana', 'cherry']);
        $keeping = stepFiveReview('Other set', CustomReviewStatus::Draft, [$source['identities'][0]]);
        $review = stepFiveReview('Ready set', CustomReviewStatus::Finished, $source['identities']);
        $attempt = stepFiveStarted($review);

        stepFiveRate($review, $attempt, 'apple', ReviewRating::Easy);

        $this->delete(route('customReviews.destroy', ['customReviewId' => $review->id]))
            ->assertRedirect(route('customReviews.index'))
            ->assertSessionHas('review_notice', 'Ready set was deleted.');

        expect(CustomReview::query()->whereKey($review->id)->exists())->toBeFalse()
            ->and(CustomReviewAttempt::query()->count())->toBe(0)
            ->and(CustomReviewAttemptItem::query()->count())->toBe(0)
            // The items themselves go nowhere: they stay in their session and
            // in every review that still holds them.
            ->and(PracticeFeedbackEntry::query()->count())->toBe(3)
            ->and(CustomReviewItem::query()->count())->toBe(1)
            ->and(CustomReviewItem::query()->sole()->custom_review_id)->toBe($keeping->id);
    });

    it('empties the listing of it and says so once, with no way back', function (): void {
        $source = stepFiveSource(['apple']);
        $review = stepFiveReview('Ready set', CustomReviewStatus::Draft, $source['identities']);

        $this->get(route('customReviews.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('reviews.0.delete_url', route('customReviews.destroy', ['customReviewId' => $review->id])));

        $this->get(route('customReviews.show', $review))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('delete_url', route('customReviews.destroy', ['customReviewId' => $review->id])));

        $this->delete(route('customReviews.destroy', ['customReviewId' => $review->id]))
            ->assertRedirect(route('customReviews.index'));

        $this->get(route('customReviews.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('notice', 'Ready set was deleted.')
                ->where('total', 0)
                ->where('reviews', []));

        $this->get(route('customReviews.index'))
            ->assertInertia(fn (Assert $page): Assert => $page->where('notice', null));
    });

    it('answers the same calm line when the review is already gone', function (): void {
        $source = stepFiveSource(['apple']);
        $review = stepFiveReview('Ready set', CustomReviewStatus::Draft, $source['identities']);
        $url = route('customReviews.destroy', ['customReviewId' => $review->id]);

        $this->delete($url)->assertRedirect(route('customReviews.index'));

        $this->delete($url)
            ->assertRedirect(route('customReviews.index'))
            ->assertSessionHas('review_notice', CustomReviewDeletion::GONE);
    });

    it('shows a friendly page for the address of a review that is gone', function (): void {
        $source = stepFiveSource(['apple']);
        $review = stepFiveReview('Ready set', CustomReviewStatus::Draft, $source['identities']);

        $this->delete(route('customReviews.destroy', ['customReviewId' => $review->id]));

        $this->get(route('customReviews.show', $review))
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page): Assert => $page->component('errors/404'));
    });

    it('refuses the next save of an attempt left open in another tab', function (): void {
        $source = stepFiveSource(['apple', 'banana']);
        $review = stepFiveReview('Ready set', CustomReviewStatus::Finished, $source['identities']);
        $attempt = stepFiveStarted($review);

        stepFiveRate($review, $attempt, 'apple', ReviewRating::Easy);

        $this->delete(route('customReviews.destroy', ['customReviewId' => $review->id]));

        expect(CustomReviewAttemptItem::query()->count())->toBe(0);

        $this->post(route('customReviews.attempts.rate', [
            'customReview' => $review->id,
            'attempt' => $attempt->id,
        ]), [
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => $source['identities'][1],
            'rating' => ReviewRating::Hard->value,
        ])->assertStatus(404);

        $this->get(route('customReviews.attempts.show', [
            'customReview' => $review->id,
            'attempt' => $attempt->id,
        ]))
            ->assertStatus(404)
            ->assertInertia(fn (Assert $page): Assert => $page->component('errors/404'));
    });
});
