<?php

declare(strict_types=1);

use App\Actions\Collections\MoveMarkAfterEdit;
use App\Actions\PracticeQuestions\SavePracticeQuestions;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewSchedule;
use App\Models\Topic;
use App\Queries\TodaysReview;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * A finished session the learner can study from.
 */
function schedSession(?CarbonImmutable $at = null): PracticeSession
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
 * One vocabulary or phrase row saved the way the editor saves it.
 */
function schedRow(
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
 * Start a review through the screen's own button and return the sitting it made.
 */
function schedStart(object $test): Review
{
    $test->post(route('review.store'))->assertRedirect();

    /** @var Review $review */
    $review = Review::query()->latest('id')->firstOrFail();

    return $review;
}

/**
 * Rate one item through the same route the screen uses.
 */
function schedRate(object $test, Review $review, PracticeFeedbackEntry|string $item, ReviewRating $rating): void
{
    $identity = $item instanceof PracticeFeedbackEntry
        ? (string) $item->collection_key
        : $item;

    $kind = $item instanceof PracticeFeedbackEntry
        ? $item->type->value
        : FeedbackType::Vocabulary->value;

    $test->post(route('review.rate', ['review' => $review->id]), [
        'kind' => $kind,
        'item_identity' => $identity,
        'rating' => $rating->value,
    ])->assertRedirect();
}

/**
 * The stored schedule for one item, or null when there is none.
 */
function schedEntry(FeedbackType $kind, string $identity): ?ReviewSchedule
{
    return ReviewSchedule::query()
        ->where('kind', $kind->value)
        ->where('item_identity', $identity)
        ->first();
}

describe('the schedule', function (): void {
    it('schedules a Hard item for the next day', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        $entry = schedRow($session, 'serendipity');

        $review = schedStart($this);
        schedRate($this, $review, $entry, ReviewRating::Hard);

        $schedule = ReviewSchedule::query()->sole();

        expect($schedule->due_on->toDateString())->toBe('2026-11-04')
            ->and($schedule->rated_count)->toBe(1)
            ->and($schedule->easy_run)->toBe(0)
            ->and($schedule->last_rating)->toBe(ReviewRating::Hard);
    });

    it('schedules an Okay item for three days later and an Easy item for a week later', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        schedRow($session, 'okayish');
        schedRow($session, 'easyish');

        $review = schedStart($this);

        foreach ($review->items as $item) {
            $rating = $item->item_identity === 'okayish' ? ReviewRating::Okay : ReviewRating::Easy;
            schedRate($this, $review, (string) $item->item_identity, $rating);
        }

        expect(schedEntry(FeedbackType::Vocabulary, 'okayish')->due_on->toDateString())->toBe('2026-11-06')
            ->and(schedEntry(FeedbackType::Vocabulary, 'easyish')->due_on->toDateString())->toBe('2026-11-10');
    });

    it('keeps overdue items due until they are reviewed', function (): void {
        Carbon::setTestNow('2026-10-20 09:00:00');
        $session = schedSession();
        $entry = schedRow($session, 'forgetful');

        $review = schedStart($this);
        schedRate($this, $review, $entry, ReviewRating::Hard);

        Carbon::setTestNow('2026-11-03 09:00:00');

        // The item is nearly two weeks overdue and is still suggested.
        expect(app(TodaysReview::class)->suggest()->count())->toBe(1);
    });

    it('puts a Hard item due the very next day', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        $entry = schedRow($session, 'serendipity');

        $review = schedStart($this);
        schedRate($this, $review, $entry, ReviewRating::Hard);

        Carbon::setTestNow('2026-11-04 10:00:00');

        expect(app(TodaysReview::class)->suggest()->count())->toBe(1)
            ->and(app(TodaysReview::class)->suggest()->items[0]['identity'])->toBe('serendipity');
    });

    it('grows the gap for repeated Easy ratings and stops at the cap', function (): void {
        Carbon::setTestNow('2026-01-05 09:00:00');
        $session = schedSession();
        $entry = schedRow($session, 'garden');

        $expectations = [
            '2026-01-05' => '2026-01-12',
            '2026-01-12' => '2026-01-26',
            '2026-01-26' => '2026-02-25',
            '2026-02-25' => '2026-04-26',
            '2026-04-26' => '2026-06-25',
        ];

        foreach ($expectations as $day => $due) {
            Carbon::setTestNow($day.' 09:00:00');

            $review = schedStart($this);
            expect($review->items)->toHaveCount(1);

            schedRate($this, $review, $entry, ReviewRating::Easy);

            expect(schedEntry(FeedbackType::Vocabulary, 'garden')->due_on->toDateString())->toBe($due);
        }

        expect(schedEntry(FeedbackType::Vocabulary, 'garden')->easy_run)->toBe(5);
    });

    it('gives the same schedule whichever review the rating arrives in', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        schedRow($session, 'serendipity');

        // Two tabs, the same sitting of items, rated in order.
        $first = schedStart($this);
        $second = schedStart($this);

        $item = $first->items->sole();

        schedRate($this, $first, 'serendipity', ReviewRating::Hard);
        schedRate($this, $second, 'serendipity', ReviewRating::Easy);

        // Hard was given first, Easy second: the Easy run is 1 and the item
        // comes back in a week. Had the order been reversed, the Hard would
        // have reset the run and won.
        $schedule = schedEntry(FeedbackType::Vocabulary, 'serendipity');

        expect($schedule->easy_run)->toBe(1)
            ->and($schedule->last_rating)->toBe(ReviewRating::Easy)
            ->and($schedule->due_on->toDateString())->toBe('2026-11-10')
            ->and($item->item_identity)->toBe('serendipity');
    });

    it('treats a repeated and retried request as one rating', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        schedRow($session, 'serendipity');

        $review = schedStart($this);
        $item = $review->items->sole();

        $payload = [
            'kind' => $item->kind->value,
            'item_identity' => (string) $item->item_identity,
            'rating' => ReviewRating::Easy->value,
        ];

        $this->post(route('review.rate', ['review' => $review->id]), $payload)->assertRedirect();
        $this->post(route('review.rate', ['review' => $review->id]), $payload)->assertRedirect();

        $schedule = ReviewSchedule::query()->sole();

        expect($schedule->rated_count)->toBe(1)
            ->and(Rating::query()->count())->toBe(1);
    });

    it('restores the schedule when the last rating is taken back', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        schedRow($session, 'serendipity');

        $first = schedStart($this);
        schedRate($this, $first, 'serendipity', ReviewRating::Easy);

        Carbon::setTestNow('2026-11-10 10:00:00');
        $second = schedStart($this);
        schedRate($this, $second, 'serendipity', ReviewRating::Hard);

        expect(schedEntry(FeedbackType::Vocabulary, 'serendipity')->due_on->toDateString())->toBe('2026-11-11')
            ->and(schedEntry(FeedbackType::Vocabulary, 'serendipity')->easy_run)->toBe(0);

        // Taking back the Hard rating restores the schedule the item had
        // before it: Easy on the 3rd, due in a week.
        $this->delete(route('review.undo', ['review' => $second->id]), [
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => 'serendipity',
        ])->assertRedirect();

        $schedule = schedEntry(FeedbackType::Vocabulary, 'serendipity');

        expect($schedule->last_rating)->toBe(ReviewRating::Easy)
            ->and($schedule->easy_run)->toBe(1)
            ->and($schedule->due_on->toDateString())->toBe('2026-11-10');
    });

    it('marks the item new again when the last remaining rating is undone', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        $entry = schedRow($session, 'serendipity');

        $review = schedStart($this);
        schedRate($this, $review, $entry, ReviewRating::Hard);

        expect(schedEntry(FeedbackType::Vocabulary, 'serendipity'))->not->toBeNull();

        $this->delete(route('review.undo', ['review' => $review->id]), [
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => 'serendipity',
        ])->assertRedirect();

        expect(schedEntry(FeedbackType::Vocabulary, 'serendipity'))->toBeNull();
    });

    it('recomputes the same stored schedule from the history', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        schedRow($session, 'serendipity');
        schedRow($session, 'nebula');
        schedRow($session, 'cascade');

        $review = schedStart($this);

        foreach ($review->items as $index => $item) {
            $rating = [ReviewRating::Hard, ReviewRating::Okay, ReviewRating::Easy][$index];
            schedRate($this, $review, (string) $item->item_identity, $rating);
        }

        $before = ReviewSchedule::query()->orderBy('id')->get()->toArray();

        $this->artisan('review:rebuild-schedules')->assertSuccessful();
        $this->artisan('review:rebuild-schedules')->assertSuccessful();

        expect(ReviewSchedule::query()->orderBy('id')->get()->toArray())->toBe($before);
    });

    it('keeps the local day across the midnight line, in the learner\'s own zone', function (): void {
        // 2026-11-03 00:30 in Berlin is still 2026-11-02 in UTC.
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-03 00:30:00', 'Europe/Berlin'));
        $session = schedSession();
        $entry = schedRow($session, 'serendipity');

        $review = schedStart($this);
        $this->post(route('review.rate', ['review' => $review->id, 'tz' => 'Europe/Berlin']), [
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => (string) $entry->collection_key,
            'rating' => ReviewRating::Hard->value,
        ])->assertRedirect();

        $schedule = schedEntry(FeedbackType::Vocabulary, 'serendipity');

        // The rating was given on the 3rd in Berlin, so the Hard item is due on the 4th.
        expect($schedule->last_rated_on->toDateString())->toBe('2026-11-03')
            ->and($schedule->due_on->toDateString())->toBe('2026-11-04');

        // In UTC the same moment is still the 2nd, and the due day moves with the learner.
        Carbon::setTestNow('2026-11-02 23:30:00');
        $session = schedSession();
        $entry = schedRow($session, 'worldpeace');

        $review = schedStart($this);
        $this->post(route('review.rate', ['review' => $review->id, 'tz' => 'UTC']), [
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => (string) $entry->collection_key,
            'rating' => ReviewRating::Hard->value,
        ])->assertRedirect();

        expect(schedEntry(FeedbackType::Vocabulary, 'worldpeace')->due_on->toDateString())->toBe('2026-11-03');
    });
});

describe('the schedule through a typo fix', function (): void {
    it('rebuilds the new identity and drops the old one', function (): void {
        Carbon::setTestNow('2026-11-01 10:00:00');
        $session = schedSession();
        $entry = schedRow($session, 'teh', 'slow to act');

        $review = schedStart($this);
        schedRate($this, $review, $entry, ReviewRating::Hard);

        $entry->update(['content_text' => 'the']);

        Carbon::setTestNow('2026-11-03 10:00:00');
        app(MoveMarkAfterEdit::class)->handle(FeedbackType::Vocabulary, 'teh', 'the', $entry->id);

        expect(schedEntry(FeedbackType::Vocabulary, 'teh'))->toBeNull();

        $moved = schedEntry(FeedbackType::Vocabulary, 'the');

        expect($moved)->not->toBeNull()
            ->and($moved->due_on->toDateString())->toBe('2026-11-02')
            ->and($moved->rated_count)->toBe(1)
            ->and($moved->last_rating)->toBe(ReviewRating::Hard);
    });

    it('keeps the rating with the old word when it still appears elsewhere', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $first = schedSession();
        $entry = schedRow($first, 'teh', 'slow to act');
        schedRow(schedSession(), 'teh', 'slow to act');

        $review = schedStart($this);
        schedRate($this, $review, $entry, ReviewRating::Hard);

        $entry->update(['content_text' => 'the']);
        app(MoveMarkAfterEdit::class)->handle(FeedbackType::Vocabulary, 'teh', 'the', $entry->id);

        expect(schedEntry(FeedbackType::Vocabulary, 'teh'))->not->toBeNull()
            ->and(schedEntry(FeedbackType::Vocabulary, 'the'))->toBeNull();
    });

    it('keeps the new identity’s existing rating, and the old one with the old word', function (): void {
        Carbon::setTestNow('2026-11-01 10:00:00');
        $first = schedSession();
        $entry = schedRow($first, 'teh', 'slow to act');

        $firstReview = schedStart($this);
        schedRate($this, $firstReview, $entry, ReviewRating::Hard);

        Carbon::setTestNow('2026-11-02 10:00:00');
        schedRow(schedSession(), 'the', 'an article');

        $review = schedStart($this);

        foreach ($review->items as $item) {
            // Only the new identity is rated for the second time here; the
            // old one already carries its rating from the first session.
            if ($item->item_identity === 'the') {
                schedRate($this, $review, 'the', ReviewRating::Hard);
            }
        }

        expect(Rating::query()->count())->toBe(2);

        $entry->update(['content_text' => 'the']);
        app(MoveMarkAfterEdit::class)->handle(FeedbackType::Vocabulary, 'teh', 'the', $entry->id);

        // Nothing is overwritten and nothing is double counted.
        expect(Rating::query()->where('item_identity', 'teh')->count())->toBe(1)
            ->and(Rating::query()->where('item_identity', 'the')->count())->toBe(1)
            ->and(schedEntry(FeedbackType::Vocabulary, 'teh')->rated_count)->toBe(1)
            ->and(schedEntry(FeedbackType::Vocabulary, 'the')->rated_count)->toBe(1);
    });

    it('moves nothing when only the formatting or the case changed', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        $entry = schedRow($session, 'serendipity');

        $review = schedStart($this);
        schedRate($this, $review, 'serendipity', ReviewRating::Hard);

        // The saving hook keeps the identity under its normalised words, so
        // the editor passes the same identity back and the rule stands still.
        $entry->update(['content_text' => '  SERENDIPITY  ']);

        app(MoveMarkAfterEdit::class)->handle(
            FeedbackType::Vocabulary,
            'serendipity',
            (string) $entry->collection_key,
            $entry->id,
        );

        expect(schedEntry(FeedbackType::Vocabulary, 'serendipity'))->not->toBeNull()
            ->and(Rating::query()->sole()->item_identity)->toBe('serendipity');
    });

    it('moves nothing in an unfinished session, through the real saving path', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $finished = schedSession();
        $entry = schedRow($finished, 'teh', 'slow to act');

        $review = schedStart($this);
        schedRate($this, $review, $entry, ReviewRating::Hard);

        $unfinished = PracticeSession::factory()
            ->for(Topic::factory()->create())
            ->create([
                'created_at' => CarbonImmutable::now(),
                'updated_at' => CarbonImmutable::now(),
            ]);
        $unfinishedRow = PracticeFeedbackEntry::factory()->for(
            PracticeQuestion::factory()->for($unfinished)->create(['position' => 1])
        )->create([
            'type' => FeedbackType::Vocabulary,
            'content_text' => 'teh',
            'content_html' => 'teh',
            'correction_text' => 'slow to act',
            'correction_html' => 'slow to act',
            'example' => '',
            'example_html' => [],
            'reason' => '',
            'position' => 0,
            'is_split' => true,
            'client_id' => (string) Str::uuid(),
        ]);

        app(SavePracticeQuestions::class)->handle($unfinished, [[
            'question_html' => '<p>What did you notice?</p>',
            'answer_html' => '<p>A sentence.</p>',
            'feedback' => [
                'vocabulary' => [[
                    'id' => $unfinishedRow->client_id,
                    'text' => 'the',
                    'meaning' => 'slow to act',
                ]],
            ],
        ]]);

        expect(Rating::query()->where('item_identity', 'teh')->count())->toBe(1)
            ->and(schedEntry(FeedbackType::Vocabulary, 'teh'))->not->toBeNull()
            ->and(schedEntry(FeedbackType::Vocabulary, 'the'))->toBeNull();
    });
});

describe('disappearing and returning items', function (): void {
    it('keeps the schedule when the item is archived and returns it with the restore', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        schedRow($session, 'serendipity');

        $review = schedStart($this);
        schedRate($this, $review, 'serendipity', ReviewRating::Hard);

        $this->postJson(route('sessions.archive', $session))->assertOk();

        expect(schedEntry(FeedbackType::Vocabulary, 'serendipity'))->not->toBeNull()
            ->and(app(TodaysReview::class)->suggest()->count())->toBe(0);

        $this->postJson(route('sessions.restore', $session))->assertOk();

        // A Hard item comes back the next day, once the session is visible again.
        Carbon::setTestNow('2026-11-04 10:00:00');

        $suggestion = app(TodaysReview::class)->suggest();

        expect($suggestion->count())->toBe(1)
            ->and($suggestion->items[0]['identity'])->toBe('serendipity');
    });

    it('keeps the schedule when its only session is deleted', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        schedRow($session, 'serendipity');

        $review = schedStart($this);
        schedRate($this, $review, 'serendipity', ReviewRating::Hard);

        $preview = $this->getJson(route('sessions.deletePreview', ['session' => $session->id, 'tz' => 'UTC']))
            ->assertOk()
            ->json();

        $this->deleteJson(route('sessions.deletePermanently', ['sessionId' => $session->id]), [
            'token' => $preview['token'],
            'tz' => 'UTC',
        ])->assertOk();

        expect(app(TodaysReview::class)->suggest()->count())->toBe(0)
            ->and(schedEntry(FeedbackType::Vocabulary, 'serendipity'))->not->toBeNull();
    });
});

describe('what reviewing does not touch', function (): void {
    it('changes no session or mark, in either direction', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        schedRow($session, 'serendipity');

        app(App\Actions\Collections\SaveItemMark::class)->handle(
            FeedbackType::Vocabulary,
            'serendipity',
            true,
            App\Enums\CollectionItemStatus::StillDifficult,
        );

        $sessionsBefore = PracticeSession::query()->get(['id', 'updated_at'])->map(fn ($s) => $s->updated_at?->toIso8601String())->toArray();
        $marksBefore = App\Models\CollectionItemMark::query()->get()->map(fn ($m) => [$m->starred, $m->status?->value, $m->notes_version])->toArray();

        $review = schedStart($this);
        schedRate($this, $review, 'serendipity', ReviewRating::Easy);

        $sessionsAfter = PracticeSession::query()->get(['id', 'updated_at'])->map(fn ($s) => $s->updated_at?->toIso8601String())->toArray();
        $marksAfter = App\Models\CollectionItemMark::query()->get()->map(fn ($m) => [$m->starred, $m->status?->value, $m->notes_version])->toArray();

        expect($sessionsAfter)->toBe($sessionsBefore)
            ->and($marksAfter)->toBe($marksBefore);
    });

    it('refuses a rating for an item that is not in the review', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        schedRow($session, 'serendipity');

        $review = schedStart($this);

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => 'something-else',
            'rating' => ReviewRating::Hard->value,
        ])->assertNotFound();
    });

    it('refuses to undo a rating for a review that does not exist', function (): void {
        $this->delete(route('review.undo', ['review' => 9999]), [
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => 'serendipity',
        ])->assertNotFound();
    });

    it('finishes the daily review only by reaching its finish screen', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = schedSession();
        schedRow($session, 'serendipity');

        // A suggestion review, started from the card, is the daily review.
        $this->post(route('review.store'), ['daily' => true])->assertRedirect();
        /** @var Review $review */
        $review = Review::query()->latest('id')->firstOrFail();

        expect($review->is_daily_review)->toBeTrue()
            ->and($review->completed_on)->toBeNull();

        schedRate($this, $review, 'serendipity', ReviewRating::Easy);

        $this->get(route('review.session', ['review' => $review->id]))->assertOk();

        $finished = $review->fresh();

        expect($finished->completed_on?->toDateString())->toBe('2026-11-03');
    });
});
