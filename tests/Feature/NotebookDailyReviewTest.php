<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewSchedule;
use App\Models\Topic;
use App\Queries\TodaysReview;
use App\Support\NotebookIdentity;
use App\Support\ReviewSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * One saved row of a notebook box, in the shape the board saves it.
 *
 * @param  array<string, mixed>  $attributes
 */
function nbDailyRow(
    Notebook $notebook,
    FeedbackType $type,
    string $subject,
    string $meaning = '',
    string $example = '',
    array $attributes = [],
): PracticeFeedbackEntry {
    $next = (int) $notebook->entries()->where('type', $type->value)->max('position') + 1;

    return PracticeFeedbackEntry::factory()
        ->for($notebook)
        ->create(array_merge([
            'practice_question_id' => null,
            'type' => $type,
            'position' => $next,
            'client_id' => (string) Str::uuid(),
            'content_html' => '',
            'content_text' => $subject,
            'correction_html' => '',
            'correction_text' => $meaning,
            'reason' => '',
            'example' => $example,
            'example_html' => [],
            'is_split' => true,
        ], $attributes));
}

/** A notebook word carrying a meaning, the simplest reviewable item. */
function nbDailyWord(Notebook $notebook, string $word, string $meaning = 'a meaning'): PracticeFeedbackEntry
{
    return nbDailyRow($notebook, FeedbackType::Vocabulary, $word, $meaning);
}

/** A grammar note with a bold part, so it can make a blank. */
function nbDailyNote(Notebook $notebook, string $html, string $text): PracticeFeedbackEntry
{
    return nbDailyRow($notebook, FeedbackType::GrammarNote, $text, '', '', [
        'content_html' => $html,
    ]);
}

/** The scoped identity a notebook word carries. */
function nbDailyKey(Notebook $notebook, string $word): string
{
    return NotebookIdentity::wordKey((int) $notebook->id, $word);
}

/** One word of a finished session, so another scope can hold the same spelling. */
function nbDailySessionWord(string $word, string $meaning): PracticeFeedbackEntry
{
    $session = PracticeSession::factory()->finished()->for(Topic::factory())->create();
    $question = PracticeQuestion::factory()->for($session)->create(['position' => 1]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::Vocabulary->value,
        'content_text' => $word,
        'correction_text' => $meaning,
        'correction_html' => '<p>'.e($meaning).'</p>',
        'position' => 0,
        'is_split' => true,
    ]);
}

/** A notebook under its own named topic. */
function nbDailyNotebook(string $name, string $topic): Notebook
{
    return Notebook::factory()->create([
        'name' => $name,
        'topic_id' => Topic::factory()->create(['name' => $topic])->id,
    ]);
}

/** Today's suggestion, read the way the card reads it. */
function nbDailySuggest(): mixed
{
    return app(TodaysReview::class)->suggest(
        ReviewSettings::SIZE,
        false,
        Request::create('/review'),
    );
}

/** Start the day's review through the route the card uses. */
function nbDailyStart(object $test): Review
{
    $test->post(route('review.store'), ['daily' => true])->assertRedirect();

    /** @var Review $review */
    $review = Review::query()->latest('id')->firstOrFail();

    return $review;
}

/** Rate one reviewed item through the route the learner uses. */
function nbDailyRate(object $test, Review $review, string $identity, ReviewRating $rating): void
{
    $test->post(route('review.rate', ['review' => $review->id]), [
        'kind' => FeedbackType::Vocabulary->value,
        'item_identity' => $identity,
        'rating' => $rating->value,
    ])->assertRedirect();
}

describe('daily practice from notebooks alone', function (): void {
    it('offers a review to a learner who has saved only notebook words', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $notebook = nbDailyNotebook('Kitchen words', 'Kitchen');
        $row = nbDailyWord($notebook, 'spatula', 'a flat tool for lifting food');

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Home')
                ->where('todayReview.state', 'suggest')
                ->where('todayReview.count', 1)
                ->where('todayReview.mix', '1 flashcard'));

        $review = nbDailyStart($this);
        $item = $review->items()->sole();

        expect($review->is_daily_review)->toBeTrue()
            ->and($item->kind)->toBe(FeedbackType::Vocabulary)
            ->and($item->item_identity)->toBe(nbDailyKey($notebook, 'spatula'));

        // The card shows the notebook's own words, names its notebook and
        // shared topic, and opens the saved row rather than any session.
        $this->get(route('review.session', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('review/Session')
                ->where('current.card.front', 'spatula')
                ->where('current.card.meaning', 'a flat tool for lifting food')
                ->where('current.card.source_kind', 'notebook')
                ->where('current.card.source_label', 'Kitchen words · Kitchen')
                ->where('current.card.session_url', fn (string $url): bool => str_contains($url, '/notebooks/'.$notebook->id)
                    && str_contains($url, 'entry='.$row->client_id)));

        nbDailyRate($this, $review, $item->item_identity, ReviewRating::Easy);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page->where('finished', true));

        // The sitting counts as review activity, exactly as a session-sourced
        // one does.
        $this->get(route('review.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('recentReviews.0.type', 'daily')
                ->where('recentReviews.0.rated', 1)
                ->where('recentReviews.0.counts', ['hard' => 0, 'okay' => 0, 'easy' => 1]));
    });

    it('turns a notebook grammar note into a blank that names its source topic', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $notebook = nbDailyNotebook('Tense notes', 'Grammar');
        $note = nbDailyNote(
            $notebook,
            '<p>Use the <strong>past tense</strong> here.</p>',
            'Use the past tense here.',
        );

        expect(nbDailySuggest()->perKind())->toBe(['fill_in_the_blank' => 1]);

        $review = nbDailyStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.card.exercise', 'fill_in_the_blank')
                ->where('current.card.kind', FeedbackType::GrammarNote->value)
                ->where('current.card.prompt_html', fn (string $html): bool => str_contains($html, 'data-blank'))
                ->where('current.card.hint', 'Grammar')
                ->where('current.card.hint_label', 'Topic')
                ->where('current.card.source_kind', 'notebook')
                ->where('current.card.source_label', 'Tense notes · Grammar')
                ->where('current.card.session_url', fn (string $url): bool => str_contains($url, 'entry='.$note->client_id)));
    });

    it('keeps the friendly starting state until something is saved', function (): void {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'empty'));

        $this->get(route('review.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'empty'));
    });

    it('says nothing needs review when the notebook items cannot be practiced yet', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $notebook = nbDailyNotebook('Rough notes', 'Kitchen');

        // A word without a meaning and a note with nothing bolded or quoted:
        // both are saved, neither can make an exercise.
        nbDailyRow($notebook, FeedbackType::Vocabulary, 'bank', '');
        nbDailyNote($notebook, '<p>Use the past tense here.</p>', 'Use the past tense here.');

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'nothing'));

        // The guidance counts both sources by the same shared rules.
        $this->get(route('review.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'nothing')
                ->where('waitingForFix.vocabulary', 1)
                ->where('waitingForFix.mistakes', 0)
                ->where('grammarNotesTip.count', 1));
    });

    it('starts and offers a notebook item again once it is due', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $notebook = nbDailyNotebook('Kitchen words', 'Kitchen');
        nbDailyWord($notebook, 'spatula', 'a flat tool for lifting food');

        $review = nbDailyStart($this);
        nbDailyRate($this, $review, nbDailyKey($notebook, 'spatula'), ReviewRating::Hard);

        // Hard comes back tomorrow, so today's fresh card has nothing due.
        expect(nbDailySuggest()->count())->toBe(0);

        Carbon::setTestNow('2026-11-04 10:00:00');

        expect(nbDailySuggest()->count())->toBe(1);
    });
});

describe('scoped items stay independent', function (): void {
    it('rates a notebook word without touching a same-spelling session word', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $notebook = nbDailyNotebook('Kitchen words', 'Kitchen');
        nbDailyWord($notebook, 'bank', 'river side');
        nbDailySessionWord('bank', 'money in a bank');

        expect(nbDailySuggest()->count())->toBe(2);

        $review = nbDailyStart($this);
        nbDailyRate($this, $review, nbDailyKey($notebook, 'bank'), ReviewRating::Hard);

        $notebookSchedule = ReviewSchedule::query()
            ->where('kind', FeedbackType::Vocabulary->value)
            ->where('item_identity', nbDailyKey($notebook, 'bank'))
            ->sole();

        expect($notebookSchedule->last_rating)->toBe(ReviewRating::Hard)
            ->and(ReviewSchedule::query()->where('item_identity', 'bank')->exists())->toBeFalse()
            ->and(Rating::query()->where('item_identity', 'bank')->exists())->toBeFalse();

        // Only the notebook word now rests; the session word is still waiting
        // for its first review.
        expect(collect(nbDailySuggest()->items)->pluck('identity')->all())->toBe(['bank']);
    });
});

describe('live notebook content during a review', function (): void {
    it('shows the notebook item as it is now, in its own scope', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $notebook = nbDailyNotebook('Kitchen words', 'Kitchen');
        $row = nbDailyWord($notebook, 'bank', 'river side');

        $review = nbDailyStart($this);

        $row->update(['correction_text' => 'edge of a river']);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.card.front', 'bank')
                ->where('current.card.meaning', 'edge of a river')
                ->where('current.card.source_kind', 'notebook'));
    });

    it('skips a notebook item deleted mid-review and never substitutes another scope', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $notebook = nbDailyNotebook('Kitchen words', 'Kitchen');
        $row = nbDailyWord($notebook, 'bank', 'river side');
        nbDailySessionWord('bank', 'money in a bank');

        $review = nbDailyStart($this);
        expect($review->items)->toHaveCount(2);

        $row->delete();

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('position.total', 1)
                ->where('sections.0.total', 1)
                ->where('current.card.source_kind', 'session')
                ->where('current.card.meaning', 'money in a bank')
                ->where('current.card.session_url', fn (string $url): bool => str_contains($url, '/sessions/')));

        expect(ReviewSchedule::query()->count())->toBe(0);
    });
});

describe('quick review from notebook topics', function (): void {
    it('offers notebook topics with their eligible counts and starts from them', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $kitchen = nbDailyNotebook('Kitchen words', 'Kitchen');
        $travel = nbDailyNotebook('Travel words', 'Travel');
        nbDailyWord($kitchen, 'spatula', 'a flat tool');
        nbDailyWord($travel, 'sail', 'to move by wind');

        $page = $this->get(route('review.custom'))->assertOk();
        $page->assertInertia(fn (Assert $page): Assert => $page
            ->component('review/Custom')
            ->where('preview.count', 2));

        $topics = collect($page->viewData('page')['props']['topics']);

        expect($topics->pluck('name')->all())->toBe(['Kitchen', 'Travel'])
            ->and($topics->pluck('count')->all())->toBe([1, 1]);

        // Choosing one topic narrows the count and the offered topics alike.
        $chosen = $this->get(route('review.custom', ['topics' => $kitchen->topic_id]))->assertOk();
        $chosen->assertInertia(fn (Assert $page): Assert => $page->where('preview.count', 1));

        $narrowed = collect($chosen->viewData('page')['props']['topics']);

        expect($narrowed->pluck('name')->all())->toBe(['Kitchen']);

        $this->post(route('review.customStore'), ['topics' => [$kitchen->topic_id]])
            ->assertRedirect();

        $review = Review::query()->sole();

        expect($review->is_custom_review)->toBeTrue()
            ->and($review->is_daily_review)->toBeFalse()
            ->and($review->items()->sole()->item_identity)->toBe(nbDailyKey($kitchen, 'spatula'));
    });
});

describe('the progress boundary', function (): void {
    it('adds no session, session total or streak when a notebook is created and edited', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');

        $this->post(route('notebooks.store'), [
            'name' => 'Book notes',
            'topic' => 'Reading',
        ])->assertRedirect();

        $notebook = Notebook::query()->sole();

        $this->putJson(route('notebooks.autosave.update', $notebook), [
            'name' => 'Book notes',
            'topic' => 'Reading',
            'description' => '',
            'version' => $notebook->version,
            'save_key' => (string) Str::uuid(),
            'feedback' => [
                'vocabulary' => [[
                    'id' => 'w1',
                    'text' => 'bank',
                    'meaning' => 'river side',
                    'example_html' => '',
                ]],
                'phrase' => [],
                'grammar_note' => [],
            ],
        ])->assertOk();

        // Saving a notebook is not a finished session and proves nothing about
        // practice: the session totals and the Home session list stay empty.
        expect(PracticeSession::query()->count())->toBe(0);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('recentSessions', [])
                ->where('unfinishedSession', null)
                ->where('todayReview.state', 'suggest'));
    });

    it('lists notebook practice as review activity but never a saved custom attempt', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $notebook = nbDailyNotebook('Kitchen words', 'Kitchen');
        nbDailyWord($notebook, 'spatula', 'a flat tool');
        nbDailyWord($notebook, 'whisk', 'a tool for beating eggs');

        // A daily sitting sourced only from the notebook.
        $review = nbDailyStart($this);
        nbDailyRate($this, $review, nbDailyKey($notebook, 'spatula'), ReviewRating::Okay);
        nbDailyRate($this, $review, nbDailyKey($notebook, 'whisk'), ReviewRating::Easy);

        $this->get(route('review.session', ['review' => $review->id]))->assertOk();

        // A saved custom review and its attempt are a separate kind of
        // practice and never appear as review activity.
        $this->postJson(route('notebooks.review.store', $notebook), [
            'creation_key' => 'progress-check',
        ])->assertOk()->assertJsonPath('outcome', 'created');

        $reviewActivity = $this->get(route('review.index'))->assertOk();

        $reviewActivity->assertInertia(fn (Assert $page): Assert => $page
            ->has('recentReviews', 1)
            ->where('recentReviews.0.type', 'daily')
            ->where('recentReviews.0.rated', 2)
            ->where('recentReviews.0.counts', ['hard' => 0, 'okay' => 1, 'easy' => 1]));
    });
});
