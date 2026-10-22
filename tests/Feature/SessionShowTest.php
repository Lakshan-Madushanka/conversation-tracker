<?php

declare(strict_types=1);

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Inertia\Testing\AssertableInertia as Assert;

it('shows a finished session so it can be read back', function (): void {
    $topic = Topic::factory()->create(['name' => 'Ordering at a restaurant']);
    $session = PracticeSession::factory()->finished()->for($topic)->create([
        'finished_at' => now()->subDay(),
    ]);

    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => 1,
        'question_text' => 'What would you like to start with?',
        'question_html' => '<p>What would you like to start with?</p>',
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
    PracticeFeedbackEntry::factory()->rich()->for($question)->create([
        'is_split' => true,
    ]);

    $this->get(route('sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sessions/Show')
            ->where('session.id', $session->id)
            ->where('session.topic.name', 'Ordering at a restaurant')
            ->where('session.finished_at', $session->finished_at?->toIso8601String())
            ->count('session.questions', 1)
            ->where('session.questions.0.question_html', '<p>What would you like to start with?</p>')
            ->where('session.questions.0.answer_html', '<p>I want a soup.</p>')
            ->where('session.questions.0.feedback.mistake.0.text', 'I want')
            ->where('session.questions.0.feedback.mistake.0.correction_html', '<strong>I&#039;d like</strong>')
            ->where('session.questions.0.feedback.vocabulary.0.meaning', 'a liquid food')
            ->where('session.questions.0.feedback.grammar_note.0.html', '<p>Use the <strong>past tense</strong> here.</p>'));
});

it('redirects an unfinished session to the editor, since the learner is still working on it', function (): void {
    $session = PracticeSession::factory()->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
    ]);

    $this->get(route('sessions.show', $session))
        ->assertRedirect(route('sessions.edit', $session));
});

it('cannot show a session that does not exist', function (): void {
    $this->get(route('sessions.show', 999))->assertNotFound();
});
