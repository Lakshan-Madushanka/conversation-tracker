<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\Topic;
use App\Support\ReviewSettings;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
    DB::disableQueryLog();
});

/** A finished session with one reviewable word in it. */
function resultsSession(string $topic = 'Everyday talk'): PracticeSession
{
    $at = CarbonImmutable::now();

    return PracticeSession::factory()
        ->finished()
        ->for(Topic::query()->firstOrCreate(['name' => $topic]))
        ->create([
            'created_at' => $at,
            'finished_at' => $at,
        ]);
}

/** One word saved the way the editor saves it. */
function resultsRow(PracticeSession $session, string $subject): PracticeFeedbackEntry
{
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::Vocabulary,
        'content_text' => $subject,
        'content_html' => $subject,
        'correction_text' => 'a meaning',
        'correction_html' => 'a meaning',
        'example' => '',
        'example_html' => [],
        'reason' => '',
        'position' => 0,
        'is_split' => true,
        'client_id' => (string) Str::uuid(),
    ])->refresh();
}

/** Start a sitting through the button the learner presses. */
function resultsStart(bool $daily = true): Review
{
    test()->post(route($daily ? 'review.store' : 'review.customStore'), ['daily' => $daily])
        ->assertSessionHasNoErrors();

    /** @var Review $review */
    $review = Review::query()->latest('id')->firstOrFail();

    return $review;
}

/** Rate one item through the route the learner uses. */
function resultsRate(Review $review, string $identity, ReviewRating $rating): void
{
    test()->post(route('review.rate', ['review' => $review->id]), [
        'kind' => 'vocabulary',
        'item_identity' => $identity,
        'rating' => $rating->value,
    ])->assertRedirect();
}

/** Rate every item, then open the sitting again to land on the finish screen. */
function resultsFinish(Review $review, ReviewRating $rating = ReviewRating::Easy): void
{
    foreach ($review->items()->orderBy('position')->get() as $item) {
        resultsRate($review, (string) $item->item_identity, $rating);
    }

    test()->get(route('review.session', ['review' => $review->id]))->assertOk();
}

/**
 * A whole finished sitting on a given day. The words named are rated as asked
 * and anything else the sitting still holds is rated Easy, so the learner
 * really does reach the end.
 *
 * @param  array<string, ReviewRating>  $ratings  identity => rating
 */
function resultsSitting(string $day, array $ratings, bool $daily = true): Review
{
    Carbon::setTestNow("{$day} 09:00:00");
    $session = resultsSession();

    foreach (array_keys($ratings) as $identity) {
        resultsRow($session, (string) $identity);
    }

    $review = resultsStart($daily);

    foreach ($review->items()->orderBy('position')->get() as $item) {
        resultsRate(
            $review,
            (string) $item->item_identity,
            $ratings[$item->item_identity] ?? ReviewRating::Easy,
        );
    }

    test()->get(route('review.session', ['review' => $review->id]))->assertOk();

    return $review;
}

describe('recent results on Review Home', function (): void {
    it('is empty until a sitting has ended', function (): void {
        resultsRow(resultsSession(), 'serendipity');
        resultsRow(resultsSession(), 'cascade');

        // Started, rated, but never finished: nothing to look back on.
        $review = resultsStart();
        resultsRate($review, 'serendipity', ReviewRating::Easy);

        test()->get(route('review.index'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('recentReviews', []));
    });

    it('lists the last five sittings the learner ended, newest first', function (): void {
        $sittings = [];

        foreach (range(1, 7) as $number) {
            $sittings[] = resultsSitting(
                "2026-10-{$number}",
                ["word-{$number}" => ReviewRating::Easy],
                daily: $number % 2 === 0,
            );
        }

        test()->get(route('review.index'))->assertInertia(fn (Assert $page): Assert => $page
            ->has('recentReviews', ReviewSettings::RECENT_RESULTS));

        $listed = collect(test()->get(route('review.index'))->viewData('page')['props']['recentReviews']);

        // The two oldest sittings have fallen off the list; the five newest
        // come back with the most recent first.
        expect($listed->pluck('id')->all())
            ->toBe(array_reverse(array_slice(array_map(fn (Review $review): int => $review->id, $sittings), -5)))
            ->and($listed->pluck('id')->contains($sittings[0]->id))->toBeFalse()
            ->and($listed->pluck('id')->contains($sittings[1]->id))->toBeFalse()
            ->and(CarbonImmutable::parse($listed->pluck('finished_at')->first()))
            ->greaterThan(CarbonImmutable::parse($listed->pluck('finished_at')->last()));
    });

    it('names the kind of sitting it was', function (): void {
        resultsSitting('2026-11-01', ['daily-word' => ReviewRating::Easy], daily: true);
        resultsSitting('2026-11-02', ['custom-word' => ReviewRating::Hard], daily: false);

        test()->get(route('review.index'))->assertInertia(fn (Assert $page): Assert => $page
            ->has('recentReviews', 2)
            ->where('recentReviews.0.type', 'custom')
            ->where('recentReviews.0.type_label', 'Custom review')
            ->where('recentReviews.0.rated', 2)
            ->where('recentReviews.0.counts', ['hard' => 1, 'okay' => 0, 'easy' => 1])
            ->where('recentReviews.1.type', 'daily')
            ->where('recentReviews.1.type_label', 'Daily review')
            ->where('recentReviews.1.rated', 1)
            ->where('recentReviews.1.counts', ['hard' => 0, 'okay' => 0, 'easy' => 1]));
    });

    it('counts the ratings that were saved, not the items that were skipped', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        $session = resultsSession();
        resultsRow($session, 'rated-easy');
        resultsRow($session, 'rated-hard');
        resultsRow($session, 'never-touched');

        $review = resultsStart();
        resultsRate($review, 'rated-easy', ReviewRating::Easy);
        resultsRate($review, 'rated-hard', ReviewRating::Hard);

        test()->post(route('review.finishEarly', ['review' => $review->id]))->assertRedirect();

        test()->get(route('review.index'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('recentReviews.0.rated', 2)
            ->where('recentReviews.0.counts', ['hard' => 1, 'okay' => 0, 'easy' => 1]));
    });

    it('leaves out a sitting that ended with nothing rated', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        resultsRow(resultsSession(), 'serendipity');

        $review = resultsStart();
        test()->post(route('review.finishEarly', ['review' => $review->id]))->assertRedirect();

        test()->get(route('review.index'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('recentReviews', []));
    });

    it('counts an item rated twice in one sitting once', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        resultsRow(resultsSession(), 'serendipity');

        $review = resultsStart();
        resultsRate($review, 'serendipity', ReviewRating::Easy);
        resultsRate($review, 'serendipity', ReviewRating::Hard);

        expect($review->ratings()->count())->toBe(1);

        test()->get(route('review.session', ['review' => $review->id]))->assertOk();

        test()->get(route('review.index'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('recentReviews.0.rated', 1)
            ->where('recentReviews.0.counts', ['hard' => 0, 'okay' => 0, 'easy' => 1]));
    });

    it('drops a rating the learner took back', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        $session = resultsSession();
        resultsRow($session, 'kept');
        resultsRow($session, 'taken-back');

        $review = resultsStart();
        resultsRate($review, 'kept', ReviewRating::Easy);
        resultsRate($review, 'taken-back', ReviewRating::Hard);
        test()->get(route('review.session', ['review' => $review->id]))->assertOk();

        test()->delete(route('review.undo', ['review' => $review->id]), [
            'kind' => 'vocabulary',
            'item_identity' => 'taken-back',
        ])->assertRedirect();

        expect($review->ratings()->count())->toBe(1);

        test()->get(route('review.index'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('recentReviews.0.rated', 1)
            ->where('recentReviews.0.counts', ['hard' => 0, 'okay' => 0, 'easy' => 1]));
    });

    it('keeps the moment the sitting ended, and a short look never claims the day', function (): void {
        Carbon::setTestNow('2026-11-05 14:30:00');
        resultsRow(resultsSession(), 'serendipity');

        $review = resultsStart();
        resultsRate($review, 'serendipity', ReviewRating::Easy);
        test()->post(route('review.finishEarly', ['review' => $review->id]))->assertRedirect();

        expect($review->refresh()->finished_at->toIso8601String())->toBe('2026-11-05T14:30:00+00:00')
            ->and($review->refresh()->completed_on)->toBeNull();

        test()->get(route('review.index'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('recentReviews.0.finished_at', fn (string $iso): bool => str_starts_with($iso, '2026-11-05T14:30:00'))
            ->where('todayReview.state', fn (string $state): bool => $state !== 'done'));
    });

    it('records a daily review that reached its end as the day’s own', function (): void {
        Carbon::setTestNow('2026-11-05 14:30:00');
        resultsRow(resultsSession(), 'serendipity');

        $review = resultsStart();
        resultsFinish($review);

        expect($review->refresh()->completed_on?->toDateString())->toBe('2026-11-05')
            ->and($review->refresh()->finished_at)->not->toBeNull();
    });
});

describe('a past sitting opened as a result', function (): void {
    it('shows what it held and how it went, read-only', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        $session = resultsSession('Past tenses');
        resultsRow($session, 'kept');
        resultsRow($session, 'left-out');

        $review = resultsStart();
        resultsRate($review, 'kept', ReviewRating::Okay);
        test()->post(route('review.finishEarly', ['review' => $review->id]))->assertRedirect();

        test()->get(route('review.results', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('review/Results')
                ->where('review.type', 'daily')
                ->where('review.type_label', 'Daily review')
                ->where('review.rated', 1)
                ->where('review.total', 2)
                ->where('review.counts', ['hard' => 0, 'okay' => 1, 'easy' => 0])
                ->has('sections', 1)
                ->where('sections.0.key', 'flashcard')
                ->where('sections.0.rated', 1)
                ->where('sections.0.items', fn (Collection $items): bool => $items
                    ->pluck('card.front')->sort()->values()->all() === ['kept', 'left-out']
                    && $items->pluck('rating')->sort()->values()->all() === [null, 'okay'])
                ->missing('sections.0.url'));
    });

    it('links each item back to the session it came from', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        $session = resultsSession();
        resultsRow($session, 'serendipity');

        $review = resultsStart();
        resultsFinish($review);

        $url = route('sessions.show', $session);

        test()->get(route('review.results', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('sections.0.items.0.card.session_url', $url));
    });

    it('changes nothing, and does not start the sitting again', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        resultsRow(resultsSession(), 'serendipity');

        $review = resultsStart();
        resultsFinish($review);

        $before = [
            'reviews' => Review::query()->count(),
            'ratings' => Rating::query()->count(),
            'finished' => $review->refresh()->finished_at?->toIso8601String(),
            'completed' => $review->refresh()->completed_on?->toDateString(),
        ];

        Carbon::setTestNow('2026-11-08 18:00:00');
        test()->get(route('review.results', ['review' => $review->id]))->assertOk();

        expect([
            'reviews' => Review::query()->count(),
            'ratings' => Rating::query()->count(),
            'finished' => $review->refresh()->finished_at?->toIso8601String(),
            'completed' => $review->refresh()->completed_on?->toDateString(),
        ])->toBe($before);
    });

    it('refuses a result that names no review', function (): void {
        test()->get(route('review.results', ['review' => 999]))->assertNotFound();
    });
});
