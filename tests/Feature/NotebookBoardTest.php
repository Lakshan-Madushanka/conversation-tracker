<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\Notebook;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The whole board as the editor sends it: metadata, the current version, and
 * the three boxes. Each call gets a fresh save key unless one is named.
 *
 * @param  array<string, mixed>  $feedback
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function notebookSavePayload(Notebook $notebook, array $feedback = [], array $overrides = []): array
{
    return [
        'name' => $notebook->name,
        'topic' => $notebook->topic->name,
        'description' => $notebook->description_html ?? '',
        'version' => $notebook->version,
        'save_key' => (string) Str::uuid(),
        'feedback' => [...[
            'vocabulary' => [],
            'phrase' => [],
            'grammar_note' => [],
        ], ...$feedback],
        ...$overrides,
    ];
}

describe('the notebook board', function (): void {
    it('opens an existing notebook as an editable board', function (): void {
        $notebook = Notebook::factory()->create();

        $this->get(route('notebooks.show', $notebook))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('notebooks/Board')
                ->where('notebook.id', $notebook->id));
    });

    it('saves words, phrases, examples and grammar notes with their formatting', function (): void {
        $notebook = Notebook::factory()->create();

        $this->putJson(route('notebooks.autosave.update', $notebook), notebookSavePayload($notebook, [
            'vocabulary' => [[
                'id' => 'w1',
                'text' => 'bank',
                'meaning' => 'a financial institution, credit union',
                'example_html' => [['id' => 'ex1', 'text' => 'I <strong>went</strong> to the bank']],
            ]],
            'phrase' => [[
                'id' => 'p1',
                'text' => 'give up',
                'meaning' => 'to stop trying',
                'example_html' => '',
            ]],
            'grammar_note' => [[
                'id' => 'g1',
                'html' => '<p>Use the <em>past tense</em>.</p>',
            ]],
        ]))
            ->assertOk()
            ->assertJsonPath('notebook.version', 2)
            ->assertJsonPath('notebook.feedback.vocabulary.0.text', 'bank')
            ->assertJsonPath('notebook.feedback.vocabulary.0.meaning', 'a financial institution, credit union')
            ->assertJsonPath('notebook.feedback.vocabulary.0.example_html.0.html', 'I <strong>went</strong> to the bank')
            ->assertJsonPath('notebook.feedback.phrase.0.text', 'give up')
            ->assertJsonPath('notebook.feedback.grammar_note.0.html', '<p>Use the <em>past tense</em>.</p>');

        expect($notebook->entries()->where('type', FeedbackType::Vocabulary->value)->count())->toBe(1);

        $this->get(route('notebooks.show', $notebook))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('notebook.feedback.vocabulary.0.text', 'bank')
                ->where('notebook.feedback.grammar_note.0.html', '<p>Use the <em>past tense</em>.</p>'));
    });

    it('skips blank rows and keeps incomplete rows editable', function (): void {
        $notebook = Notebook::factory()->create();

        $this->putJson(route('notebooks.autosave.update', $notebook), notebookSavePayload($notebook, [
            'vocabulary' => [
                ['id' => 'b1', 'text' => '', 'meaning' => '', 'example_html' => ''],
                ['id' => 'i1', 'text' => 'bank', 'meaning' => '', 'example_html' => ''],
            ],
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'notebook.feedback.vocabulary')
            ->assertJsonPath('notebook.feedback.vocabulary.0.text', 'bank')
            ->assertJsonPath('notebook.feedback.vocabulary.0.meaning', '');

        expect($notebook->entries()->count())->toBe(1);
    });

    it('does not duplicate entries when the same save is retried', function (): void {
        $notebook = Notebook::factory()->create();

        $payload = notebookSavePayload($notebook, [
            'vocabulary' => [['id' => 'w1', 'text' => 'bank', 'meaning' => 'a financial institution', 'example_html' => '']],
        ], ['save_key' => 'save-1']);

        $this->putJson(route('notebooks.autosave.update', $notebook), $payload)
            ->assertOk()
            ->assertJsonPath('notebook.version', 2);

        $this->putJson(route('notebooks.autosave.update', $notebook), $payload)
            ->assertOk()
            ->assertJsonPath('notebook.version', 2);

        expect($notebook->entries()->count())->toBe(1);
    });

    it('treats a repeated row id within one box as one entry', function (): void {
        $notebook = Notebook::factory()->create();

        $this->putJson(route('notebooks.autosave.update', $notebook), notebookSavePayload($notebook, [
            'vocabulary' => [
                ['id' => 'w1', 'text' => 'bank', 'meaning' => 'a financial institution', 'example_html' => ''],
                ['id' => 'w1', 'text' => 'bank twice', 'meaning' => 'ignored', 'example_html' => ''],
            ],
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'notebook.feedback.vocabulary')
            ->assertJsonPath('notebook.feedback.vocabulary.0.text', 'bank');
    });

    it('returns the latest board when another tab saved first', function (): void {
        $notebook = Notebook::factory()->create();

        $this->putJson(route('notebooks.autosave.update', $notebook), notebookSavePayload($notebook, [
            'vocabulary' => [['id' => 'w1', 'text' => 'first', 'meaning' => 'meaning', 'example_html' => '']],
        ], ['save_key' => 'tab-a']))->assertOk();

        $this->putJson(route('notebooks.autosave.update', $notebook), notebookSavePayload($notebook, [
            'vocabulary' => [['id' => 'w1', 'text' => 'second', 'meaning' => 'meaning', 'example_html' => '']],
        ], ['save_key' => 'tab-b', 'version' => 1]))
            ->assertStatus(409)
            ->assertJsonPath('conflict', true)
            ->assertJsonPath('notebook.feedback.vocabulary.0.text', 'first');

        expect($notebook->entries()->firstOrFail()->content_text)->toBe('first');
    });

    it('refuses a blank name on autosave and keeps the saved version', function (): void {
        $notebook = Notebook::factory()->create();

        $this->putJson(route('notebooks.autosave.update', $notebook), notebookSavePayload($notebook, [], ['name' => '']))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name']]);

        expect($notebook->refresh()->version)->toBe(1);
    });

    it('keeps notebook words scoped away from session words', function (): void {
        $notebook = Notebook::factory()->create();

        $this->putJson(route('notebooks.autosave.update', $notebook), notebookSavePayload($notebook, [
            'vocabulary' => [['id' => 'w1', 'text' => 'bank', 'meaning' => 'a financial institution', 'example_html' => '']],
        ]))->assertOk();

        $key = (string) $notebook->entries()->firstOrFail()->collection_key;

        expect(substr($key, 0, 9))->toBe('Notebook-');
    });
});
