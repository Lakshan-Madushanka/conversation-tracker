<?php

declare(strict_types=1);

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Inertia\Testing\AssertableInertia as Assert;

it('lists a finished session with its topic, date and counts', function (): void {
    $topic = Topic::factory()->create(['name' => 'Ordering at a restaurant']);

    $session = PracticeSession::factory()->finished()->for($topic)->create([
        'finished_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => 1,
        'question_text' => 'Question',
        'question_html' => '<p>Question</p>',
    ]);

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => 'mistake',
        'content_text' => 'I go',
        'is_split' => true,
    ]);
    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => 'vocabulary',
        'content_text' => 'soup',
        'correction_text' => 'a liquid food',
        'is_split' => true,
    ]);

    $this->get(route('sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sessions/Index')
            ->count('sessions', 1)
            ->where('sessions.0.id', $session->id)
            ->where('sessions.0.topic', 'Ordering at a restaurant')
            ->where('sessions.0.finished_at', $session->finished_at?->toIso8601String())
            ->where('sessions.0.counts', [
                'questions' => 1,
                'words' => 1,
                'phrases' => 0,
                'mistakes' => 1,
                'grammar_notes' => 0,
            ]));
});

it('shows finished sessions newest first', function (): void {
    $topic = Topic::factory()->create();

    $older = PracticeSession::factory()->finished()->for($topic)->create([
        'finished_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);
    $newer = PracticeSession::factory()->finished()->for($topic)->create([
        'finished_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $this->get(route('sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sessions/Index')
            ->count('sessions', 2)
            ->where('sessions.0.id', $newer->id)
            ->where('sessions.1.id', $older->id));
});

it('leaves unfinished sessions out of the library', function (): void {
    PracticeSession::factory()->finished()->create([
        'finished_at' => now(),
        'updated_at' => now(),
    ]);
    PracticeSession::factory()->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
        'updated_at' => now()->addMinute(),
    ]);

    $this->get(route('sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sessions/Index')
            ->count('sessions', 1));
});

it('shows an empty library when nothing has been finished', function (): void {
    PracticeSession::factory()->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
    ]);

    $this->get(route('sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sessions/Index')
            ->count('sessions', 0));
});
