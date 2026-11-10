<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * One saved row on a notebook's board.
 */
function notebookRow(Notebook $notebook, FeedbackType $type, string $text): PracticeFeedbackEntry
{
    return PracticeFeedbackEntry::factory()->for($notebook)->create([
        'practice_question_id' => null,
        'type' => $type,
        'content_text' => $text,
        'correction_text' => $type->isTable() ? 'the meaning of '.$text : '',
        'client_id' => (string) Str::uuid(),
    ]);
}

describe('entry parent integrity', function (): void {
    it('rejects an entry with no parent', function (): void {
        expect(fn () => PracticeFeedbackEntry::factory()->create([
            'practice_question_id' => null,
            'notebook_id' => null,
        ]))->toThrow(LogicException::class);

        expect(PracticeFeedbackEntry::count())->toBe(0);
    });

    it('rejects an entry with both a question and a notebook', function (): void {
        $question = PracticeQuestion::factory()->create();
        $notebook = Notebook::factory()->create();

        expect(fn () => PracticeFeedbackEntry::factory()->create([
            'practice_question_id' => $question->id,
            'notebook_id' => $notebook->id,
        ]))->toThrow(LogicException::class);

        expect(PracticeFeedbackEntry::count())->toBe(0);
    });

    it('rejects an entry whose notebook does not exist', function (): void {
        expect(fn () => PracticeFeedbackEntry::factory()->create([
            'practice_question_id' => null,
            'notebook_id' => 999999,
            'type' => FeedbackType::Vocabulary,
            'content_text' => 'bank',
            'correction_text' => 'a financial institution',
        ]))->toThrow(QueryException::class);

        expect(PracticeFeedbackEntry::count())->toBe(0);
    });

    it('rejects an entry whose question does not exist', function (): void {
        expect(fn () => PracticeFeedbackEntry::factory()->create([
            'practice_question_id' => 999999,
            'notebook_id' => null,
        ]))->toThrow(QueryException::class);

        expect(PracticeFeedbackEntry::count())->toBe(0);
    });

    it('refuses notebook rows of kinds a notebook cannot hold', function (FeedbackType $type): void {
        $notebook = Notebook::factory()->create();

        expect(fn (): PracticeFeedbackEntry => notebookRow($notebook, $type, 'some text'))->toThrow(LogicException::class);

        expect(PracticeFeedbackEntry::count())->toBe(0);
    })->with([
        'mistakes' => FeedbackType::Mistake,
        'natural versions' => FeedbackType::NaturalVersion,
    ]);

    it('stores the three kinds a notebook holds', function (FeedbackType $type): void {
        $notebook = Notebook::factory()->create();

        $entry = notebookRow($notebook, $type, 'bank');

        expect($entry->exists)->toBeTrue()
            ->and($entry->notebook_id)->toBe($notebook->id)
            ->and($entry->practice_question_id)->toBeNull();
    })->with([
        'vocabulary' => FeedbackType::Vocabulary,
        'phrases' => FeedbackType::Phrase,
        'grammar notes' => FeedbackType::GrammarNote,
    ]);
});

describe('scoped study identities', function (): void {
    it('keeps one word in two notebooks and a session as three independent identities', function (): void {
        $question = PracticeQuestion::factory()->create();
        $sessionWord = PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Vocabulary,
            'content_text' => 'Bank',
            'correction_text' => 'a financial institution',
        ]);

        $first = notebookRow(Notebook::factory()->create(), FeedbackType::Vocabulary, 'bank');
        $second = notebookRow(Notebook::factory()->create(), FeedbackType::Vocabulary, 'bank');

        expect($sessionWord->collection_key)->toBe('bank')
            ->and($first->collection_key)->not->toBe('bank')
            ->and($second->collection_key)->not->toBe('bank')
            ->and($first->collection_key)->not->toBe($second->collection_key);
    });

    it('keeps a notebook word and phrase with the same text as distinct items', function (): void {
        $notebook = Notebook::factory()->create();

        $word = notebookRow($notebook, FeedbackType::Vocabulary, 'bank');
        $phrase = notebookRow($notebook, FeedbackType::Phrase, 'bank');

        expect([$word->type->value, $word->collection_key])
            ->not->toBe([$phrase->type->value, $phrase->collection_key]);
    });

    it('keeps comma-separated meanings and repeated rows of one word as one identity', function (): void {
        $notebook = Notebook::factory()->create();

        $first = notebookRow($notebook, FeedbackType::Vocabulary, 'bank, credit union');
        $second = notebookRow($notebook, FeedbackType::Vocabulary, 'Bank,  credit union');
        $plain = notebookRow($notebook, FeedbackType::Vocabulary, 'bank');

        expect($first->collection_key)->toBe($second->collection_key)
            ->and($first->collection_key)->not->toBe($plain->collection_key);
    });

    it('keeps identical grammar notes as separate identities', function (): void {
        $notebook = Notebook::factory()->create();

        $first = notebookRow($notebook, FeedbackType::GrammarNote, 'Use the past tense.');
        $second = notebookRow($notebook, FeedbackType::GrammarNote, 'Use the past tense.');

        expect($first->collection_key)->toBe('note-'.$first->id)
            ->and($second->collection_key)->toBe('note-'.$second->id)
            ->and($first->collection_key)->not->toBe($second->collection_key);
    });

    it('leaves session identities under the existing normalisation rule', function (): void {
        $question = PracticeQuestion::factory()->create();

        $entry = PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Vocabulary,
            'content_text' => '  Café   Bank ',
            'correction_text' => 'meaning',
        ]);

        expect($entry->collection_key)->toBe('cafe bank');
    });

    it('gives a blank notebook row no identity', function (): void {
        $entry = notebookRow(Notebook::factory()->create(), FeedbackType::Vocabulary, '   ');

        expect($entry->collection_key)->toBeNull();
    });
});

describe('deletion boundaries', function (): void {
    it('deletes only the notebook\'s own rows when the notebook goes', function (): void {
        $topic = Topic::factory()->create();
        $notebook = Notebook::factory()->for($topic)->create();
        notebookRow($notebook, FeedbackType::Vocabulary, 'bank');
        notebookRow($notebook, FeedbackType::GrammarNote, 'A note.');

        $session = PracticeSession::factory()->for($topic)->create();
        $question = PracticeQuestion::factory()->for($session)->create();
        $sessionEntry = PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Vocabulary,
            'content_text' => 'bank',
            'correction_text' => 'meaning',
        ]);

        $notebook->delete();

        expect(PracticeFeedbackEntry::where('notebook_id', $notebook->id)->count())->toBe(0)
            ->and(PracticeFeedbackEntry::find($sessionEntry->id))->not->toBeNull()
            ->and(Topic::find($topic->id))->not->toBeNull()
            ->and(PracticeSession::find($session->id))->not->toBeNull();
    });

    it('still cascades only its own rows when a question is deleted', function (): void {
        $session = PracticeSession::factory()->create();
        $first = PracticeQuestion::factory()->for($session)->create(['position' => 1]);
        $second = PracticeQuestion::factory()->for($session)->create(['position' => 2]);

        $gone = PracticeFeedbackEntry::factory()->for($first)->create([
            'type' => FeedbackType::Vocabulary,
            'content_text' => 'bank',
            'correction_text' => 'meaning',
        ]);
        $kept = PracticeFeedbackEntry::factory()->for($second)->create([
            'type' => FeedbackType::Vocabulary,
            'content_text' => 'bank',
            'correction_text' => 'meaning',
        ]);

        $first->delete();

        expect(PracticeFeedbackEntry::find($gone->id))->toBeNull()
            ->and(PracticeFeedbackEntry::find($kept->id))->not->toBeNull();
    });
});
