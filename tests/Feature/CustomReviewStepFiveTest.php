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
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session holding the given words, ready to give a review items.
 *
 * @param  list<string>  $words
 * @return array{session: PracticeSession, identities: list<string>}
 */
function stepFiveSource(array $words, bool $archived = false): array
{
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

    foreach ($words as $position => $word) {
        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Vocabulary->value,
            'content_text' => $word,
            'correction_text' => 'the meaning of '.$word,
            'correction_html' => '<p>the meaning of '.e($word).'</p>',
            'client_id' => (string) Str::uuid(),
            'position' => $position,
            'is_split' => true,
        ]);

        $identities[] = SearchText::normalize($word);
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
