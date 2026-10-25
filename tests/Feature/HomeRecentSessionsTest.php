<?php

declare(strict_types=1);

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Inertia\Testing\AssertableInertia as Assert;

it('lists only finished sessions, newest change first', function (): void {
    $topic = Topic::factory()->create(['name' => 'Travel']);

    $older = PracticeSession::factory()->finished()->for($topic)->create([
        'created_at' => now()->subDay(),
        'finished_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $newer = PracticeSession::factory()->finished()->for($topic)->create([
        'created_at' => now(),
        'finished_at' => now(),
        'updated_at' => now(),
    ]);
    // An unfinished session belongs to the Continue card, not this list.
    PracticeSession::factory()->for($topic)->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
        'updated_at' => now()->addMinute(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Home')
            ->count('recentSessions', 2)
            ->where('recentSessions.0.id', $newer->id)
            ->where('recentSessions.0.topic', 'Travel')
            ->where('recentSessions.0.session_date', $newer->created_at?->toIso8601String())
            ->where('recentSessions.0.status', PracticeSession::STATUS_FINISHED)
            ->where('recentSessions.1.id', $older->id));
});

it('shows what each recent session holds', function (): void {
    $session = PracticeSession::factory()->finished()->create();

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

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Home')
            ->count('recentSessions', 1)
            ->where('recentSessions.0.counts', [
                'questions' => 1,
                'words' => 1,
                'phrases' => 0,
                'mistakes' => 1,
                'grammar_notes' => 0,
            ]));
});

it('shows at most five recent sessions', function (): void {
    $topic = Topic::factory()->create();

    foreach (range(1, 6) as $day) {
        PracticeSession::factory()->finished()->for($topic)->create([
            'finished_at' => now()->subDays($day),
            'updated_at' => now()->subDays($day),
        ]);
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Home')
            ->count('recentSessions', 5));
});

it('shows an empty list when nothing has been finished yet', function (): void {
    PracticeSession::factory()->for(Topic::factory())->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Home')
            ->count('recentSessions', 0));
});
