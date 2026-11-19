<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
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

    it('counts saved rows, so a repeated word still counts', function (): void {
        $notebook = Notebook::factory()->create();

        // A repeated word is a mistake to fix on the board, not a second
        // meaning: the listing shows what was typed, tag included.
        listingEntry($notebook, FeedbackType::Vocabulary, 'bank', 'a financial institution');
        listingEntry($notebook, FeedbackType::Vocabulary, 'Bank', 'the edge of a river');
        listingEntry($notebook, FeedbackType::Vocabulary, 'river', 'a watercourse');

        $this->getJson(route('notebooks.index'))
            ->assertOk()
            ->assertJsonPath('notebooks.0.counts.words', 3);
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

    it('narrows the listing to one topic, alone and together with a search', function (): void {
        $reading = Topic::factory()->create(['name' => 'Reading']);
        $travel = Topic::factory()->create(['name' => 'Travel']);

        $readingNotebook = Notebook::factory()->for($reading)->create(['name' => 'Nature reading']);
        listingEntry($readingNotebook, FeedbackType::Vocabulary, 'bank', 'a financial institution');

        $travelNotebook = Notebook::factory()->for($travel)->create(['name' => 'Trip notes']);
        listingEntry($travelNotebook, FeedbackType::Vocabulary, 'bank', 'a financial institution');

        $this->getJson(route('notebooks.index', ['topic' => $reading->id]))
            ->assertOk()
            ->assertJsonCount(1, 'notebooks')
            ->assertJsonPath('notebooks.0.id', $readingNotebook->id)
            ->assertJsonPath('topic', $reading->id)
            ->assertJsonPath('total', 1);

        $this->getJson(route('notebooks.index', ['topic' => $reading->id, 'q' => 'financial institution']))
            ->assertOk()
            ->assertJsonCount(1, 'notebooks')
            ->assertJsonPath('notebooks.0.id', $readingNotebook->id);

        $this->getJson(route('notebooks.index', ['topic' => $travel->id, 'q' => 'financial institution']))
            ->assertOk()
            ->assertJsonCount(1, 'notebooks')
            ->assertJsonPath('notebooks.0.id', $travelNotebook->id);

        $this->getJson(route('notebooks.index', ['topic' => $reading->id, 'q' => 'airport']))
            ->assertOk()
            ->assertJsonCount(0, 'notebooks')
            ->assertJsonPath('total', 0);
    });

    it('offers only the topics a notebook uses and ignores a topic it cannot offer', function (): void {
        $reading = Topic::factory()->create(['name' => 'Reading']);
        $travel = Topic::factory()->create(['name' => 'Travel']);
        Topic::factory()->create(['name' => 'Unused topic']);

        $readingNotebook = Notebook::factory()->for($reading)->create();
        Notebook::factory()->for($travel)->create();

        $this->getJson(route('notebooks.index'))
            ->assertOk()
            ->assertJsonCount(2, 'filter_topics')
            ->assertJsonPath('filter_topics.0.name', 'Reading')
            ->assertJsonPath('filter_topics.1.name', 'Travel')
            ->assertJsonPath('topic', null);

        $this->getJson(route('notebooks.index', ['topic' => 999999]))
            ->assertOk()
            ->assertJsonCount(2, 'notebooks')
            ->assertJsonPath('topic', null)
            ->assertJsonPath('notebooks.1.id', $readingNotebook->id);
    });

    it('explains a match found in an entry and names the row to open', function (): void {
        $notebook = Notebook::factory()->create(['name' => 'Kitchen words']);
        $word = listingEntry($notebook, FeedbackType::Vocabulary, 'bank', 'a financial institution', 'I went to the bank');
        $word->update(['position' => 2]);

        $response = $this->getJson(route('notebooks.index', ['q' => 'financial institution']))
            ->assertOk()
            ->assertJsonPath('notebooks.0.match.label', 'Meaning')
            ->assertJsonPath('notebooks.0.match.type', 'vocabulary')
            ->assertJsonPath('notebooks.0.match.entry', $word->client_id)
            ->assertJsonPath('notebooks.0.match.position', 2);

        $segments = $response->json('notebooks.0.match.segments');

        expect(implode('', array_column($segments, 'text')))
            ->toBe('a financial institution');
        expect(collect($segments)->where('match', true)->pluck('text')->implode(''))
            ->toBe('financialinstitution');
    });

    it('leaves the preview off when the card already shows why it matched', function (): void {
        $reading = Topic::factory()->create(['name' => 'Reading']);
        $notebook = Notebook::factory()->for($reading)->create([
            'name' => 'Nature reading',
            'description_text' => 'Chapters about forests',
        ]);
        listingEntry($notebook, FeedbackType::Vocabulary, 'bank', 'a financial institution');

        foreach (['nature', 'reading', 'forests'] as $query) {
            $this->getJson(route('notebooks.index', ['q' => $query]))
                ->assertOk()
                ->assertJsonPath('notebooks.0.match', null);
        }
    });

    it('shows a matching grammar note as plain words without its formatting', function (): void {
        $notebook = Notebook::factory()->create();
        PracticeFeedbackEntry::factory()->for($notebook)->create([
            'practice_question_id' => null,
            'type' => FeedbackType::GrammarNote,
            'content_text' => 'Use the past tense here.',
            'content_html' => '<p>Use the <strong>past tense</strong> here.</p>',
            'client_id' => (string) Str::uuid(),
        ]);

        $response = $this->getJson(route('notebooks.index', ['q' => 'past tense']))
            ->assertOk()
            ->assertJsonPath('notebooks.0.match.label', 'Note');

        $segments = $response->json('notebooks.0.match.segments');

        expect(implode('', array_column($segments, 'text')))
            ->toBe('Use the past tense here.');
        expect(json_encode($segments, JSON_THROW_ON_ERROR))->not->toContain('<');
    });

    it('points at the first matching row in the order the board shows', function (): void {
        $notebook = Notebook::factory()->create();

        PracticeFeedbackEntry::factory()->for($notebook)->create([
            'practice_question_id' => null,
            'type' => FeedbackType::GrammarNote,
            'content_text' => 'The past tense of go is went.',
            'client_id' => (string) Str::uuid(),
        ]);
        listingEntry($notebook, FeedbackType::Vocabulary, 'tense', 'how a verb shows time');

        $this->getJson(route('notebooks.index', ['q' => 'tense']))
            ->assertOk()
            ->assertJsonPath('notebooks.0.match.type', 'vocabulary');
    });

    it('asks the database for one matching row per notebook', function (): void {
        $notebook = Notebook::factory()->create();

        foreach (range(1, 3) as $number) {
            listingEntry($notebook, FeedbackType::Vocabulary, 'bank '.$number, 'river '.$number);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(route('notebooks.index', ['q' => 'bank']))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        DB::flushQueryLog();

        expect($queries->contains(
            fn (string $sql): bool => str_contains(strtolower($sql), 'row_number()')
                && str_contains(strtolower($sql), 'partition by')
                && str_contains(strtolower($sql), 'match_rank'),
        ))->toBeTrue();
    });

    it('reads a page in the same number of queries whatever it holds', function (): void {
        Notebook::factory()->count(25)->create();

        DB::enableQueryLog();
        $this->getJson(route('notebooks.index', ['per_page' => 5]))->assertJsonCount(5, 'notebooks');
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->getJson(route('notebooks.index', ['per_page' => 25]))->assertJsonCount(25, 'notebooks');
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        expect($large)->toBe($small);
    });
});
