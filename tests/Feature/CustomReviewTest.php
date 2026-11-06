<?php

declare(strict_types=1);

use App\Actions\Collections\SaveItemMark;
use App\Actions\Reviews\SyncReviewSchedule;
use App\Enums\CollectionItemStatus;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
    DB::disableQueryLog();
});

/**
 * A finished session the learner can study from, on its own topic.
 */
function customSession(string $topic = 'Everyday talk'): PracticeSession
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

/**
 * One table row saved the way the editor saves a word, a phrase or a mistake.
 */
function customRow(
    PracticeSession $session,
    string $subject,
    string $meaning = 'a meaning',
    FeedbackType $type = FeedbackType::Vocabulary,
): PracticeFeedbackEntry {
    $question = customQuestionRow($session);

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
    ])->refresh();
}

/** A grammar note with the words a blank can be built from. */
function customNote(PracticeSession $session, string $text = 'Use the past simple here.'): PracticeFeedbackEntry
{
    $question = customQuestionRow($session);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::GrammarNote,
        'content_html' => '<p>Use the <strong>past simple</strong> here.</p>',
        'content_text' => $text,
        'position' => 0,
        'is_split' => true,
        'client_id' => (string) Str::uuid(),
    ])->refresh();
}

/** A past question with the natural version that makes it reviewable. */
function customQuestionItem(PracticeSession $session): PracticeQuestion
{
    $question = customQuestionRow($session);

    $question->update(['question_text' => 'How was your weekend?', 'question_html' => '<p>How was your weekend?</p>']);

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::NaturalVersion,
        'content_text' => 'It was relaxing.',
        'content_html' => '<p>It was relaxing.</p>',
    ]);

    return $question->refresh();
}

/** The bare question a row or a note hangs on. */
function customQuestionRow(PracticeSession $session): PracticeQuestion
{
    return PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);
}

/**
 * A rating saved on a given day, with the schedule grown from it, so the
 * fixtures carry real histories rather than hand-written schedule rows.
 */
function customRateOn(
    string $identity,
    ReviewRating $rating,
    string $day,
    FeedbackType $kind = FeedbackType::Vocabulary,
): void {
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
}

/** Open the page with these choices, exactly as the address carries them. */
function customVisitPage(object $test, array $query = []): TestResponse
{
    return $test->get(route('review.custom', $query));
}

/** Start a sitting from the page's own button. */
function customStart(object $test, array $query = []): TestResponse
{
    return $test->post(route('review.customStore', $query));
}

/** The identities of the items a started sitting holds, in order. */
function customItemIdentities(Review $review): array
{
    return array_map(
        fn (object $item): string => $item->kind->value.':'.$item->item_identity,
        $review->items()->orderBy('position')->get()->all(),
    );
}

describe('the custom review page', function (): void {
    it('opens from its own address with nothing chosen', function (): void {
        customVisitPage($this)->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('review/Custom')
            ->where('choices', [
                'topics' => [],
                'collections' => [],
                'starred' => false,
                'learned' => false,
                'size' => ReviewSettings::SIZE,
            ])
            ->where('dailySize', ReviewSettings::SIZE)
            ->where('sizes', ReviewSettings::CUSTOM_SIZES)
            ->has('collections', 5)
            ->where('collections.0.label', 'Words')
            ->where('collections.4.label', 'Past questions'));
    });

    it('counts what is reviewable as soon as the page opens', function (): void {
        $session = customSession();
        customRow($session, 'serendipity');
        customRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);
        customQuestionItem($session);
        customNote($session);

        customVisitPage($this)->assertInertia(fn (Assert $page): Assert => $page
            ->where('preview.count', 4)
            ->where('preview.comfortable', true)
            ->where('preview.size', ReviewSettings::SIZE));
    });

    it('counts the items a started sitting holds', function (): void {
        $session = customSession();

        foreach (range(1, 12) as $number) {
            customRow($session, "word-{$number}");
        }

        customVisitPage($this, ['size' => 5])->assertInertia(fn (Assert $page): Assert => $page
            ->where('preview.count', 5)
            ->where('preview.size', 5));

        expect(Review::query()->count())->toBe(0);

        customStart($this, ['size' => 5])->assertSessionHasNoErrors();

        /** @var Review $review */
        $review = Review::query()->latest('id')->firstOrFail();
        expect($review->items()->count())->toBe(5);
    });

    it('says plainly when the choices match only a few items', function (): void {
        $session = customSession();
        customRow($session, 'serendipity');
        customRow($session, 'cascade');

        customVisitPage($this)->assertInertia(fn (Assert $page): Assert => $page
            ->where('preview.count', 2)
            ->where('preview.comfortable', false));
    });

    it('says when nothing matches and offers no sitting', function (): void {
        customSession();
        customRow(customSession(), 'serendipity');

        customVisitPage($this, ['collections' => 'mistakes'])
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('preview.count', 0)
                ->where('preview.comfortable', false)
                ->where('preview.mix', '')
                ->where('preview.minutes', 0)
                ->where('preview.kinds', 0));

        expect(Review::query()->count())->toBe(0);
    });

    it('starts nothing when the choices match nothing', function (): void {
        customRow(customSession(), 'serendipity');

        customStart($this, ['collections' => 'mistakes'])
            ->assertRedirect(route('review.custom', ['collections' => 'mistakes']));

        expect(Review::query()->count())->toBe(0);
    });

    it('sends a hand-made size back to the page', function (): void {
        customRow(customSession(), 'serendipity');

        customVisitPage($this, ['size' => 7])
            ->assertRedirect()
            ->assertSessionHasErrors('size');

        customStart($this, ['size' => 7])
            ->assertRedirect()
            ->assertSessionHasErrors('size');

        expect(Review::query()->count())->toBe(0);
    });
});

describe('the choices', function (): void {
    it('offers only the topics that still hold reviewable items, alphabetically and counted', function (): void {
        $words = customSession('Zebra topics');
        customRow($words, 'serendipity');
        customRow($words, 'cascade');

        customRow(customSession('Alpha topics'), 'meeting');

        // A topic whose sessions are unfinished or archived is not offered.
        $unfinished = PracticeSession::factory()->for(Topic::factory()->create(['name' => 'Half done']))->create();
        customRow($unfinished, 'incomplete');

        $archived = PracticeSession::factory()->archived()->for(Topic::factory()->create(['name' => 'Put away']))->create();
        customRow($archived, 'archived');

        customVisitPage($this)->assertInertia(fn (Assert $page): Assert => $page
            ->has('topics', 2)
            ->where('topics.0.name', 'Alpha topics')
            ->where('topics.0.count', 1)
            ->where('topics.1.name', 'Zebra topics')
            ->where('topics.1.count', 2));
    });

    it('narrows the count to the topics chosen', function (): void {
        customRow(customSession('Alpha topics'), 'meeting');

        $zebra = customSession('Zebra topics');
        customRow($zebra, 'serendipity');
        customRow($zebra, 'cascade');

        /** @var int $zebraId */
        $zebraId = Topic::query()->where('name', 'Zebra topics')->value('id');

        customVisitPage($this, ['topics' => (string) $zebraId])
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('choices.topics', [$zebraId])
                ->where('preview.count', 2));

        customStart($this, ['topics' => (string) $zebraId])->assertSessionHasNoErrors();

        /** @var Review $review */
        $review = Review::query()->latest('id')->firstOrFail();
        expect(collect(customItemIdentities($review))->sort()->values()->all())
            ->toBe(['vocabulary:cascade', 'vocabulary:serendipity']);
    });

    it('keeps every collection when none is chosen and narrows to the ones named', function (): void {
        $session = customSession();
        customRow($session, 'serendipity');
        customRow($session, 'in a nutshell', 'a short way to say it', FeedbackType::Phrase);
        customRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);
        customQuestionItem($session);
        customNote($session);

        customVisitPage($this)->assertInertia(fn (Assert $page): Assert => $page
            ->where('choices.collections', [])
            ->where('preview.count', 5));

        customVisitPage($this, ['collections' => 'words,mistakes'])
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('choices.collections', ['words', 'mistakes'])
                ->where('preview.count', 2)
                ->where('preview.kinds', 2));
    });

    it('drops a collection an address invented', function (): void {
        customRow(customSession(), 'serendipity');

        customVisitPage($this, ['collections' => 'widgets'])
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('choices.collections', [])
                ->where('preview.count', 1));
    });

    it('leaves past questions and grammar notes out of a starred-only count', function (): void {
        $session = customSession();
        customRow($session, 'serendipity');
        customRow($session, 'cascade');
        customRow($session, 'unstarred');
        customQuestionItem($session);
        customNote($session);

        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'serendipity', true, null);
        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'cascade', true, null);

        customVisitPage($this, ['starred' => '1'])
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('choices.starred', true)
                ->where('preview.count', 2));

        customStart($this, ['starred' => '1'])->assertSessionHasNoErrors();

        /** @var Review $review */
        $review = Review::query()->latest('id')->firstOrFail();
        expect(collect(customItemIdentities($review))->every(
            fn (string $identity): bool => str_starts_with($identity, 'vocabulary:'),
        ))->toBeTrue();
    });

    it('leaves Learned items out until the learner asks for them', function (): void {
        $session = customSession();
        customRow($session, 'serendipity');
        customRow($session, 'cascade');
        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'cascade', false, CollectionItemStatus::Learned);

        customVisitPage($this)->assertInertia(fn (Assert $page): Assert => $page
            ->where('choices.learned', false)
            ->where('preview.count', 1));

        customVisitPage($this, ['learned' => '1'])
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('choices.learned', true)
                ->where('preview.count', 2));
    });

    it('lets Learned work join a sitting without pushing it to the front', function (): void {
        Carbon::setTestNow('2026-11-05 09:00:00');
        $session = customSession();

        customRow($session, 'hard-due');
        customRateOn('hard-due', ReviewRating::Hard, '2026-09-20');

        customRow($session, 'plain-due');
        customRateOn('plain-due', ReviewRating::Okay, '2026-09-20');

        customRow($session, 'resting-learned');
        customRateOn('resting-learned', ReviewRating::Easy, '2026-11-04');
        app(SaveItemMark::class)->handle(FeedbackType::Vocabulary, 'resting-learned', false, CollectionItemStatus::Learned);

        customStart($this, ['collections' => 'words', 'learned' => '1', 'size' => 5])
            ->assertSessionHasNoErrors();

        /** @var Review $review */
        $review = Review::query()->latest('id')->firstOrFail();

        expect(customItemIdentities($review))->toBe([
            'vocabulary:hard-due',
            'vocabulary:plain-due',
            'vocabulary:resting-learned',
        ]);
    });

    it('keeps the choices in the address and saves nothing', function (): void {
        $alpha = customSession('Alpha topics');
        customRow($alpha, 'meeting');
        customRow(customSession('Zebra topics'), 'serendipity');

        $address = ['topics' => '1', 'collections' => 'words', 'starred' => '1', 'learned' => '1', 'size' => '15'];

        customVisitPage($this, $address)->assertInertia(fn (Assert $page): Assert => $page
            ->where('choices.topics', [1])
            ->where('choices.collections', ['words'])
            ->where('choices.starred', true)
            ->where('choices.learned', true)
            ->where('choices.size', 15));

        // A page opened again with no address keeps the app's own defaults: the
        // choices were never stored anywhere.
        customVisitPage($this)->assertInertia(fn (Assert $page): Assert => $page
            ->where('choices', [
                'topics' => [],
                'collections' => [],
                'starred' => false,
                'learned' => false,
                'size' => ReviewSettings::SIZE,
            ]));

        // Review Home still offers the day's own suggestion, untouched.
        $this->get(route('review.index'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('todayReview.count', 2));
    });
});

describe('a custom sitting', function (): void {
    it('is marked custom, never as the day’s review', function (): void {
        $session = customSession();
        customRow($session, 'serendipity');
        customRow($session, 'cascade');

        customStart($this)->assertSessionHasNoErrors();

        /** @var Review $review */
        $review = Review::query()->latest('id')->firstOrFail();
        expect($review->is_custom_review)->toBeTrue()
            ->and($review->is_daily_review)->toBeFalse();

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page->where('isDailyReview', false));

        // Finishing every item ends the sitting without claiming the day.
        foreach ($review->items()->get() as $item) {
            $this->post(route('review.rate', ['review' => $review->id]), [
                'kind' => $item->kind->value,
                'item_identity' => $item->item_identity,
                'rating' => ReviewRating::Easy->value,
            ])->assertRedirect();
        }

        $this->get(route('review.session', ['review' => $review->id]))->assertOk();

        expect($review->refresh()->completed_on)->toBeNull()
            ->and($review->refresh()->finished_at)->not->toBeNull();

        $this->get(route('review.index'))->assertInertia(fn (Assert $page): Assert => $page
            ->where('todayReview.state', fn (string $state): bool => $state !== 'done'));
    });

    it('shows no stepper when the choices name one kind', function (): void {
        $session = customSession();
        customRow($session, 'serendipity');
        customRow($session, 'cascade');

        customStart($this, ['collections' => 'words'])->assertSessionHasNoErrors();

        /** @var Review $review */
        $review = Review::query()->latest('id')->firstOrFail();

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('sections', 1)
                ->where('sections.0.key', 'flashcard'));
    });

    it('rates, takes a rating back and finishes early by the same rules as any other sitting', function (): void {
        $session = customSession();
        customRow($session, 'serendipity');
        customRow($session, 'cascade');

        customStart($this)->assertSessionHasNoErrors();

        /** @var Review $review */
        $review = Review::query()->latest('id')->firstOrFail();

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => 'vocabulary',
            'item_identity' => 'serendipity',
            'rating' => ReviewRating::Hard->value,
        ])->assertRedirect();

        expect($review->ratings()->count())->toBe(1);

        $this->delete(route('review.undo', ['review' => $review->id]), [
            'kind' => 'vocabulary',
            'item_identity' => 'serendipity',
        ])->assertRedirect();

        expect($review->ratings()->count())->toBe(0)
            ->and($review->refresh()->finished_at)->toBeNull();

        $this->post(route('review.finishEarly', ['review' => $review->id]))->assertRedirect(route('review.index'));

        expect($review->refresh()->finished_at)->not->toBeNull()
            ->and($review->refresh()->completed_on)->toBeNull();
    });
});

describe('the custom review at scale', function (): void {
    it('counts in a bounded number of queries whatever the size of the pool', function (): void {
        $small = customSession('Small topic');
        foreach (range(1, 20) as $number) {
            customRow($small, "small-{$number}");
        }

        DB::enableQueryLog();
        customVisitPage($this)->assertOk();
        $smallQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $large = customSession('Large topic');
        foreach (range(1, 200) as $number) {
            customRow($large, "large-{$number}");
        }

        DB::enableQueryLog();
        customVisitPage($this)->assertOk();
        $largeQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // The number of queries does not grow with the number of items.
        expect($largeQueries)->toBe($smallQueries)
            ->and($smallQueries)->toBeLessThanOrEqual(25);
    });
});
