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
 * A notebook on the topic, optionally holding one saved word.
 */
function topicNotebook(Topic $topic, bool $withEntry = false): Notebook
{
    $notebook = Notebook::factory()->for($topic)->create();

    if ($withEntry) {
        PracticeFeedbackEntry::factory()->for($notebook)->create([
            'practice_question_id' => null,
            'type' => FeedbackType::Vocabulary,
            'content_text' => 'bank',
            'correction_text' => 'a financial institution',
            'client_id' => (string) Str::uuid(),
        ]);
    }

    return $notebook;
}

describe('renaming a topic', function (): void {
    it('reaches every session and notebook reference without changing item identities', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);
        $session = PracticeSession::factory()->for($topic)->create();
        $notebook = topicNotebook($topic, withEntry: true);
        $question = PracticeQuestion::factory()->for($session)->create();
        $entry = PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Vocabulary,
            'content_text' => 'bank',
            'correction_text' => 'meaning',
        ]);

        $this->putJson(route('topics.update', $topic), ['name' => 'Holiday'])
            ->assertOk()
            ->assertJsonPath('name', 'Holiday');

        expect($topic->refresh()->name)->toBe('Holiday')
            ->and($session->refresh()->topic->name)->toBe('Holiday')
            ->and($notebook->refresh()->topic->name)->toBe('Holiday')
            ->and($entry->refresh()->collection_key)->toBe('bank');
    });

    it('tidies spacing the same way topic creation does', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);

        $this->putJson(route('topics.update', $topic), ['name' => '  travel   plans  '])
            ->assertOk();

        expect($topic->refresh()->name)->toBe('travel plans');
    });

    it('returns 422 when the name is empty or too long', function (string $name, string $message): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);

        $this->putJson(route('topics.update', $topic), ['name' => $name])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', $message);

        expect($topic->refresh()->name)->toBe('Travel');
    })->with([
        'empty' => ['', 'The name field is required.'],
        'over the limit' => [str_repeat('a', 121), 'The name field must not be greater than 120 characters.'],
    ]);

    it('returns 422 when another topic already uses the name', function (): void {
        Topic::factory()->create(['name' => 'Holiday']);
        $topic = Topic::factory()->create(['name' => 'Travel']);

        $this->putJson(route('topics.update', $topic), ['name' => 'holiday'])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'A topic with this name already exists.');

        expect($topic->refresh()->name)->toBe('Travel')
            ->and(Topic::count())->toBe(2);
    });

    it('accepts a name that differs from its own only by capitalisation', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);

        $this->putJson(route('topics.update', $topic), ['name' => 'travel'])
            ->assertOk();

        expect(Topic::count())->toBe(1);
    });
});

describe('deleting a topic', function (): void {
    it('returns 422 while any session or notebook still references it', function (string $case): void {
        $topic = Topic::factory()->create();

        match ($case) {
            'finished session' => PracticeSession::factory()->finished()->for($topic)->create(),
            'unfinished session' => PracticeSession::factory()->for($topic)->create(),
            'archived session' => PracticeSession::factory()->archived()->for($topic)->create(),
            'empty notebook' => topicNotebook($topic),
            'notebook with entries' => topicNotebook($topic, withEntry: true),
        };

        $this->deleteJson(route('topics.destroy', $topic))
            ->assertStatus(422)
            ->assertJsonPath('errors.topic.0', 'This topic is still used by sessions or notebooks, so it cannot be deleted.');

        expect(Topic::find($topic->id))->not->toBeNull();
    })->with([
        'finished session',
        'unfinished session',
        'archived session',
        'empty notebook',
        'notebook with entries',
    ]);

    it('deletes a topic nothing references', function (): void {
        $topic = Topic::factory()->create();

        $this->deleteJson(route('topics.destroy', $topic))
            ->assertNoContent();

        expect(Topic::find($topic->id))->toBeNull();
    });

    it('keeps the database itself from deleting a topic a notebook still uses', function (): void {
        $topic = Topic::factory()->create();
        topicNotebook($topic);

        expect(fn () => $topic->delete())->toThrow(QueryException::class);

        expect(Topic::find($topic->id))->not->toBeNull();
    });
});
