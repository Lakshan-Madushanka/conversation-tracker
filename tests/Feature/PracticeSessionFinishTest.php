<?php

declare(strict_types=1);

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;

/**
 * Build a session from a list of question shapes, each carrying its own
 * feedback entries, so a test can describe the content it expects to be
 * counted or removed.
 *
 * @param  array<int, array<string, mixed>>  $questions
 */
function sessionHolding(array $questions): PracticeSession
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
                // Saved through the editor, so the one-time split leaves it be.
                'is_split' => true,
                ...$entry,
            ]);
        }
    }

    return $session;
}

describe('counting what a session holds', function (): void {
    it('counts only the questions and entries that hold something', function (): void {
        $session = sessionHolding([
            [
                'question_text' => 'What would you like?',
                'question_html' => '<p>What would you like?</p>',
                'answer_text' => 'Soup, please.',
                'answer_html' => '<p>Soup, please.</p>',
                'entries' => [
                    ['type' => 'mistake', 'content_text' => 'I go', 'correction_text' => 'I went', 'correction_html' => '<p>I went</p>'],
                    // A mistake with no correction: counted, and needs attention.
                    ['type' => 'mistake', 'content_text' => 'he go'],
                    // Blank rows are never counted.
                    ['type' => 'mistake', 'content_text' => '   '],
                    ['type' => 'vocabulary', 'content_text' => 'soup', 'correction_text' => 'a liquid food'],
                    ['type' => 'vocabulary', 'content_text' => ' '],
                    ['type' => 'phrase', 'content_text' => 'I would like'],
                    ['type' => 'grammar_note', 'content_text' => 'Use the past tense', 'content_html' => '<p>Use the past tense</p>'],
                    // The natural version is one piece of text, not a counted entry.
                    ['type' => 'natural_version', 'content_text' => 'I would like some soup.', 'content_html' => '<p>I would like some soup.</p>'],
                ],
            ],
            // A card the learner never filled in: not a question at all.
            ['question_text' => '', 'question_html' => '', 'answer_text' => '', 'answer_html' => ''],
        ]);

        $this->postJson(route('sessions.finish', $session))
            ->assertOk()
            ->assertJsonPath('counts.questions', 1)
            ->assertJsonPath('counts.words', 1)
            ->assertJsonPath('counts.phrases', 1)
            ->assertJsonPath('counts.mistakes', 2)
            ->assertJsonPath('counts.grammar_notes', 1)
            ->assertJsonPath('needs_attention', 1)
            ->assertJsonPath('empty_questions_removed', 1);
    });

    it('still counts entries flagged as duplicates, because they are real entries', function (): void {
        $session = sessionHolding([
            [
                'question_text' => 'Question',
                'question_html' => '<p>Question</p>',
                'entries' => [
                    ['type' => 'vocabulary', 'content_text' => 'soup', 'correction_text' => 'a liquid food'],
                    ['type' => 'vocabulary', 'content_text' => 'soup', 'correction_text' => 'a liquid food'],
                ],
            ],
        ]);

        $this->postJson(route('sessions.finish', $session))
            ->assertOk()
            ->assertJsonPath('counts.words', 2);
    });
});

describe('finishing a session', function (): void {
    it('marks it finished, records the moment, and drops the empty cards', function (): void {
        $session = sessionHolding([
            ['question_text' => 'Question one', 'question_html' => '<p>Question one</p>'],
            ['question_text' => '', 'question_html' => ''],
            ['question_text' => 'Question three', 'question_html' => '<p>Question three</p>'],
        ]);

        $response = $this->postJson(route('sessions.finish', $session))
            ->assertOk()
            ->assertJsonPath('empty', false)
            ->assertJsonPath('session.status', 'finished')
            ->assertJsonPath('session.version', 2)
            ->assertJsonPath('empty_questions_removed', 1)
            ->assertJsonStructure(['session' => ['finished_at'], 'counts', 'needs_attention']);

        $fresh = $session->fresh();

        expect($fresh?->status)->toBe('finished')
            ->and($fresh?->finished_at)->not->toBeNull()
            ->and($fresh?->version)->toBe(2)
            ->and($response->json('session.finished_at'))->not->toBeNull()
            ->and(PracticeQuestion::count())->toBe(2);

        expect(PracticeQuestion::orderBy('position')->pluck('question_text')->all())
            ->toBe(['Question one', 'Question three'])
            ->and(PracticeQuestion::orderBy('position')->pluck('position')->all())
            ->toBe([1, 2]);
    });

    it('warns instead of finishing a session whose questions all hold nothing', function (): void {
        $session = sessionHolding([
            ['question_text' => '', 'question_html' => '', 'answer_text' => '', 'answer_html' => ''],
        ]);

        $this->postJson(route('sessions.finish', $session))
            ->assertOk()
            ->assertJsonPath('empty', true);

        expect($session->fresh()?->status)->toBe('unfinished')
            ->and($session->fresh()?->finished_at)->toBeNull()
            ->and(PracticeQuestion::count())->toBe(1);
    });

    it('warns instead of finishing a session with no questions at all', function (): void {
        $session = PracticeSession::factory()->create();

        $this->postJson(route('sessions.finish', $session))
            ->assertOk()
            ->assertJsonPath('empty', true);

        expect($session->fresh()?->status)->toBe('unfinished');
    });

    it('changes nothing when a finished session is finished again', function (): void {
        $session = sessionHolding([
            ['question_text' => 'Question one', 'question_html' => '<p>Question one</p>'],
        ]);

        $first = $this->postJson(route('sessions.finish', $session))->assertOk();
        $finishedAt = $session->fresh()?->finished_at?->toIso8601String();

        $second = $this->postJson(route('sessions.finish', $session))->assertOk();

        expect($session->fresh()?->version)->toBe(2)
            ->and($session->fresh()?->finished_at?->toIso8601String())->toBe($finishedAt)
            ->and(PracticeQuestion::count())->toBe(1)
            ->and($second->json('session.version'))->toBe(2)
            ->and($second->json('session.finished_at'))->toBe($first->json('session.finished_at'));
    });

    it('refuses a stale save afterwards, so a second tab cannot silently overwrite it', function (): void {
        $session = sessionHolding([
            ['question_text' => 'Question one', 'question_html' => '<p>Question one</p>'],
        ]);

        // Finishing moves the session to version 2 while this tab still holds 1.
        $this->postJson(route('sessions.finish', $session))->assertOk();

        $this->putJson(route('sessions.autosave.update', $session), [
            'topic' => 'Travel',
            'questions' => [['question_html' => '<p>From the other tab</p>']],
            'version' => 1,
            'save_key' => 'stale-tab',
        ])
            ->assertStatus(409)
            ->assertJsonPath('conflict', true)
            ->assertJsonPath('session.version', 2);
    });

    it('keeps a finished session editable', function (): void {
        $session = sessionHolding([
            ['question_text' => 'Question one', 'question_html' => '<p>Question one</p>'],
        ]);

        $this->postJson(route('sessions.finish', $session))->assertOk();

        $this->putJson(route('sessions.autosave.update', $session), [
            'topic' => 'Travel',
            'questions' => [['question_html' => '<p>A later edit</p>']],
            'version' => 2,
            'save_key' => 'edit-after-finish',
        ])
            ->assertOk()
            ->assertJsonPath('session.version', 3);

        expect($session->fresh()?->status)->toBe('finished')
            ->and(PracticeQuestion::sole()->question_html)->toBe('<p>A later edit</p>');
    });
});

describe('discarding a session', function (): void {
    it('discards a session that holds nothing', function (): void {
        $session = sessionHolding([
            ['question_text' => '', 'question_html' => ''],
        ]);

        $this->deleteJson(route('sessions.destroy', $session))
            ->assertOk()
            ->assertJsonPath('discarded', true);

        expect(PracticeSession::count())->toBe(0)
            ->and(PracticeQuestion::count())->toBe(0);
    });

    it('refuses to discard a session that holds content', function (): void {
        $session = sessionHolding([
            ['question_text' => 'Question one', 'question_html' => '<p>Question one</p>'],
        ]);

        $this->deleteJson(route('sessions.destroy', $session))->assertStatus(422);

        expect(PracticeSession::count())->toBe(1)
            ->and(PracticeQuestion::count())->toBe(1);
    });
});
