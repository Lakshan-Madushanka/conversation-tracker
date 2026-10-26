<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Build a session from a list of question shapes, each carrying its own
 * feedback entries. Used to create sessions that mimic imported data:
 * plain text, empty questions, missing boxes, or rows with gaps.
 *
 * @param  array<int, array<string, mixed>>  $questions
 */
function editingSessionHolding(array $questions): PracticeSession
{
    $session = PracticeSession::factory()->create();

    foreach ($questions as $index => $shape) {
        $question = PracticeQuestion::factory()->for($session)->create([
            'position' => $index + 1,
            'question_html' => $shape['question_html'] ?? '',
            'question_text' => $shape['question_text'] ?? '',
            'answer_html' => $shape['answer_html'] ?? '',
            'answer_text' => $shape['answer_text'] ?? '',
        ]);

        foreach ($shape['entries'] ?? [] as $entry) {
            PracticeFeedbackEntry::factory()->for($question)->create([
                'is_split' => true,
                ...$entry,
            ]);
        }
    }

    return $session;
}

describe('opening a session', function (): void {
    it('sends an unfinished session straight to the editor', function (): void {
        $session = PracticeSession::factory()->create([
            'status' => PracticeSession::STATUS_UNFINISHED,
        ]);

        $this->get(route('sessions.show', $session))
            ->assertRedirect(route('sessions.edit', $session));
    });

    it('keeps a finished session on the reading page', function (): void {
        $session = PracticeSession::factory()->finished()->create();

        $this->get(route('sessions.show', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('sessions/Show'));
    });

    it('shows a friendly page for a session that does not exist', function (): void {
        $this->withoutVite();

        $this->get(route('sessions.show', 99999))
            ->assertNotFound()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('errors/404'));
    });

    it('still opens an existing session through its original address', function (): void {
        $session = PracticeSession::factory()->finished()->create();

        $this->get(route('sessions.show', $session->id))
            ->assertOk();
    });

    it('does not change the session when it is merely opened', function (): void {
        $session = editingSessionHolding([
            ['question_text' => 'Question', 'question_html' => '<p>Question</p>'],
        ]);

        $before = $session->fresh();

        $this->get(route('sessions.edit', $session))->assertOk();

        $after = $session->fresh();

        expect($after?->version)->toBe($before?->version)
            ->and($after?->updated_at?->toIso8601String())
            ->toBe($before?->updated_at?->toIso8601String())
            ->and($after?->last_changed_at)
            ->toBe($before?->last_changed_at);
    });

    it('splits content saved before entries existed when the editor opens', function (): void {
        $session = PracticeSession::factory()->create();
        $question = PracticeQuestion::factory()->for($session)->create(['position' => 1]);

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Mistake,
            'content_html' => '',
            'content_text' => 'I go → I went',
            'is_split' => false,
        ]);

        $this->get(route('sessions.edit', $session))->assertOk();

        $entry = PracticeFeedbackEntry::firstOrFail();

        expect($entry->content_text)->toBe('I go')
            ->and($entry->correction_text)->toBe('I went')
            ->and($entry->is_split)->toBeTrue();
    });
});

describe('the reading page', function (): void {
    it('shows the topic, status, counts, and every question in order', function (): void {
        $topic = Topic::factory()->create(['name' => 'Ordering at a restaurant']);
        $session = PracticeSession::factory()->finished()->for($topic)->create([
            'finished_at' => now()->subDay(),
        ]);

        $question = PracticeQuestion::factory()->for($session)->create([
            'position' => 1,
            'question_text' => 'What would you like?',
            'question_html' => '<p>What would you like?</p>',
            'answer_text' => 'I want a soup.',
            'answer_html' => '<p>I want a soup.</p>',
        ]);

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => 'mistake',
            'content_text' => 'I want',
            'correction_html' => '<strong>I&#039;d like</strong>',
            'reason' => 'Sounds more polite',
            'is_split' => true,
        ]);
        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => 'vocabulary',
            'content_text' => 'soup',
            'correction_text' => 'a liquid food',
            'is_split' => true,
        ]);

        $this->get(route('sessions.show', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('sessions/Show')
                ->where('session.topic.name', 'Ordering at a restaurant')
                ->where('session.finished_at', $session->finished_at?->toIso8601String())
                ->count('session.questions', 1)
                ->where('session.questions.0.question_html', '<p>What would you like?</p>')
                ->where('session.questions.0.answer_html', '<p>I want a soup.</p>')
                ->where('session.questions.0.feedback.mistake.0.text', 'I want')
                ->where('session.questions.0.feedback.vocabulary.0.meaning', 'a liquid food'));
    });

    it('hides questions that hold nothing at all', function (): void {
        $session = editingSessionHolding([
            ['question_text' => 'Real question', 'question_html' => '<p>Real question</p>'],
            ['question_text' => '', 'question_html' => ''],
        ]);
        $session->forceFill(['status' => 'finished', 'finished_at' => now()])->save();

        $this->get(route('sessions.show', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->count('session.questions', 2));
    });
});

describe('last changed tracking', function (): void {
    it('sets last_changed_at when a session is first saved', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);

        $this->postJson(route('sessions.autosave.store'), [
            'topic' => 'Travel',
            'questions' => [['question_html' => '<p>Hello</p>']],
        ])->assertCreated();

        $session = PracticeSession::sole();

        expect($session->last_changed_at)->not->toBeNull();
    });

    it('updates last_changed_at when the session is edited', function (): void {
        $session = editingSessionHolding([
            ['question_text' => 'Question', 'question_html' => '<p>Question</p>'],
        ]);
        $session->forceFill(['last_changed_at' => now()->subDay()])->save();
        $original = $session->fresh()?->last_changed_at;

        $this->travel(1)->hours();
        $this->putJson(route('sessions.autosave.update', $session), [
            'topic' => 'Travel',
            'questions' => [['question_html' => '<p>Edited</p>']],
            'version' => $session->version,
            'save_key' => 'edit-1',
        ])->assertOk();

        expect($session->fresh()?->last_changed_at)
            ->not->toBe($original)
            ->and($session->fresh()?->last_changed_at)
            ->toBeGreaterThan($original);
    });

    it('does not change last_changed_at when opening the session', function (): void {
        $session = editingSessionHolding([
            ['question_text' => 'Question', 'question_html' => '<p>Question</p>'],
        ]);

        $session->forceFill(['last_changed_at' => now()->subDay()])->save();
        $original = $session->fresh()?->last_changed_at?->toIso8601String();

        $this->get(route('sessions.edit', $session))->assertOk();

        expect($session->fresh()?->last_changed_at?->toIso8601String())
            ->toBe($original);
    });
});

describe('editing a saved session', function (): void {
    it('keeps a finished session finished after an edit', function (): void {
        $session = editingSessionHolding([
            ['question_text' => 'Question', 'question_html' => '<p>Question</p>'],
        ]);
        $session->forceFill(['status' => 'finished', 'finished_at' => now()])->save();

        $this->putJson(route('sessions.autosave.update', $session), [
            'topic' => 'Travel',
            'questions' => [['question_html' => '<p>Edited</p>']],
            'version' => $session->fresh()?->version,
            'save_key' => 'finish-edit',
        ])->assertOk();

        $fresh = $session->fresh();

        expect($fresh?->status)->toBe('finished')
            ->and($fresh?->finished_at)->not->toBeNull()
            ->and(PracticeQuestion::sole()->question_html)->toBe('<p>Edited</p>');
    });

    it('keeps an unfinished session unfinished after an edit', function (): void {
        $session = editingSessionHolding([
            ['question_text' => 'Question', 'question_html' => '<p>Question</p>'],
        ]);

        $this->putJson(route('sessions.autosave.update', $session), [
            'topic' => 'Travel',
            'questions' => [['question_html' => '<p>Edited</p>']],
            'version' => $session->version,
            'save_key' => 'unfinished-edit',
        ])->assertOk();

        $fresh = $session->fresh();

        expect($fresh?->status)->toBe('unfinished')
            ->and($fresh?->finished_at)->toBeNull();
    });

    it('changes only this session when the topic is edited', function (): void {
        $topicA = Topic::factory()->create(['name' => 'Travel']);
        $topicB = Topic::factory()->create(['name' => 'Cooking']);

        $sessionA = PracticeSession::factory()->for($topicA)->create();
        $sessionB = PracticeSession::factory()->for($topicB)->create();

        PracticeQuestion::factory()->for($sessionA)->create([
            'question_html' => '<p>A</p>',
            'question_text' => 'A',
        ]);
        PracticeQuestion::factory()->for($sessionB)->create([
            'question_html' => '<p>B</p>',
            'question_text' => 'B',
        ]);

        $this->putJson(route('sessions.autosave.update', $sessionA), [
            'topic' => 'Cooking',
            'questions' => [['question_html' => '<p>A</p>']],
            'version' => $sessionA->version,
            'save_key' => 'topic-change',
        ])->assertOk();

        expect($sessionA->fresh()?->topic->name)->toBe('Cooking')
            ->and($sessionB->fresh()?->topic->name)->toBe('Cooking')
            ->and(Topic::count())->toBe(2);
    });

    it('preserves formatted text through an edit round-trip', function (): void {
        $session = editingSessionHolding([
            ['question_text' => 'Question', 'question_html' => '<p><strong>Bold</strong> and <em>italic</em></p>'],
        ]);

        $this->get(route('sessions.edit', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('session.questions.0.question_html', '<p><strong>Bold</strong> and <em>italic</em></p>'));
    });

    it('lets an empty finished session stay finished and warns on Finish', function (): void {
        $session = editingSessionHolding([
            ['question_text' => 'Question', 'question_html' => '<p>Question</p>'],
        ]);
        $session->forceFill(['status' => 'finished', 'finished_at' => now()])->save();

        // Empty the session through the editor.
        $this->putJson(route('sessions.autosave.update', $session), [
            'topic' => 'Travel',
            'questions' => [['question_html' => '', 'answer_html' => '']],
            'version' => $session->fresh()?->version,
            'save_key' => 'empty-edit',
        ])->assertOk();

        expect($session->fresh()?->status)->toBe('finished');

        // Finishing warns instead of completing silently.
        $this->postJson(route('sessions.finish', $session))
            ->assertOk()
            ->assertJsonPath('empty', true);

        expect($session->fresh()?->status)->toBe('finished');
    });
});

describe('sessions that were not recorded through the app', function (): void {
    it('opens a session with only plain text and no formatting', function (): void {
        $session = editingSessionHolding([
            [
                'question_text' => 'Plain question',
                'question_html' => '<p>Plain question</p>',
                'answer_text' => 'Plain answer',
                'answer_html' => '<p>Plain answer</p>',
                'entries' => [
                    ['type' => 'mistake', 'content_text' => 'said', 'correction_text' => 'told', 'correction_html' => '<p>told</p>'],
                ],
            ],
        ]);

        $this->get(route('sessions.edit', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('sessions/Create')
                ->where('session.questions.0.question_html', '<p>Plain question</p>')
                ->has('session.questions.0.feedback.mistake', 1));
    });

    it('opens a session with an empty tutor question', function (): void {
        $session = editingSessionHolding([
            [
                'question_text' => '',
                'question_html' => '',
                'answer_text' => 'My answer',
                'answer_html' => '<p>My answer</p>',
            ],
        ]);

        $this->get(route('sessions.edit', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('session.questions.0.question_html', '')
                ->where('session.questions.0.answer_html', '<p>My answer</p>'));
    });

    it('opens a session where a mistake row is missing its correction', function (): void {
        $session = editingSessionHolding([
            [
                'question_text' => 'Question',
                'question_html' => '<p>Question</p>',
                'entries' => [
                    ['type' => 'mistake', 'content_text' => 'he go'],
                ],
            ],
        ]);

        $this->get(route('sessions.edit', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('session.questions.0.feedback.mistake', 1)
                ->where('session.questions.0.feedback.mistake.0.text', 'he go'));

        $this->postJson(route('sessions.finish', $session))
            ->assertOk()
            ->assertJsonPath('needs_attention', 1);
    });

    it('opens a session with many questions and edits them', function (): void {
        $questions = [];

        for ($i = 1; $i <= 10; $i++) {
            $questions[] = [
                'question_text' => "Question {$i}",
                'question_html' => "<p>Question {$i}</p>",
            ];
        }

        $session = editingSessionHolding($questions);
        $session->forceFill(['status' => 'finished', 'finished_at' => now()])->save();

        $this->get(route('sessions.edit', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->count('session.questions', 10));

        $this->putJson(route('sessions.autosave.update', $session), [
            'topic' => 'Travel',
            'questions' => array_map(
                fn (int $i): array => ['question_html' => "<p>Edited {$i}</p>"],
                range(1, 10),
            ),
            'version' => $session->fresh()?->version,
            'save_key' => 'many-edit',
        ])->assertOk();

        expect(PracticeQuestion::count())->toBe(10)
            ->and(PracticeQuestion::orderBy('position')->first()?->question_html)
            ->toBe('<p>Edited 1</p>');
    });
});
