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
use App\Models\Topic;
use App\Queries\TodaysReview;
use App\Support\MistakeIdentity;
use App\Support\ReviewSettings;
use App\Support\ReviewSuggestion;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
    DB::disableQueryLog();
});

/**
 * Freeze the clock on a day and suggest with a neutral request, so the
 * learner's zone falls back to the application's (UTC in tests).
 */
function dailySuggest(int $size = ReviewSettings::SIZE, bool $excludingRatedToday = false): ReviewSuggestion
{
    return app(TodaysReview::class)->suggest($size, $excludingRatedToday, Request::create('/review'));
}

/**
 * A finished session the learner can study from.
 */
function dailySession(?CarbonImmutable $at = null): PracticeSession
{
    $at ??= CarbonImmutable::now();

    return PracticeSession::factory()
        ->finished()
        ->for(Topic::factory()->create())
        ->create([
            'created_at' => $at,
            'finished_at' => $at,
        ]);
}

/**
 * One table row saved the way the editor saves a word, a phrase or a mistake.
 */
function dailyRow(
    PracticeSession $session,
    string $subject,
    string $meaning = 'a meaning',
    FeedbackType $type = FeedbackType::Vocabulary,
): PracticeFeedbackEntry {
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => $type,
        'content_text' => $subject,
        'content_html' => $subject,
        'correction_text' => $meaning,
        'correction_html' => $meaning,
        'example' => '',
        'example_html' => [],
        'reason' => '',
        'position' => 0,
        'is_split' => true,
        'client_id' => (string) Str::uuid(),
    ]);
}

/**
 * Insert a rating for an item on the given day and rebuild its schedule, so
 * the fixtures carry real, grown-over-time schedules.
 */
function dailyRateOn(
    string $identity,
    ReviewRating $rating,
    string $day,
    FeedbackType $kind = FeedbackType::Vocabulary,
): void {
    $moment = CarbonImmutable::parse($day, config('app.timezone'));

    $review = new Review(['is_daily_review' => true, 'completed_on' => $moment->toDateString()]);
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
}

/**
 * Start a review through the screen's own button, as the card does.
 */
function dailyStart(object $test, bool $daily = true): ?Review
{
    $test->post(route('review.store'), ['daily' => $daily])->assertRedirect();

    return Review::query()->latest('id')->first();
}

/**
 * Rate one item through the route the learner uses.
 */
function dailyRate(
    object $test,
    Review $review,
    string $identity,
    ReviewRating $rating,
    FeedbackType $kind = FeedbackType::Vocabulary,
): void {
    $test->post(route('review.rate', ['review' => $review->id]), [
        'kind' => $kind->value,
        'item_identity' => $identity,
        'rating' => $rating->value,
    ])->assertRedirect();
}

/**
 * Work through a review, rating each item, and land on the finish screen.
 */
function dailyFinish(object $test, Review $review, ReviewRating $rating = ReviewRating::Easy): void
{
    foreach ($review->items()->get() as $item) {
        dailyRate($test, $review, (string) $item->item_identity, $rating, $item->kind);
    }

    $test->get(route('review.session', ['review' => $review->id]))->assertOk();
}

/**
 * How many of the suggestion's items are due and how many are new.
 *
 * @return array{total: int, due: int, new: int}
 */
function dailyCounts(ReviewSuggestion $suggestion): array
{
    $items = collect($suggestion->items)->pluck('identity');

    return [
        'total' => $items->count(),
        'due' => $items->filter(fn (string $i): bool => str_starts_with($i, 'due-'))->count(),
        'new' => $items->filter(fn (string $i): bool => str_starts_with($i, 'new-'))->count(),
    ];
}

/**
 * The due fixtures used by the ordering tests.
 */
function dailySeedDueItems(): void
{
    $session = dailySession();

    dailyRow($session, 'hard-due');
    dailyRateOn('hard-due', ReviewRating::Hard, '2026-10-25');

    dailyRow($session, 'difficult-due');
    dailyRateOn('difficult-due', ReviewRating::Okay, '2026-10-25');
    app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'difficult-due', false, CollectionItemStatus::StillDifficult);

    dailyRow($session, 'starred-due');
    dailyRateOn('starred-due', ReviewRating::Okay, '2026-10-29');
    app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'starred-due', true, null);

    dailyRow($session, 'oldest-due');
    dailyRateOn('oldest-due', ReviewRating::Okay, '2026-09-15');

    dailyRow($session, 'middle-due');
    dailyRateOn('middle-due', ReviewRating::Okay, '2026-10-05');

    dailyRow($session, 'newest-due');
    dailyRateOn('newest-due', ReviewRating::Okay, '2026-10-25');
}

describe('choosing today’s items', function (): void {
    it('offers the never-reviewed items from finished, non-archived sessions', function (): void {
        $session = dailySession();
        dailyRow($session, 'serendipity');
        dailyRow($session, 'cascade');

        $suggestion = dailySuggest();

        expect($suggestion->count())->toBe(2)
            ->and(collect($suggestion->items)->pluck('identity')->sort()->values()->all())
            ->toBe(['cascade', 'serendipity']);
    });

    it('leaves out items without a meaning, unfinished sessions and archived sessions', function (): void {
        dailyRow(dailySession(), 'meaningless', '');

        $unfinished = PracticeSession::factory()->for(Topic::factory()->create())->create();
        dailyRow($unfinished, 'unfinished-word');

        $archived = PracticeSession::factory()->archived()->create();
        dailyRow($archived, 'archived-word');

        dailyRow(dailySession(), 'visible-word');

        expect(dailySuggest()->items[0]['identity'])->toBe('visible-word')
            ->and(dailySuggest()->count())->toBe(1);
    });

    it('never suggests a learned item that was never reviewed', function (): void {
        dailyRow(dailySession(), 'learned-new');

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'learned-new', false, CollectionItemStatus::Learned);

        expect(dailySuggest()->count())->toBe(0);
    });

    it('offers a learned item again once it is due, and leaves it out before then', function (): void {
        Carbon::setTestNow('2026-10-20 10:00:00');
        dailyRow(dailySession(), 'learned-due');
        dailyRateOn('learned-due', ReviewRating::Easy, '2026-10-13');

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'learned-due', false, CollectionItemStatus::Learned);

        // Easy on the 13th comes back on the 20th — today. The learned mark
        // does not hide a due item.
        expect(dailySuggest()->count())->toBe(1);

        Carbon::setTestNow('2026-10-19 10:00:00');

        // The day before it is due, the learned item is left out.
        expect(dailySuggest()->count())->toBe(0);
    });

    it('fills with due items first: Hard, then Still difficult, then starred, then most overdue', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        dailySeedDueItems();

        $order = collect(dailySuggest(size: 8)->items)->pluck('identity')->all();

        expect($order)->toBe([
            'hard-due',
            'difficult-due',
            'starred-due',
            'oldest-due',
            'middle-due',
            'newest-due',
        ]);
    });

    it('puts new starred or Still difficult items ahead of other new items', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 5) as $i) {
            dailyRow($session, "plain-new-{$i}");
        }

        dailyRow($session, 'starred-new');
        dailyRow($session, 'difficult-new');

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'starred-new', true, null);
        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'difficult-new', false, CollectionItemStatus::StillDifficult);

        $items = collect(dailySuggest()->items)->pluck('identity')->all();

        // Both boosted items lead the new items; which one comes first is varied.
        expect(array_slice($items, 0, 2))->toEqualCanonicalizing(['starred-new', 'difficult-new']);
    });

    it('limits the new items when enough due items exist', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 6) as $i) {
            dailyRow($session, "due-{$i}");
            dailyRateOn("due-{$i}", ReviewRating::Okay, '2026-10-01');
        }

        foreach (range(1, 6) as $i) {
            dailyRow($session, "new-{$i}");
        }

        $counts = dailyCounts(dailySuggest());

        expect($counts['due'])->toBe(6)
            ->and($counts['new'])->toBe(2)
            ->and($counts['total'])->toBe(8);
    });

    it('lets new items fill the slots when too few items are due', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        dailyRow($session, 'due-1');
        dailyRateOn('due-1', ReviewRating::Okay, '2026-10-01');
        dailyRow($session, 'due-2');
        dailyRateOn('due-2', ReviewRating::Okay, '2026-10-01');

        foreach (range(1, 10) as $i) {
            dailyRow($session, "new-{$i}");
        }

        $counts = dailyCounts(dailySuggest());

        expect($counts['due'])->toBe(2)
            ->and($counts['new'])->toBe(6)
            ->and($counts['total'])->toBe(8);
    });

    it('fills with due items alone when there is enough overdue work', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 12) as $i) {
            dailyRow($session, "due-{$i}");
            dailyRateOn("due-{$i}", ReviewRating::Okay, '2026-10-01');
        }

        foreach (range(1, 4) as $i) {
            dailyRow($session, "new-{$i}");
        }

        $counts = dailyCounts(dailySuggest());

        expect($counts['total'])->toBe(8)
            ->and($counts['new'])->toBe(0);
    });

    it('mixes flashcards and fix-the-mistake by their target shares', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 10) as $i) {
            dailyRow($session, "word-{$i}");
            dailyRow($session, "said-{$i}", "told-{$i}", FeedbackType::Mistake);
        }

        $suggestion = dailySuggest();

        // 5 of 8 flashcards and 3 mistakes, as the one setting asks for.
        expect($suggestion->count())->toBe(8)
            ->and($suggestion->perKind())->toBe(['flashcard' => 5, 'mistake' => 3])
            ->and($suggestion->mix())->toBe('5 flashcards, 3 mistakes to fix')
            ->and($suggestion->minutes())->toBe(6);
    });

    it('renormalises over the kinds that have items and fills gaps from the other kind', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 10) as $i) {
            dailyRow($session, "word-{$i}");
        }

        dailyRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);

        $suggestion = dailySuggest();

        // The single mistake fills its share, and flashcards take the rest.
        expect($suggestion->count())->toBe(8)
            ->and($suggestion->perKind())->toBe(['flashcard' => 7, 'mistake' => 1]);
    });

    it('offers the one built kind when it is the only kind with items', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 10) as $i) {
            dailyRow($session, "said-{$i}", "told-{$i}", FeedbackType::Mistake);
        }

        $suggestion = dailySuggest();

        expect($suggestion->count())->toBe(8)
            ->and($suggestion->perKind())->toBe(['mistake' => 8])
            ->and($suggestion->mix())->toBe('8 mistakes to fix');
    });

    it('treats the same mistake pair as one item and a different pair as another', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        // The same correction on two different sentences is two mistakes.
        dailyRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);
        dailyRow($session, 'She has went', 'I have gone', FeedbackType::Mistake);
        dailyRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);

        $suggestion = dailySuggest();

        expect($suggestion->count())->toBe(2)
            ->and(collect($suggestion->items)->pluck('kind')->unique()->values()->all())
            ->toBe([FeedbackType::Mistake]);
    });

    it('treats the same word and the same phrase as two items', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();
        dailyRow($session, 'chase');
        dailyRow($session, 'chase', 'to hurry along', FeedbackType::Phrase);

        $suggestion = dailySuggest();

        expect($suggestion->count())->toBe(2)
            ->and(collect($suggestion->items)->pluck('kind')->map->value->all())
            ->toEqualCanonicalizing([FeedbackType::Vocabulary->value, FeedbackType::Phrase->value]);
    });

    it('never repeats an item, even when it appears in many sessions', function (): void {
        dailyRow(dailySession(), 'serendipity');
        dailyRow(dailySession(), 'serendipity');
        dailyRow(dailySession(), 'serendipity');

        expect(dailySuggest()->count())->toBe(1);
    });

    it('takes eight items from the one named setting', function (): void {
        $session = dailySession();

        foreach (range(1, 20) as $i) {
            dailyRow($session, "word-{$i}");
        }

        $review = dailyStart($this);

        expect($review->items)->toHaveCount(ReviewSettings::SIZE)
            ->and(ReviewSettings::SIZE)->toBe(8);
    });

    it('keeps the order stable once the review has started', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 10) as $i) {
            dailyRow($session, "word-{$i}");
        }

        $review = dailyStart($this);
        $before = $review->items->pluck('item_identity')->all();

        $this->get(route('review.session', ['review' => $review->id]))->assertOk();

        expect($review->items()->pluck('item_identity')->all())->toBe($before);
    });

    it('pulls a starred item into the sitting', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 10) as $i) {
            dailyRow($session, "word-{$i}");
        }

        // Nobody knows word-1 yet; starring it must pull it into the sitting.
        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'word-1', true, null);

        expect(collect(dailySuggest()->items)->pluck('identity')->all())->toContain('word-1');
    });

    it('offers starred and Still difficult due items before other due items', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        dailySeedDueItems();

        $order = collect(dailySuggest(size: 3)->items)->pluck('identity')->all();

        // The first three are the Hard item, the Still difficult one and the
        // starred one — the plain due items wait their turn.
        expect($order)->toBe(['hard-due', 'difficult-due', 'starred-due']);
    });

    it('builds the suggestion from current data, not from a saved copy', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        dailyRow(dailySession(), 'ephemeral');

        expect(dailySuggest()->count())->toBe(1);

        // Deleting the only session leaves nothing worth reviewing.
        PracticeSession::query()->each(fn (PracticeSession $session) => $session->delete());

        expect(dailySuggest()->count())->toBe(0);
    });
});

describe('the daily suggestion card', function (): void {
    it('shows the same honest summary on Home and on Review Home', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 3) as $i) {
            dailyRow($session, "card-word-{$i}");
        }

        $expected = (fn (Assert $page): Assert => $page
            ->where('todayReview.state', 'suggest')
            ->where('todayReview.count', 3)
            ->where('todayReview.minutes', 2)
            ->where('todayReview.mix', '3 flashcards'));

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $expected($page)->component('Home'));

        $this->get(route('review.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $expected($page)->component('review/Index'));
    });

    it('uses singular wording and a one-minute floor for a single item', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        dailyRow(dailySession(), 'solo');

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.count', 1)
                ->where('todayReview.minutes', 1)
                ->where('todayReview.mix', '1 flashcard'));
    });

    it('uses singular wording and the mistake time for a single mistake', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        dailyRow(dailySession(), 'I has went', 'I have gone', FeedbackType::Mistake);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.count', 1)
                ->where('todayReview.minutes', 1)
                ->where('todayReview.mix', '1 mistake to fix'));
    });

    it('matches the review that starts with what the card shows', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 4) as $i) {
            dailyRow($session, "start-word-{$i}");
        }

        // The card promises four flashcards, and the sitting delivers them.
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.count', 4));

        $review = dailyStart($this);

        expect($review->items)->toHaveCount(4);
    });

    it('shows You have reviewed today after the daily review is completed', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 8) as $i) {
            dailyRow($session, "day-word-{$i}");
        }

        $review = dailyStart($this);
        dailyFinish($this, $review);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'done')
                ->where('todayReview.canReviewMore', false));

        $this->get(route('review.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'done')
                ->where('todayReview.canReviewMore', false));
    });

    it('offers Review a bit more that leaves out what was already rated today', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 10) as $i) {
            dailyRow($session, "more-word-{$i}");
        }

        $review = dailyStart($this);
        expect($review->items)->toHaveCount(8);

        dailyFinish($this, $review);

        // Two unpicked words are left, and the card offers them.
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'done')
                ->where('todayReview.canReviewMore', true));

        $this->post(route('review.store'), ['more' => true])->assertRedirect();

        /** @var Review $bitMore */
        $bitMore = Review::query()->latest('id')->firstOrFail();

        $ratedToday = Rating::query()
            ->where('created_at', '>=', CarbonImmutable::parse('2026-11-03', config('app.timezone'))->setTimezone('UTC'))
            ->pluck('item_identity')
            ->all();

        expect($bitMore->is_daily_review)->toBeFalse()
            ->and($bitMore->items)->toHaveCount(2);

        foreach ($bitMore->items as $item) {
            expect($ratedToday)->not->toContain($item->item_identity);
        }

        // Finishing the extra sitting does not change the reviewed-today message.
        dailyFinish($this, $bitMore);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'done')
                ->where('todayReview.canReviewMore', false));
    });

    it('reviews mistakes in Review a bit more, leaving out what was already rated today', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 6) as $i) {
            dailyRow($session, "more-word-{$i}");
            dailyRow($session, "more-said-{$i}", "more-told-{$i}", FeedbackType::Mistake);
        }

        $review = dailyStart($this);

        expect($review->items)->toHaveCount(8)
            ->and($review->items->filter(fn ($item): bool => $item->kind === FeedbackType::Mistake))->toHaveCount(3);

        dailyFinish($this, $review);

        $this->post(route('review.store'), ['more' => true])->assertRedirect();

        /** @var Review $bitMore */
        $bitMore = Review::query()->latest('id')->firstOrFail();

        $ratedToday = Rating::query()
            ->where('created_at', '>=', CarbonImmutable::parse('2026-11-03', config('app.timezone'))->setTimezone('UTC'))
            ->pluck('item_identity')
            ->all();

        expect($bitMore->is_daily_review)->toBeFalse()
            ->and($bitMore->items)->toHaveCount(4)
            ->and($bitMore->items->filter(fn ($item): bool => $item->kind === FeedbackType::Mistake))->toHaveCount(3);

        foreach ($bitMore->items as $item) {
            expect($ratedToday)->not->toContain($item->item_identity);
        }
    });

    it('shows the friendly message instead of the option when nothing more remains', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 8) as $i) {
            dailyRow($session, "full-word-{$i}");
        }

        dailyFinish($this, dailyStart($this));

        $this->post(route('review.store'), ['more' => true])->assertRedirect(route('review.index'));

        // No sitting is created when nothing more is worth revisiting.
        expect(Review::query()->count())->toBe(1);
    });

    it('shrinks the suggestion when the daily review is left part-way', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 4) as $i) {
            dailyRow($session, "partway-{$i}");
        }

        $review = dailyStart($this);
        $firstItem = $review->items->first();
        dailyRate($this, $review, (string) $firstItem->item_identity, ReviewRating::Hard);

        // The card does not claim the day is reviewed, and the hard item is gone for now.
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'suggest')
                ->where('todayReview.count', 3));

        $this->get(route('review.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'suggest')
                ->where('todayReview.count', 3));

        expect($review->fresh()->completed_on)->toBeNull();
    });

    it('keeps the part-way ratings of an unfinished daily review', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 3) as $i) {
            dailyRow($session, "kept-{$i}");
        }

        $review = dailyStart($this);
        dailyRate($this, $review, 'kept-1', ReviewRating::Hard);

        expect(Rating::query()->where('item_identity', 'kept-1')->count())->toBe(1);

        $this->get(route('review.session', ['review' => $review->id]))->assertOk();
    });

    it('comes back fresh the next local day, across midnight and daylight saving', function (): void {
        // Winter time in Berlin, finishing the day's review late in the evening.
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-03 23:30:00', 'Europe/Berlin'));
        $session = dailySession();
        dailyRow($session, 'late-word');

        dailyFinish($this, dailyStart($this), ReviewRating::Hard);

        // The same local day, just before midnight: still marked as reviewed.
        $this->get(route('home', ['tz' => 'Europe/Berlin']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'done'));

        // Just after midnight: the fresh suggestion is back, and the Hard item is due.
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-04 00:30:00', 'Europe/Berlin'));

        $this->get(route('home', ['tz' => 'Europe/Berlin']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'suggest')
                ->where('todayReview.count', 1));

        // The spring-forward day itself: finishing it registers on the same local day.
        Carbon::setTestNow(CarbonImmutable::parse('2026-03-29 09:00:00', 'Europe/Berlin'));
        $dst = dailySession();
        dailyRow($dst, 'spring-word');

        dailyFinish($this, dailyStart($this), ReviewRating::Hard);

        $this->get(route('home', ['tz' => 'Europe/Berlin']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'done'));

        // Just after midnight of the spring-forward day: fresh again.
        Carbon::setTestNow(CarbonImmutable::parse('2026-03-30 00:30:00', 'Europe/Berlin'));

        $this->get(route('home', ['tz' => 'Europe/Berlin']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'suggest'));
    });

    it('shows the friendly nothing-due message when nothing worth reviewing is saved', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');

        // A mistake without its correction is waiting for a fix, not
        // reviewable, and no word or phrase exists at all.
        dailyRow(dailySession(), 'I has went', '', FeedbackType::Mistake);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'nothing'));

        $this->get(route('review.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'nothing')
                ->where('waitingForFix.mistakes', 1));
    });

    it('shows the friendly empty state when no session has been finished', function (): void {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'empty'));

        $this->get(route('review.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'empty'));
    });

    it('counts items waiting for a fix, linked to the collections', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        dailyRow($session, 'no-meaning-word', '');
        dailyRow($session, 'unsplittable -> line -> here', '', FeedbackType::Phrase);
        dailyRow($session, 'I has went', '', FeedbackType::Mistake);

        $this->get(route('review.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('waitingForFix.vocabulary', 1)
                ->where('waitingForFix.phrases', 1)
                ->where('waitingForFix.mistakes', 1));

        // When all three are fixed, the note disappears. Saving through the
        // model recomputes the stored markers the Collections read.
        PracticeFeedbackEntry::query()
            ->where('content_text', 'no-meaning-word')
            ->first()
            ?->update(['correction_text' => 'a real meaning']);
        PracticeFeedbackEntry::query()
            ->where('content_text', 'unsplittable -> line -> here')
            ->first()
            ?->update(['example' => 'a real example']);
        PracticeFeedbackEntry::query()
            ->where('content_text', 'I has went')
            ->first()
            ?->update(['correction_text' => 'I have gone']);

        $this->get(route('review.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('waitingForFix.vocabulary', 0)
                ->where('waitingForFix.phrases', 0)
                ->where('waitingForFix.mistakes', 0));
    });

    it('marks the suggestion review as the daily review, and the extra one not', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 3) as $i) {
            dailyRow($session, "mark-word-{$i}");
        }

        $daily = dailyStart($this);

        expect($daily->is_daily_review)->toBeTrue();

        $this->post(route('review.store'), ['more' => true])->assertRedirect();

        expect(Review::query()->latest('id')->firstOrFail()->is_daily_review)->toBeFalse();
    });
});

/**
 * Fill the database with the kind of pool a long-used local database holds:
 * thousands of reviewable words, with ratings and schedules behind a good
 * share of them, and stars behind a few.
 */
function bulkDailyPool(int $words): void
{
    $now = CarbonImmutable::now();
    $topic = Topic::factory()->create(['name' => 'Bulk topics']);

    $sessionIds = [];

    for ($i = 0; $i < 5; $i += 1) {
        $sessionIds[] = (int) DB::table('practice_sessions')->insertGetId([
            'topic_id' => $topic->id,
            'status' => 'finished',
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
            'finished_at' => $now->toDateTimeString(),
            'last_changed_at' => $now->toDateTimeString(),
        ]);
    }

    // Questions keep explicit, high ids so the entries can point at them
    // without a lookup per row.
    $questionBase = 100000;

    foreach (array_chunk(range(1, $words), 500) as $chunk) {
        $rows = [];

        foreach ($chunk as $i) {
            $rows[] = [
                'id' => $questionBase + $i,
                'practice_session_id' => $sessionIds[$i % 5],
                'position' => intdiv($i, 5) + 1,
                'question_html' => '<p>Question?</p>',
                'question_text' => 'Question?',
                'answer_html' => '<p>Answer.</p>',
                'answer_text' => 'Answer.',
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ];
        }

        DB::table('practice_questions')->insert($rows);
    }

    foreach (array_chunk(range(1, $words), 1000) as $chunk) {
        $rows = [];

        foreach ($chunk as $i) {
            $rows[] = [
                'practice_question_id' => $questionBase + $i,
                'type' => 'vocabulary',
                'content_text' => 'bulkword-'.$i,
                'content_html' => 'bulkword-'.$i,
                'correction_text' => 'a meaning',
                'correction_html' => 'a meaning',
                'example' => '',
                'example_html' => '',
                'reason' => '',
                'position' => 0,
                'is_split' => true,
                'collection_key' => 'bulkword-'.$i,
                'client_id' => (string) Str::uuid(),
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ];
        }

        DB::table('practice_feedback_entries')->insert($rows);
    }

    // A thousand mistakes with a correction sit alongside the words, so the
    // composer has both kinds to share the sitting between.
    $mistakeRows = [];

    foreach (range(1, 1000) as $i) {
        $said = 'I has went '.$i;
        $correction = 'I have gone '.$i;

        $mistakeRows[] = [
            'practice_question_id' => $questionBase + $i,
            'type' => 'mistake',
            'content_text' => $said,
            'content_html' => $said,
            'correction_text' => $correction,
            'correction_html' => '<strong>'.$correction.'</strong>',
            'example' => '',
            'example_html' => '',
            'reason' => 'Use the past participle.',
            'position' => 0,
            'is_split' => true,
            'collection_key' => MistakeIdentity::pairKey($said, $correction),
            'client_id' => (string) Str::uuid(),
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ];
    }

    foreach (array_chunk($mistakeRows, 1000) as $chunk) {
        DB::table('practice_feedback_entries')->insert($chunk);
    }

    // A thousand items carry a rating history and a schedule.
    $reviewIds = [];

    for ($r = 0; $r < 2; $r += 1) {
        $reviewIds[] = (int) DB::table('reviews')->insertGetId([
            'is_daily_review' => true,
            'completed_on' => $now->subDays(40)->toDateString(),
            'created_at' => $now->subDays(40)->toDateTimeString(),
            'updated_at' => $now->subDays(40)->toDateTimeString(),
        ]);
    }

    $ratingRows = [];
    $scheduleRows = [];
    $markRows = [];

    foreach (range(1, 1000) as $i) {
        $identity = 'bulkword-'.(2 * $i);

        foreach ([0, 1] as $offset) {
            $ratingRows[] = [
                'review_id' => $reviewIds[$offset],
                'kind' => 'vocabulary',
                'item_identity' => $identity,
                'rating' => $i % 3 === 0 ? 'hard' : 'okay',
                'unique_key' => Rating::keyFor($reviewIds[$offset], FeedbackType::Vocabulary, $identity),
                'created_at' => $now->subDays(40 - $offset)->toDateTimeString(),
                'updated_at' => $now->subDays(40 - $offset)->toDateTimeString(),
            ];
        }

        $overdue = $i % 2 === 0;

        $scheduleRows[] = [
            'kind' => 'vocabulary',
            'item_identity' => $identity,
            'due_on' => $overdue ? $now->subDays(($i % 60) + 1)->toDateString() : $now->addDays(30)->toDateString(),
            'last_rated_on' => $now->subDays(39)->toDateString(),
            'last_rating' => $overdue ? 'hard' : 'okay',
            'rated_count' => 2,
            'easy_run' => 0,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ];

        if ($i <= 300) {
            $markRows[] = [
                'kind' => 'vocabulary',
                'item_key' => CollectionItemMark::keyFor(FeedbackType::Vocabulary, $identity),
                'group_key' => $identity,
                'starred' => true,
                'status' => null,
                'notes_html' => '',
                'notes_text' => '',
                'notes_search_text' => null,
                'notes_version' => 0,
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ];
        }
    }

    // Two hundred mistakes also carry history, so due work exists in both
    // kinds and the composer has a real choice to make.
    foreach (range(1, 200) as $i) {
        $identity = MistakeIdentity::pairKey('I has went '.$i, 'I have gone '.$i);

        foreach ([0, 1] as $offset) {
            $ratingRows[] = [
                'review_id' => $reviewIds[$offset],
                'kind' => 'mistake',
                'item_identity' => $identity,
                'rating' => $i % 3 === 0 ? 'hard' : 'okay',
                'unique_key' => Rating::keyFor($reviewIds[$offset], FeedbackType::Mistake, $identity),
                'created_at' => $now->subDays(40 - $offset)->toDateTimeString(),
                'updated_at' => $now->subDays(40 - $offset)->toDateTimeString(),
            ];
        }

        $overdue = $i % 2 === 0;

        $scheduleRows[] = [
            'kind' => 'mistake',
            'item_identity' => $identity,
            'due_on' => $overdue ? $now->subDays(($i % 60) + 1)->toDateString() : $now->addDays(30)->toDateString(),
            'last_rated_on' => $now->subDays(39)->toDateString(),
            'last_rating' => $overdue ? 'hard' : 'okay',
            'rated_count' => 2,
            'easy_run' => 0,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ];
    }

    foreach (array_chunk($ratingRows, 500) as $chunk) {
        DB::table('ratings')->insert($chunk);
    }

    foreach (array_chunk($scheduleRows, 500) as $chunk) {
        DB::table('review_schedules')->insert($chunk);
    }

    foreach (array_chunk($markRows, 300) as $chunk) {
        DB::table('collection_item_marks')->insert($chunk);
    }
}

describe('the daily review at scale', function (): void {
    it('builds the suggestion in a bounded number of queries with thousands of items', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        bulkDailyPool(5000);

        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($queries)->toBeLessThanOrEqual(20);

        // And the suggestion at this size is full and honest, in both kinds.
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'suggest')
                ->where('todayReview.count', 8)
                ->where('todayReview.mix', '5 flashcards, 3 mistakes to fix'));
    });

    it('keeps the same bounded number of queries for a small pool', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = dailySession();

        foreach (range(1, 26) as $i) {
            dailyRow($session, "small-{$i}");
        }

        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // The number of queries does not grow with the number of items.
        expect($queries)->toBeLessThanOrEqual(20);
    });
});
