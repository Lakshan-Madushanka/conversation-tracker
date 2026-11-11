<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use Illuminate\Support\Str;

/**
 * One saved row on a notebook's board, with its meaning and example.
 */
function listingEntry(Notebook $notebook, FeedbackType $type, string $text, string $meaning = '', string $example = ''): PracticeFeedbackEntry
{
    return PracticeFeedbackEntry::factory()->for($notebook)->create([
        'practice_question_id' => null,
        'type' => $type,
        'content_text' => $text,
        'correction_text' => $meaning,
        'example' => $example,
        'example_html' => $example === '' ? '' : [['id' => (string) Str::uuid(), 'text' => $example, 'html' => $example]],
        'client_id' => (string) Str::uuid(),
    ]);
}

describe('the notebook listing', function (): void {
    it('shows each notebook with its topic, counts and last-updated time', function (): void {
        $notebook = Notebook::factory()->create(['name' => 'Book notes']);
        listingEntry($notebook, FeedbackType::Vocabulary, 'bank', 'a financial institution');
        listingEntry($notebook, FeedbackType::Vocabulary, 'river', 'the edge of a river');
        listingEntry($notebook, FeedbackType::GrammarNote, 'Use the past tense.');

        $this->getJson(route('notebooks.index'))
            ->assertOk()
            ->assertJsonCount(1, 'notebooks')
            ->assertJsonPath('notebooks.0.id', $notebook->id)
            ->assertJsonPath('notebooks.0.name', 'Book notes')
            ->assertJsonPath('notebooks.0.topic', $notebook->topic->name)
            ->assertJsonPath('notebooks.0.counts.words', 2)
            ->assertJsonPath('notebooks.0.counts.grammar_notes', 1)
            ->assertJsonStructure([
                'notebooks' => [['id', 'name', 'topic', 'description', 'last_updated_at', 'counts']],
                'next_cursor',
                'has_more',
                'total',
            ]);
    });

    it('finds a notebook by entry meaning, example and note, returning it once', function (): void {
        $notebook = Notebook::factory()->create();
        listingEntry($notebook, FeedbackType::Vocabulary, 'bank', 'a financial institution', 'I went to the bank');
        listingEntry($notebook, FeedbackType::Vocabulary, 'credit', 'a financial institution');
        listingEntry($notebook, FeedbackType::GrammarNote, 'Use the past tense.');

        foreach (['financial institution', 'went to the bank', 'past tense'] as $query) {
            $this->getJson(route('notebooks.index', ['q' => $query]))
                ->assertOk()
                ->assertJsonCount(1, 'notebooks')
                ->assertJsonPath('notebooks.0.id', $notebook->id);
        }
    });

    it('finds notebooks by name, topic and description', function (): void {
        $this->post(route('notebooks.store'), [
            'name' => 'Nature reading',
            'topic' => 'Reading',
            'description' => '<p>Chapters about forests</p>',
        ])->assertRedirect();

        $notebook = Notebook::query()->firstOrFail();

        foreach (['nature', 'reading', 'forests'] as $query) {
            $this->getJson(route('notebooks.index', ['q' => $query]))
                ->assertOk()
                ->assertJsonCount(1, 'notebooks')
                ->assertJsonPath('notebooks.0.id', $notebook->id);
        }
    });

    it('does not match through another notebook or a session', function (): void {
        $notebook = Notebook::factory()->create();
        listingEntry($notebook, FeedbackType::Vocabulary, 'bank', 'a financial institution');

        $other = Notebook::factory()->create();
        listingEntry($other, FeedbackType::Vocabulary, 'bank', 'the edge of a river');

        $question = PracticeQuestion::factory()->create();
        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Vocabulary,
            'content_text' => 'bank',
            'correction_text' => 'a financial institution',
        ]);

        $this->getJson(route('notebooks.index', ['q' => 'financial institution']))
            ->assertOk()
            ->assertJsonCount(1, 'notebooks')
            ->assertJsonPath('notebooks.0.id', $notebook->id);
    });
});
