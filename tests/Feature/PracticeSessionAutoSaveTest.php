<?php

declare(strict_types=1);

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;

/**
 * Build a full editor payload for an automatic save.
 *
 * @param  array<int, array<string, mixed>>  $questions
 * @return array<string, mixed>
 */
function autosavePayload(string $topic, array $questions, array $extra = []): array
{
    return [
        'topic' => $topic,
        'questions' => $questions,
        ...$extra,
    ];
}

describe('first automatic save', function (): void {
    it('creates a session from the editor and returns it with version one', function (): void {
        $response = $this->postJson(route('sessions.autosave.store'), autosavePayload(
            'Ordering at a restaurant',
            [['question_html' => '<p>What would you like?</p>', 'answer_html' => '<p>Soup, please.</p>']],
            ['client_key' => 'client-abc'],
        ));

        $session = PracticeSession::sole();

        $response->assertCreated()
            ->assertJsonPath('session.id', $session->id)
            ->assertJsonPath('session.version', 1)
            ->assertJsonPath('session.topic.name', 'Ordering at a restaurant')
            ->assertJsonPath('session.questions.0.question_html', '<p>What would you like?</p>')
            ->assertJsonStructure(['session', 'saved_at']);

        expect($session->status)->toBe('unfinished')
            ->and($session->client_key)->toBe('client-abc')
            ->and(PracticeQuestion::count())->toBe(1);
    });

    it('creates nothing and reports the problem when there is no topic', function (): void {
        $this->postJson(route('sessions.autosave.store'), autosavePayload('   ', [['question_html' => '<p>Hi</p>']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'topic' => 'A session needs a topic so you can find it later.',
            ]);

        expect(PracticeSession::count())->toBe(0);
    });

    it('does not create a second session when the same first save is retried', function (): void {
        $payload = autosavePayload('Travel', [['question_html' => '<p>Where did you go?</p>']], [
            'client_key' => 'client-xyz',
        ]);

        $first = $this->postJson(route('sessions.autosave.store'), $payload)->assertCreated();
        $second = $this->postJson(route('sessions.autosave.store'), $payload)->assertCreated();

        expect(PracticeSession::count())->toBe(1)
            ->and($second->json('session.id'))->toBe($first->json('session.id'));
    });
});

describe('automatic update', function (): void {
    it('saves the changes and returns the next version', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);
        $session = PracticeSession::factory()->for($topic)->create();

        PracticeQuestion::factory()->for($session)->create([
            'position' => 1,
            'question_html' => '<p>Old</p>',
            'question_text' => 'Old',
        ]);

        $response = $this->putJson(route('sessions.autosave.update', $session), autosavePayload(
            'Job interview',
            [['question_html' => '<p>New</p>', 'answer_html' => '<p>My answer</p>']],
            ['version' => 1, 'save_key' => 'save-1'],
        ));

        $response->assertOk()
            ->assertJsonPath('session.version', 2)
            ->assertJsonPath('session.topic.name', 'Job interview')
            ->assertJsonPath('session.questions.0.question_html', '<p>New</p>');

        expect($session->fresh()?->version)->toBe(2)
            ->and(PracticeSession::count())->toBe(1)
            ->and(PracticeQuestion::count())->toBe(1)
            ->and(PracticeQuestion::sole()->answer_text)->toBe('My answer');
    });

    it('requires the version the client last read', function (): void {
        $session = PracticeSession::factory()->for(Topic::factory())->create();

        $this->putJson(route('sessions.autosave.update', $session), autosavePayload('Travel', [[]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('version');
    });

    it('refuses a stale save and returns the newer session instead of overwriting it', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);
        $session = PracticeSession::factory()->for($topic)->create();

        PracticeQuestion::factory()->for($session)->create([
            'position' => 1,
            'question_html' => '<p>Original</p>',
            'question_text' => 'Original',
        ]);

        // Another tab saves first, moving the session to version 2.
        $this->putJson(route('sessions.autosave.update', $session), autosavePayload(
            'Travel',
            [['question_html' => '<p>From the other tab</p>']],
            ['version' => 1, 'save_key' => 'other-tab'],
        ))->assertOk();

        $response = $this->putJson(route('sessions.autosave.update', $session), autosavePayload(
            'Travel',
            [['question_html' => '<p>My stale edit</p>']],
            ['version' => 1, 'save_key' => 'this-tab'],
        ));

        $response->assertStatus(409)
            ->assertJsonPath('conflict', true)
            ->assertJsonPath('session.version', 2)
            ->assertJsonPath('session.questions.0.question_html', '<p>From the other tab</p>');

        expect($session->fresh()?->version)->toBe(2)
            ->and(PracticeQuestion::sole()->question_html)->toBe('<p>From the other tab</p>');
    });

    it('does nothing when a retried save carries a save key the server already applied', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);
        $session = PracticeSession::factory()->for($topic)->create();

        PracticeQuestion::factory()->for($session)->create([
            'position' => 1,
            'question_html' => '<p>Original</p>',
            'question_text' => 'Original',
        ]);

        $payload = autosavePayload(
            'Travel',
            [['question_html' => '<p>Saved once</p>']],
            ['version' => 1, 'save_key' => 'same-save'],
        );

        $this->putJson(route('sessions.autosave.update', $session), $payload)->assertOk();

        $this->putJson(route('sessions.autosave.update', $session), $payload)
            ->assertOk()
            ->assertJsonPath('session.version', 2)
            ->assertJsonPath('session.questions.0.question_html', '<p>Saved once</p>');

        expect($session->fresh()?->version)->toBe(2)
            ->and(PracticeQuestion::count())->toBe(1);
    });

    it('keeps the saved session unchanged when a field is invalid', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);
        $session = PracticeSession::factory()->for($topic)->create();

        PracticeQuestion::factory()->for($session)->create([
            'position' => 1,
            'question_html' => '<p>Original</p>',
            'question_text' => 'Original',
        ]);

        $this->putJson(route('sessions.autosave.update', $session), autosavePayload(
            '',
            [['question_html' => '<p>Changed</p>']],
            ['version' => 1, 'save_key' => 'save-1'],
        ))->assertStatus(422)->assertJsonValidationErrors('topic');

        expect($session->fresh()?->version)->toBe(1)
            ->and(PracticeQuestion::sole()->question_html)->toBe('<p>Original</p>');
    });

    it('does not duplicate feedback when the same content is saved again with a new key', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);
        $session = PracticeSession::factory()->for($topic)->create();

        PracticeQuestion::factory()->for($session)->create([
            'position' => 1,
            'question_html' => '<p>Question</p>',
            'question_text' => 'Question',
        ]);

        $question = ['question_html' => '<p>Question</p>', 'feedback' => ['mistake' => 'I go → I went']];

        $this->putJson(route('sessions.autosave.update', $session), autosavePayload('Travel', [$question], [
            'version' => 1, 'save_key' => 'save-1',
        ]))->assertOk();

        $this->putJson(route('sessions.autosave.update', $session), autosavePayload('Travel', [$question], [
            'version' => 2, 'save_key' => 'save-2',
        ]))->assertOk();

        expect(PracticeQuestion::count())->toBe(1)
            ->and(PracticeFeedbackEntry::count())->toBe(1);
    });
});
