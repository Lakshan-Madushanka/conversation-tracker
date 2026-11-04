<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\ReviewSchedule;
use App\Models\Topic;
use App\Support\ReviewSettings;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
    DB::disableQueryLog();
});

/**
 * A finished session the learner can study from, with its own named topic.
 */
function blankSession(string $topic = 'Past tenses'): PracticeSession
{
    $at = CarbonImmutable::now();

    return PracticeSession::factory()
        ->finished()
        ->for(Topic::factory()->create(['name' => $topic]))
        ->create([
            'created_at' => $at,
            'finished_at' => $at,
        ]);
}

/**
 * One table row saved the way the editor saves a word, a phrase or a mistake.
 */
function blankRow(
    PracticeSession $session,
    string $content,
    string $correction,
    FeedbackType $type = FeedbackType::Vocabulary,
    string $reason = '',
    ?string $example = null,
): PracticeFeedbackEntry {
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => $type,
        'content_text' => $content,
        'content_html' => $content,
        'correction_text' => $correction,
        'correction_html' => $correction,
        'example' => $example ?? '',
        'example_html' => [],
        'reason' => $reason,
        'position' => 0,
        'is_split' => true,
        'client_id' => (string) Str::uuid(),
    ])->refresh();
}

/**
 * A grammar note, kept as the learner saved it.
 */
function blankNote(
    PracticeSession $session,
    string $html,
    string $text,
): PracticeFeedbackEntry {
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::GrammarNote,
        'content_html' => $html,
        'content_text' => $text,
        'position' => 0,
        'is_split' => true,
        'client_id' => (string) Str::uuid(),
    ])->refresh();
}

/** A past question with its natural version. */
function blankQuestion(PracticeSession $session): PracticeQuestion
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

    return $question;
}

/** Start a review through the screen's own button. */
function blankStart(object $test, bool $daily = true): Review
{
    $test->post(route('review.store'), ['daily' => $daily])->assertRedirect();

    /** @var Review $review */
    $review = Review::query()->latest('id')->firstOrFail();

    $review->load('items');

    return $review;
}

/** The stored item with this kind and the chosen exercise. */
function blankItem(Review $review, FeedbackType $kind, string $exercise = 'fill_in_the_blank'): ReviewItem
{
    /** @var ReviewItem|null $item */
    $item = $review->items->first(
        fn (ReviewItem $candidate): bool => $candidate->kind === $kind
            && $candidate->exercise === $exercise,
    );

    return $item ?? throw new RuntimeException(
        "No {$kind->value} item with exercise {$exercise} in this review."
    );
}

describe('the fill-in-the-blank card', function (): void {
    it('turns a vocabulary example into a blank card', function (): void {
        $session = blankSession();
        $entries = [];

        foreach (range(1, 12) as $i) {
            $entries[] = blankRow(
                $session,
                "word-{$i}",
                'a meaning',
                example: "A lovely word-{$i} moment.",
            );
        }

        blankNote($session, '<p>Use the <strong>past tense</strong> here.</p>', 'Use the past tense here.');

        $review = blankStart($this);
        $item = blankItem($review, FeedbackType::Vocabulary);

        $response = $this->get(route('review.session', [
            'review' => $review->id,
            'step' => 'fill_in_the_blank',
            'position' => $item->position,
        ]));

        $response->assertOk();

        $page = $response->viewData('page');
        $card = $page['props']['current']['card'];
        $entry = collect($entries)->first(
            fn (PracticeFeedbackEntry $candidate): bool => (string) $candidate->collection_key === $card['item_identity'],
        );

        expect($page['props']['current']['step'])->toBe('fill_in_the_blank')
            ->and($page['props']['current']['label'])->toBe('Fill in the blank')
            ->and($page['props']['sections'][1]['icon'])->toBe('text-cursor-input')
            ->and($entry)->not->toBeNull()
            ->and($card['kind'])->toBe('vocabulary')
            ->and($card['exercise'])->toBe('fill_in_the_blank')
            ->and($card['label'])->toBe('Fill in the blank')
            ->and($card['prompt_html'])->toContain('data-blank=""')
            ->and($card['prompt_html'])->not->toContain($entry->content_text)
            ->and($card['answer_html'])->toContain('<mark>'.$entry->content_text.'</mark>')
            ->and($card['answer_text'])->toContain('A lovely')
            ->and($card['session_url'])->toBe(route('sessions.show', [
                'session' => $session->id,
                'from' => 'collections',
                'question' => (string) $entry->practiceQuestion?->position,
                'field' => 'entry',
                'type' => 'vocabulary',
                'position' => (string) $entry->position,
                'entry' => (string) $entry->client_id,
            ]));
    });

    it('turns a phrase into a blank card', function (): void {
        $session = blankSession();

        foreach (range(1, 12) as $i) {
            blankRow(
                $session,
                "look after {$i}",
                'take care of',
                FeedbackType::Phrase,
                example: "Could you look after {$i} for me?",
            );
        }

        blankNote($session, '<p>Use the <strong>present simple</strong>.</p>', 'Use the present simple.');

        $review = blankStart($this);
        $item = blankItem($review, FeedbackType::Phrase);

        $card = $this->get(route('review.session', [
            'review' => $review->id,
            'step' => 'fill_in_the_blank',
            'position' => $item->position,
        ]))->viewData('page')['props']['current']['card'];

        expect($card['kind'])->toBe('phrase')
            ->and($card['prompt_html'])->toContain('data-blank=""')
            ->and($card['answer_html'])->toContain('<mark>')
            ->and($card['session_url'])->toContain('type=phrase');
    });

    it('turns a mistake correction into a blank card with the reason as hint', function (): void {
        $session = blankSession();
        $entries = [];

        foreach (range(1, 12) as $i) {
            $entries[] = blankRow(
                $session,
                "I has went to the shop {$i}",
                "I have gone to the shop {$i}",
                FeedbackType::Mistake,
                'Use the past participle.',
            );
        }

        blankNote($session, '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $review = blankStart($this);
        $item = blankItem($review, FeedbackType::Mistake);

        $this->get(route('review.session', [
            'review' => $review->id,
            'step' => 'fill_in_the_blank',
            'position' => $item->position,
        ]))->assertInertia(fn (Assert $page): Assert => $page
            ->where('current.card.kind', 'mistake')
            ->where('current.card.hint_label', 'Why')
            ->where('current.card.hint', 'Use the past participle.')
            ->where('current.card.prompt_html', fn (string $html): bool => str_contains($html, 'data-blank=""'))
            ->where('current.card.answer_html', fn (string $html): bool => str_contains($html, '<mark>')));
    });

    it('turns a grammar note with a bold part into a blank card', function (): void {
        $session = blankSession('Past tenses');
        $note = blankNote(
            $session,
            '<ul><li>Use <strong>have gone</strong> after have.</li><li>Do not use went.</li></ul>',
            'Use have gone after have. Do not use went.',
        );

        $review = blankStart($this);

        $this->get(route('review.session', [
            'review' => $review->id,
            'step' => 'fill_in_the_blank',
        ]))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('current.step', 'fill_in_the_blank')
            ->where('current.card.kind', 'grammar_note')
            ->where('current.card.exercise', 'fill_in_the_blank')
            ->where('current.card.item_identity', 'note-'.$note->id)
            ->where('current.card.hint_label', 'Topic')
            ->where('current.card.hint', 'Past tenses'));
    });

    it('uses a quoted example when a grammar note has no bold part', function (): void {
        $session = blankSession();
        blankNote(
            $session,
            '<p>We say “I have gone”, not “I has went”.</p>',
            'We say “I have gone”, not “I has went”.',
        );

        $review = blankStart($this);

        $card = $this->get(route('review.session', [
            'review' => $review->id,
            'step' => 'fill_in_the_blank',
        ]))->viewData('page')['props']['current']['card'];

        expect($card['prompt_html'])->toContain('data-blank=""')
            ->and($card['prompt_html'])->not->toContain('I have gone')
            ->and($card['answer_html'])->toContain('<mark>');
    });
});

describe('which grammar notes can make a blank', function (): void {
    it('skips notes with nothing to blank and keeps the rest of the review working', function (): void {
        $session = blankSession();
        blankRow($session, 'serendipity', 'finding something good by chance');

        // No bold and no quote.
        blankNote($session, '<p>Remember this rule.</p>', 'Remember this rule.');
        // Every word is bold.
        blankNote($session, '<p><strong>Everything is bold here</strong></p>', 'Everything is bold here');
        // The only bold part is longer than four words.
        blankNote(
            $session,
            '<p><strong>one two three four five</strong> is too long.</p>',
            'one two three four five is too long.',
        );

        $review = blankStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('sections', 1)
                ->where('sections.0.key', 'flashcard')
                ->where('finished', false));
    });

    it('shows no fill-in-the-blank step when no suitable text exists', function (): void {
        $session = blankSession();
        blankRow($session, 'serendipity', 'finding something good by chance');

        $review = blankStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('sections', 1)
                ->where('sections.0.key', 'flashcard'));
    });
});

describe('the fill-in-the-blank step', function (): void {
    it('opens by its name or number at any time, before the earlier steps are rated', function (): void {
        $session = blankSession();
        blankRow($session, 'serendipity', 'finding something good by chance', example: 'A lovely serendipity moment.');
        blankRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);
        blankQuestion($session);
        $note = blankNote($session, '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $review = blankStart($this);

        $this->get(route('review.session', ['review' => $review->id, 'step' => 'fill_in_the_blank']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.step', 'fill_in_the_blank')
                ->where('current.card.item_identity', 'note-'.$note->id));

        $this->get(route('review.session', ['review' => $review->id, 'step' => 4]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('current.step', 'fill_in_the_blank'));
    });

    it('keeps the fixed order and puts the blank step fourth', function (): void {
        $session = blankSession();
        blankRow($session, 'serendipity', 'finding something good by chance', example: 'A lovely serendipity moment.');
        blankRow($session, 'I has went', 'I have gone', FeedbackType::Mistake);
        blankQuestion($session);
        blankNote($session, '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $review = blankStart($this);

        $this->get(route('review.session', ['review' => $review->id]))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('sections', 4)
                ->where('sections.0.key', 'flashcard')
                ->where('sections.1.key', 'mistake')
                ->where('sections.2.key', 'say_it_again')
                ->where('sections.3.key', 'fill_in_the_blank')
                ->where('sections.3.label', 'Fill in the blank')
                ->where('sections.3.icon', 'text-cursor-input')
                ->where('sections.3.number', 4)
                ->where('sections.3.total', 1));
    });

    it('never shows the same item twice in one review', function (): void {
        $session = blankSession();

        foreach (range(1, 12) as $i) {
            blankRow($session, "word-{$i}", 'a meaning', example: "A lovely word-{$i} moment.");
        }

        blankNote($session, '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $review = blankStart($this);

        $keys = $review->items
            ->map(fn (ReviewItem $item): string => $item->kind->value.'|'.$item->item_identity)
            ->all();

        $flashcards = $review->items
            ->where('exercise', 'flashcard')
            ->pluck('item_identity')
            ->all();

        $blanks = $review->items
            ->where('exercise', 'fill_in_the_blank')
            ->pluck('item_identity')
            ->all();

        expect($keys)->toHaveCount(count(array_unique($keys)))
            ->and($blanks)->not->toBeEmpty()
            ->and(array_intersect($flashcards, $blanks))->toBe([]);
    });

    it('lets the natural exercises take their shares before the blanks take leftovers', function (): void {
        $session = blankSession();

        foreach (range(1, 20) as $i) {
            blankRow($session, "word-{$i}", 'a meaning', example: "A lovely word-{$i} moment.");
            blankRow($session, "said-{$i}", "told-{$i}", FeedbackType::Mistake);
            blankQuestion($session);
        }

        $review = blankStart($this);

        $flashcards = $review->items->where('exercise', 'flashcard');
        $mistakes = $review->items->where('exercise', 'mistake');
        $sayItAgain = $review->items->where('exercise', 'say_it_again');
        $blanks = $review->items->where('exercise', 'fill_in_the_blank');

        expect($review->items)->toHaveCount(ReviewSettings::SIZE)
            ->and($flashcards)->toHaveCount(4)
            ->and($mistakes)->toHaveCount(2)
            ->and($sayItAgain)->toHaveCount(1)
            ->and($blanks)->toHaveCount(1)
            ->and($blanks->sole()->kind)->toBe(FeedbackType::Vocabulary)
            ->and($flashcards->pluck('item_identity')->all())
            ->not->toContain($blanks->sole()->item_identity);
    });
});

describe('rating a grammar note', function (): void {
    it('keeps its rating and schedule when the note is edited', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $note = blankNote(blankSession(), '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $review = blankStart($this);

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => FeedbackType::GrammarNote->value,
            'item_identity' => 'note-'.$note->id,
            'rating' => ReviewRating::Hard->value,
        ])->assertRedirect();

        $note->update([
            'content_html' => '<p>Use the <strong>present perfect</strong> instead.</p>',
            'content_text' => 'Use the present perfect instead.',
        ]);

        $rating = Rating::query()->sole();
        $schedule = ReviewSchedule::query()->sole();

        expect($rating->kind)->toBe(FeedbackType::GrammarNote)
            ->and($rating->item_identity)->toBe('note-'.$note->id)
            ->and($schedule->kind)->toBe(FeedbackType::GrammarNote)
            ->and($schedule->item_identity)->toBe('note-'.$note->id);
    });

    it('keeps two identical notes separate', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = blankSession();
        $first = blankNote($session, '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');
        $second = blankNote($session, '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $review = blankStart($this);

        expect($review->items)->toHaveCount(2);

        foreach ([$first, $second] as $note) {
            $this->post(route('review.rate', ['review' => $review->id]), [
                'kind' => FeedbackType::GrammarNote->value,
                'item_identity' => 'note-'.$note->id,
                'rating' => ReviewRating::Okay->value,
            ])->assertRedirect();
        }

        expect(Rating::query()->count())->toBe(2)
            ->and(ReviewSchedule::query()->count())->toBe(2)
            ->and(ReviewSchedule::query()->pluck('item_identity')->sort()->values()->all())
            ->toBe(['note-'.$first->id, 'note-'.$second->id]);
    });

    it('keeps the schedule when the session is hidden and returns it afterwards', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = blankSession();
        $note = blankNote($session, '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $review = blankStart($this);

        $this->post(route('review.rate', ['review' => $review->id]), [
            'kind' => FeedbackType::GrammarNote->value,
            'item_identity' => 'note-'.$note->id,
            'rating' => ReviewRating::Hard->value,
        ])->assertRedirect();

        $session->forceFill(['archived_at' => CarbonImmutable::now()])->save();

        expect(ReviewSchedule::query()->where('kind', FeedbackType::GrammarNote->value)->count())->toBe(1);

        Carbon::setTestNow('2026-11-06 10:00:00');
        $session->forceFill(['archived_at' => null])->save();

        $again = blankStart($this);

        expect($again->items->pluck('item_identity'))->toContain('note-'.$note->id);
    });

    it('saves the rating once', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $note = blankNote(blankSession(), '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $review = blankStart($this);

        $payload = [
            'kind' => FeedbackType::GrammarNote->value,
            'item_identity' => 'note-'.$note->id,
            'rating' => ReviewRating::Easy->value,
        ];

        $this->post(route('review.rate', ['review' => $review->id]), $payload)->assertRedirect();
        $this->post(route('review.rate', ['review' => $review->id]), $payload)->assertRedirect();

        expect(Rating::query()->count())->toBe(1)
            ->and(ReviewSchedule::query()->count())->toBe(1);
    });
});

describe('the Review Home grammar-notes tip', function (): void {
    it('counts only the notes that cannot make a blank', function (): void {
        $session = blankSession();
        blankNote($session, '<p>Remember this rule.</p>', 'Remember this rule.');
        blankNote($session, '<p>Remember this rule.</p>', 'Remember this rule.');
        blankNote($session, '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $this->get(route('review.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('grammarNotesTip.count', 2));
    });

    it('is quiet when every note can make a blank', function (): void {
        blankNote(blankSession(), '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $this->get(route('review.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('grammarNotesTip.count', 0));
    });
});

describe('fill-in-the-blank in the daily suggestion', function (): void {
    it('shows the blank count in the mix and the honest estimate', function (): void {
        Carbon::setTestNow('2026-11-03 10:00:00');
        $session = blankSession();

        foreach (range(1, 12) as $i) {
            blankRow($session, "word-{$i}", 'a meaning', example: "A lovely word-{$i} moment.");
        }

        blankNote($session, '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('todayReview.state', 'suggest')
                ->where('todayReview.count', 8)
                ->where('todayReview.mix', '6 flashcards, 2 blanks to fill in')
                // 6 × 35s and 2 × 40s is 290 seconds, rounded up to 5 minutes.
                ->where('todayReview.minutes', 5));
    });
});

describe('the chosen exercise is stored', function (): void {
    it('writes the exercise each item was picked for', function (): void {
        $session = blankSession();

        foreach (range(1, 12) as $i) {
            blankRow($session, "word-{$i}", 'a meaning', example: "A lovely word-{$i} moment.");
        }

        $note = blankNote($session, '<p>Use the <strong>past tense</strong>.</p>', 'Use the past tense.');

        $review = blankStart($this);

        expect($review->items)->not->toBeEmpty()
            ->and($review->items->every(fn (ReviewItem $item): bool => $item->exercise !== null))->toBeTrue();

        $noteItem = blankItem($review, FeedbackType::GrammarNote);
        $flashcard = blankItem($review, FeedbackType::Vocabulary, 'flashcard');
        $blank = blankItem($review, FeedbackType::Vocabulary);

        expect($noteItem->item_identity)->toBe('note-'.$note->id)
            ->and($flashcard->exercise)->toBe('flashcard')
            ->and($blank->exercise)->toBe('fill_in_the_blank');
    });
});
