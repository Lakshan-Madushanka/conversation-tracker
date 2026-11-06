<?php

declare(strict_types=1);

use App\Actions\Collections\SaveItemMark;
use App\Actions\Reviews\SyncReviewSchedule;
use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CollectionItemMark;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewSuggestionDecline;
use App\Models\Topic;
use App\Support\ReviewSettings;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
    DB::disableQueryLog();
});

/** A finished session holding one reviewable word. */
function suggestionSession(string $word = 'serendipity'): PracticeSession
{
    $at = CarbonImmutable::now();

    $session = PracticeSession::factory()
        ->finished()
        ->for(Topic::query()->firstOrCreate(['name' => 'Everyday talk']))
        ->create([
            'created_at' => $at,
            'finished_at' => $at,
        ]);

    suggestionRow($session, $word);

    return $session;
}

/** One word saved the way the editor saves it. */
function suggestionRow(
    PracticeSession $session,
    string $subject,
    FeedbackType $type = FeedbackType::Vocabulary,
): PracticeFeedbackEntry {
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => $type,
        'content_text' => $subject,
        'content_html' => $subject,
        'correction_text' => 'finding something good by chance',
        'correction_html' => 'finding something good by chance',
        'example' => '',
        'example_html' => [],
        'reason' => '',
        'position' => 0,
        'is_split' => true,
        'client_id' => (string) Str::uuid(),
    ])->refresh();
}

/** A grammar note that can make a blank. */
function suggestionNote(PracticeSession $session): PracticeFeedbackEntry
{
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::GrammarNote,
        'content_html' => '<p>Use the <strong>past simple</strong> here.</p>',
        'content_text' => 'Use the past simple here.',
        'position' => 0,
        'is_split' => true,
        'client_id' => (string) Str::uuid(),
    ])->refresh();
}

/** A past question with the natural version that makes it reviewable. */
function suggestionQuestion(PracticeSession $session): PracticeQuestion
{
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
        'question_text' => 'How was your weekend?',
        'question_html' => '<p>How was your weekend?</p>',
    ]);

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::NaturalVersion,
        'content_text' => 'It was relaxing.',
        'content_html' => '<p>It was relaxing.</p>',
    ]);

    return $question->refresh();
}

/**
 * A rating the learner earned on a past day, with the schedule grown from it.
 * The sitting it belongs to is returned, so a test can take the rating back.
 */
function suggestionRateOn(
    string $identity,
    ReviewRating $rating,
    string $day,
    FeedbackType $kind = FeedbackType::Vocabulary,
): Review {
    $moment = CarbonImmutable::parse($day, config('app.timezone'));

    $review = new Review;
    $review->created_at = $moment;
    $review->updated_at = $moment;
    $review->save();

    DB::table('ratings')->insert([
        'review_id' => $review->id,
        'kind' => $kind->value,
        'item_identity' => $identity,
        'rating' => $rating->value,
        'unique_key' => Rating::keyFor($review->id, $kind, $identity),
        'created_at' => $moment,
        'updated_at' => $moment,
    ]);

    app(SyncReviewSchedule::class)->handle($kind, $identity);

    return $review;
}

/** The rating the learner gave each item of a sitting, straight through the route. */
function suggestionRateAll(Review $review, ReviewRating $rating): void
{
    foreach ($review->items()->orderBy('position')->get() as $item) {
        test()->post(route('review.rate', ['review' => $review->id]), [
            'kind' => $item->kind->value,
            'item_identity' => $item->item_identity,
            'rating' => $rating->value,
        ])->assertRedirect();
    }
}

/** Start the day’s sitting. */
function suggestionStartDaily(): Review
{
    test()->post(route('review.store'), ['daily' => true])->assertSessionHasNoErrors();

    return Review::query()->latest('id')->firstOrFail();
}

/** Start a sitting that holds everything, resting items included. */
function suggestionStartCustom(): Review
{
    test()->post(route('review.customStore'))->assertSessionHasNoErrors();

    return Review::query()->latest('id')->firstOrFail();
}

/**
 * A whole finished sitting rating every item the same way, so the learner is
 * standing on the finish screen.
 */
function suggestionFinish(ReviewRating $rating = ReviewRating::Easy): Review
{
    $review = suggestionStartDaily();
    suggestionRateAll($review, $rating);

    test()->get(route('review.session', ['review' => $review->id]))->assertOk();

    return $review;
}

/** The finish screen, read again. */
function suggestionOpenFinish(Review $review): TestResponse
{
    return test()->get(route('review.session', ['review' => $review->id]))->assertOk();
}

/**
 * A finished sitting with one Learned suggestion ready to be answered.
 *
 * @return array{0: Review, 1: array<string, mixed>}
 */
function suggestionFinishedWithOffer(): array
{
    Carbon::setTestNow('2026-11-05 09:00:00');
    suggestionSession();

    suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-01');
    suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-10');

    $review = suggestionFinish(ReviewRating::Easy);

    /** @var array<string, mixed> $offer */
    $offer = suggestionOffers($review)->get('serendipity');

    return [$review, $offer];
}

/**
 * The suggestions on the finish screen, keyed by item identity.
 *
 * @return Collection<string, array<string, mixed>>
 */
function suggestionOffers(Review $review): Collection
{
    return collect(suggestionOpenFinish($review)->viewData('page')['props']['suggestions'])
        ->keyBy('item_identity');
}

describe('what the finish screen offers', function (): void {
    it('offers Learned after three Easy ratings in a row', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        suggestionSession();

        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-01');
        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-10');

        $review = suggestionFinish(ReviewRating::Easy);

        $offers = suggestionOffers($review);

        expect($offers->keys()->all())->toBe(['serendipity'])
            ->and($offers->first()['status'])->toBe('learned')
            ->and($offers->first()['status_label'])->toBe('Learned')
            ->and($offers->first()['rating_label'])->toBe('Easy')
            ->and($offers->first()['run'])->toBe(3)
            ->and($offers->first()['text'])->toBe('serendipity')
            ->and($offers->first()['kind'])->toBe('vocabulary');
    });

    it('offers Still difficult after two Hard ratings in a row', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        suggestionSession();

        suggestionRateOn('serendipity', ReviewRating::Hard, '2026-10-20');

        $review = suggestionFinish(ReviewRating::Hard);

        expect(suggestionOffers($review)->first()['status'])->toBe('still_difficult')
            ->and(suggestionOffers($review)->first()['status_label'])->toBe('Still difficult')
            ->and(suggestionOffers($review)->first()['run'])->toBe(2);
    });

    it('offers nothing when an Okay breaks the run', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        suggestionSession();

        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-01');
        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-10');
        suggestionRateOn('serendipity', ReviewRating::Okay, '2026-09-20');

        $review = suggestionFinish(ReviewRating::Easy);

        expect(suggestionOffers($review)->all())->toBe([]);
    });

    it('offers nothing before the run is long enough', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        suggestionSession();

        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-01');

        $easy = suggestionFinish(ReviewRating::Easy);
        expect(suggestionOffers($easy)->all())->toBe([]);

        Carbon::setTestNow('2026-11-06 09:00:00');
        $hard = suggestionStartCustom();
        suggestionRateAll($hard, ReviewRating::Hard);
        test()->get(route('review.session', ['review' => $hard->id]))->assertOk();

        expect(suggestionOffers($hard)->all())->toBe([]);
    });

    it('ignores a rating the learner took back', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        suggestionSession();

        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-01');
        $middle = suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-10');

        // Two Easy in the history plus this sitting’s makes the run of three.
        $review = suggestionFinish(ReviewRating::Easy);
        expect(suggestionOffers($review)->count())->toBe(1);

        test()->delete(route('review.undo', ['review' => $middle->id]), [
            'kind' => 'vocabulary',
            'item_identity' => 'serendipity',
        ])->assertRedirect();

        // The run is two again, and an undone rating is not part of it.
        expect(suggestionOffers($review)->all())->toBe([]);
    });

    it('never offers a status for a past question or a grammar note', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        $session = suggestionSession('serendipity');

        $question = suggestionQuestion($session);
        $note = suggestionNote($session);

        // Both have earned three Easy ratings in a row, which is what offers a
        // status for the kinds that carry one.
        foreach (['2026-09-01', '2026-09-10', '2026-09-20'] as $day) {
            suggestionRateOn((string) $question->id, ReviewRating::Easy, $day, FeedbackType::Question);
            suggestionRateOn('note-'.$note->id, ReviewRating::Easy, $day, FeedbackType::GrammarNote);
        }

        $review = suggestionStartCustom();
        suggestionRateAll($review, ReviewRating::Easy);

        expect($review->ratings()->whereIn('kind', ['question', 'grammar_note'])->count())->toBe(2)
            ->and(suggestionOffers($review)->all())->toBe([]);
    });

    it('leaves an item that already carries the status alone', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        suggestionSession();

        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-01');
        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-10');
        app(SaveItemMark::class)->handle(
            FeedbackType::Vocabulary,
            'serendipity',
            false,
            CollectionItemStatus::Learned,
        );

        $review = suggestionFinish(ReviewRating::Easy);

        expect(suggestionOffers($review)->all())->toBe([]);
    });

    it('raises at most two suggestions on one screen', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        $session = suggestionSession('first');

        foreach (['second', 'third', 'fourth'] as $word) {
            suggestionRow($session, $word);
        }

        foreach (['first', 'second', 'third', 'fourth'] as $word) {
            suggestionRateOn($word, ReviewRating::Easy, '2026-09-01');
            suggestionRateOn($word, ReviewRating::Easy, '2026-09-10');
        }

        $review = suggestionStartCustom();
        suggestionRateAll($review, ReviewRating::Easy);

        expect(suggestionOffers($review)->count())->toBe(ReviewSettings::MAX_STATUS_SUGGESTIONS);
    });

    it('offers nothing while the learner is still rating', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        $session = suggestionSession('serendipity');
        suggestionRow($session, 'cascade');

        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-01');
        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-10');

        $review = suggestionStartCustom();

        // One item rated, one still to meet: a card is on screen, not a result.
        test()->post(route('review.rate', ['review' => $review->id]), [
            'kind' => 'vocabulary',
            'item_identity' => 'serendipity',
            'rating' => ReviewRating::Easy->value,
        ])->assertRedirect();

        test()->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('finished', false)
                ->where('suggestions', []));
    });

    it('offers nothing for a sitting that was finished early', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        $session = suggestionSession('serendipity');
        suggestionRow($session, 'cascade');

        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-01');
        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-10');

        $review = suggestionStartCustom();
        suggestionRateAll($review, ReviewRating::Easy);

        // The learner stopped before the end, so the finish screen was never
        // reached and nothing is offered.
        test()->delete(route('review.undo', ['review' => $review->id]), [
            'kind' => 'vocabulary',
            'item_identity' => 'serendipity',
        ])->assertRedirect();

        test()->post(route('review.finishEarly', ['review' => $review->id]))
            ->assertRedirect(route('review.index'));

        test()->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('finished', false)
                ->where('suggestions', []));
    });

    it('never holds the finish screen up', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        suggestionSession();

        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-01');
        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-09-10');

        $review = suggestionFinish(ReviewRating::Easy);

        suggestionOpenFinish($review)->assertInertia(fn (Assert $page): Assert => $page
            ->where('finished', true)
            ->where('suggestions.0.item_identity', 'serendipity')
            ->where('counts.easy', 1)
            ->where('reviewedCount', 1)
            ->where('isDailyReview', true));
    });
});

describe('the learner’s answers', function (): void {
    it('marks the item and changes nothing else', function (): void {
        [$review, $offer] = suggestionFinishedWithOffer();

        // The star belongs to the learner alone, so it is set before and must
        // survive the answer.
        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'serendipity', true, null);

        $before = [
            'reviews' => Review::query()->count(),
            'ratings' => Rating::query()->count(),
            'declines' => ReviewSuggestionDecline::query()->count(),
            'sessions' => PracticeSession::query()->count(),
            'entries' => PracticeFeedbackEntry::query()->count(),
        ];

        suggestionAnswerConfirm($review, $offer);

        /** @var CollectionItemMark $mark */
        $mark = CollectionItemMark::query()
            ->where('kind', 'vocabulary')
            ->where('group_key', 'serendipity')
            ->firstOrFail();

        expect($mark->status)->toBe(CollectionItemStatus::Learned)
            ->and($mark->starred)->toBeTrue()
            ->and([
                'reviews' => Review::query()->count(),
                'ratings' => Rating::query()->count(),
                'declines' => ReviewSuggestionDecline::query()->count(),
                'sessions' => PracticeSession::query()->count(),
                'entries' => PracticeFeedbackEntry::query()->count(),
            ])->toBe($before)
            ->and($review->refresh()->completed_on?->toDateString())->toBe('2026-11-05')
            ->and(PracticeSession::query()->count())->toBe($before['sessions']);

        expect(suggestionOffers($review)->all())->toBe([]);
    });

    it('keeps the day’s review the day’s review when a status is confirmed', function (): void {
        [$review, $offer] = suggestionFinishedWithOffer();

        suggestionAnswerConfirm($review, $offer);

        expect($review->refresh()->is_daily_review)->toBeTrue()
            ->and($review->refresh()->completed_on?->toDateString())->toBe('2026-11-05')
            ->and($review->refresh()->finished_at)->not->toBeNull();
    });

    it('changes nothing when the learner says Not now, and does not ask again for that run', function (): void {
        [$review, $offer] = suggestionFinishedWithOffer();

        suggestionAnswerDecline($review, $offer);

        expect(CollectionItemMark::query()->where('kind', 'vocabulary')
            ->where('group_key', 'serendipity')
            ->where('status', CollectionItemStatus::Learned->value)
            ->count())->toBe(0)
            ->and(ReviewSuggestionDecline::query()->count())->toBe(1)
            ->and(suggestionOffers($review)->all())->toBe([]);

        // A fourth Easy rating is the same run, so the screen stays quiet.
        Carbon::setTestNow('2026-11-30 09:00:00');
        $again = suggestionStartCustom();
        suggestionRateAll($again, ReviewRating::Easy);

        expect(suggestionOffers($again)->all())->toBe([])
            ->and(ReviewSuggestionDecline::query()->count())->toBe(1);
    });

    it('asks again when a new run reaches the threshold', function (): void {
        [$review, $offer] = suggestionFinishedWithOffer();

        suggestionAnswerDecline($review, $offer);

        // The Okay breaks the run the learner refused, and a fresh run of Easy
        // ratings reaches the threshold again.
        suggestionRateOn('serendipity', ReviewRating::Okay, '2026-11-06');
        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-11-07');
        suggestionRateOn('serendipity', ReviewRating::Easy, '2026-11-08');

        Carbon::setTestNow('2026-11-09 09:00:00');
        $newer = suggestionStartCustom();
        suggestionRateAll($newer, ReviewRating::Easy);

        $offers = suggestionOffers($newer);

        expect($offers->keys()->all())->toBe(['serendipity'])
            ->and($offers->first()['status'])->toBe('learned')
            ->and($offers->first()['run_started_id'])->not->toBe($offer['run_started_id'])
            ->and(ReviewSuggestionDecline::query()->count())->toBe(1);
    });

    it('refuses an answer about an item this sitting never held', function (): void {
        [, $offer] = suggestionFinishedWithOffer();

        // A day later the learner starts again. The item just rated is resting,
        // so this sitting never met it.
        Carbon::setTestNow('2026-11-06 09:00:00');
        suggestionSession('another');
        $other = suggestionStartDaily();

        expect($other->items()->pluck('item_identity')->all())->not->toContain('serendipity');

        test()->post(route('review.suggestionConfirm', ['review' => $other->id]), [
            'kind' => $offer['kind'],
            'item_identity' => $offer['item_identity'],
            'status' => $offer['status'],
            'run_started_id' => $offer['run_started_id'],
        ])->assertNotFound();

        expect(CollectionItemMark::query()->count())->toBe(0);
    });

    it('refuses a run that is not a rating of that item', function (): void {
        [$review, $offer] = suggestionFinishedWithOffer();

        $elsewhere = suggestionRateOn('other-word', ReviewRating::Easy, '2026-09-02');

        test()->post(route('review.suggestionConfirm', ['review' => $review->id]), [
            'kind' => $offer['kind'],
            'item_identity' => $offer['item_identity'],
            'status' => $offer['status'],
            'run_started_id' => $elsewhere->ratings()->value('id') ?? 0,
        ])->assertRedirect()->assertSessionHasErrors('run_started_id');

        // The same refusal when the item is the one that does not fit.
        test()->post(route('review.suggestionConfirm', ['review' => $review->id]), [
            'kind' => $offer['kind'],
            'item_identity' => 'not-a-word-the-learner-met',
            'status' => $offer['status'],
            'run_started_id' => $offer['run_started_id'],
        ])->assertRedirect()->assertSessionHasErrors('run_started_id');

        expect(CollectionItemMark::query()->count())->toBe(0);
    });

    it('refuses a status the finish screen never offers', function (): void {
        [$review, $offer] = suggestionFinishedWithOffer();

        test()->post(route('review.suggestionConfirm', ['review' => $review->id]), [
            'kind' => 'question',
            'item_identity' => $offer['item_identity'],
            'status' => $offer['status'],
            'run_started_id' => $offer['run_started_id'],
        ])->assertRedirect()->assertSessionHasErrors('kind');

        expect(CollectionItemMark::query()->count())->toBe(0);
    });
});

/** Answer a suggestion the way the card does. */
function suggestionAnswerConfirm(Review $review, array $offer): void
{
    test()->post(route('review.suggestionConfirm', ['review' => $review->id]), [
        'kind' => $offer['kind'],
        'item_identity' => $offer['item_identity'],
        'status' => $offer['status'],
        'run_started_id' => $offer['run_started_id'],
    ])->assertRedirect(route('review.session', ['review' => $review->id]));
}

/** Say "Not now" the way the card does. */
function suggestionAnswerDecline(Review $review, array $offer): void
{
    test()->post(route('review.suggestionDecline', ['review' => $review->id]), [
        'kind' => $offer['kind'],
        'item_identity' => $offer['item_identity'],
        'status' => $offer['status'],
        'run_started_id' => $offer['run_started_id'],
    ])->assertRedirect(route('review.session', ['review' => $review->id]));
}
