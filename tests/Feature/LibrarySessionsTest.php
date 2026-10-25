<?php

declare(strict_types=1);

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('lists finished and unfinished sessions newest session date first', function (): void {
    $topic = Topic::factory()->create(['name' => 'Ordering at a restaurant']);

    $older = PracticeSession::factory()->finished()->for($topic)->create([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
        'last_changed_at' => now()->subDays(2),
    ]);

    $newer = PracticeSession::factory()->for($topic)->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
        'last_changed_at' => now()->subDay(),
    ]);

    $this->get(route('sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sessions/Index')
            ->count('sessions', 2)
            ->where('sessions.0.id', $newer->id)
            ->where('sessions.0.status', PracticeSession::STATUS_UNFINISHED)
            ->where('sessions.1.id', $older->id)
            ->where('sessions.1.status', PracticeSession::STATUS_FINISHED));
});

it('puts the most recently created session first when two share a day', function (): void {
    $topic = Topic::factory()->create();

    $earlier = PracticeSession::factory()->finished()->for($topic)->create([
        'created_at' => now()->startOfDay()->addHour(),
        'updated_at' => now()->startOfDay()->addHour(),
    ]);

    $later = PracticeSession::factory()->finished()->for($topic)->create([
        'created_at' => now()->startOfDay()->addHours(5),
        'updated_at' => now()->startOfDay()->addHours(5),
    ]);

    $this->getJson(route('sessions.index'))
        ->assertOk()
        ->assertJsonPath('sessions.0.id', $later->id)
        ->assertJsonPath('sessions.1.id', $earlier->id);
});

it('shows the topic, session date, edit date, status and counts on a card', function (): void {
    $topic = Topic::factory()->create(['name' => 'Ordering at a restaurant']);
    $session = PracticeSession::factory()->finished()->for($topic)->create([
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
        'last_changed_at' => now()->subDay(),
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
            ->where('sessions.0.session_date', $session->created_at?->toIso8601String())
            ->where('sessions.0.last_edited_at', $session->last_changed_at?->toIso8601String())
            ->where('sessions.0.status', PracticeSession::STATUS_FINISHED)
            ->where('sessions.0.counts', [
                'questions' => 1,
                'words' => 1,
                'phrases' => 0,
                'mistakes' => 1,
                'grammar_notes' => 0,
            ])
            ->where('total', 1));
});

it('uses the day the session was created as its session date', function (): void {
    $session = PracticeSession::factory()->finished()->create([
        'created_at' => now()->subDays(5),
        'finished_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $this->getJson(route('sessions.index'))
        ->assertOk()
        ->assertJsonPath('sessions.0.session_date', $session->created_at?->toIso8601String());
});

it('shows zero counts and an unfinished status for an empty session', function (): void {
    $session = PracticeSession::factory()->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $this->getJson(route('sessions.index'))
        ->assertOk()
        ->assertJsonPath('sessions.0.id', $session->id)
        ->assertJsonPath('sessions.0.status', PracticeSession::STATUS_UNFINISHED)
        ->assertJsonPath('sessions.0.counts', [
            'questions' => 0,
            'words' => 0,
            'phrases' => 0,
            'mistakes' => 0,
            'grammar_notes' => 0,
        ]);
});

it('counts every session in the header total', function (): void {
    PracticeSession::factory()->finished()->create();

    $this->getJson(route('sessions.index'))
        ->assertOk()
        ->assertJsonPath('total', 1);

    PracticeSession::factory()->create();

    $this->getJson(route('sessions.index'))
        ->assertOk()
        ->assertJsonPath('total', 2);
});

it('loads the first twenty sessions and then the next ones by cursor', function (): void {
    $topic = Topic::factory()->create();

    foreach (range(0, 24) as $index) {
        PracticeSession::factory()->finished()->for($topic)->create([
            'created_at' => now()->subMinutes($index),
            'updated_at' => now()->subMinutes($index),
        ]);
    }

    $first = $this->getJson(route('sessions.index'))->assertOk()->json();

    expect($first['sessions'])->toHaveCount(20)
        ->and($first['has_more'])->toBeTrue()
        ->and($first['next_cursor'])->toBeString()
        ->and($first['total'])->toBe(25);

    $second = $this->getJson(route('sessions.index', [
        'cursor' => $first['next_cursor'],
    ]))->assertOk()->json();

    expect($second['sessions'])->toHaveCount(5)
        ->and($second['has_more'])->toBeFalse()
        ->and($second['next_cursor'])->toBeNull();

    $ids = [
        ...array_column($first['sessions'], 'id'),
        ...array_column($second['sessions'], 'id'),
    ];

    expect(array_unique($ids))->toHaveCount(25);
});

it('never repeats or skips sessions when one is created between pages', function (): void {
    $topic = Topic::factory()->create();

    foreach (range(0, 24) as $index) {
        PracticeSession::factory()->finished()->for($topic)->create([
            'created_at' => now()->subMinutes($index + 1),
            'updated_at' => now()->subMinutes($index + 1),
        ]);
    }

    $first = $this->getJson(route('sessions.index'))->assertOk()->json();

    // A new session is created while the Library is open. It is newer than the
    // cursor, so it must not make the second page repeat or skip anything.
    PracticeSession::factory()->finished()->for($topic)->create([
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $second = $this->getJson(route('sessions.index', [
        'cursor' => $first['next_cursor'],
    ]))->assertOk()->json();

    $ids = [
        ...array_column($first['sessions'], 'id'),
        ...array_column($second['sessions'], 'id'),
    ];

    expect($second['sessions'])->toHaveCount(5)
        ->and(array_unique($ids))->toHaveCount(25);
});

it('works out counts in bulk without a query per session', function (): void {
    $topic = Topic::factory()->create();

    $makeSession = function () use ($topic): void {
        $session = PracticeSession::factory()->finished()->for($topic)->create();
        $question = PracticeQuestion::factory()->for($session)->create();

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => 'mistake',
            'content_text' => 'I go',
            'is_split' => true,
        ]);
    };

    $makeSession();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson(route('sessions.index'))->assertOk();

    $smallPageQueries = count(DB::getQueryLog());

    foreach (range(1, 19) as $index) {
        $makeSession();
    }

    DB::flushQueryLog();

    $this->getJson(route('sessions.index'))->assertOk();

    $largePageQueries = count(DB::getQueryLog());

    expect($largePageQueries)->toBe($smallPageQueries);
});

it('reflects an edit in the counts straight away', function (): void {
    $session = PracticeSession::factory()->finished()->create();
    $question = PracticeQuestion::factory()->for($session)->create();

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => 'mistake',
        'content_text' => 'I go',
        'is_split' => true,
    ]);

    $this->getJson(route('sessions.index'))
        ->assertOk()
        ->assertJsonPath('sessions.0.counts.mistakes', 1);

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => 'phrase',
        'content_text' => 'Could I have',
        'is_split' => true,
    ]);

    $this->getJson(route('sessions.index'))
        ->assertOk()
        ->assertJsonPath('sessions.0.counts.mistakes', 1)
        ->assertJsonPath('sessions.0.counts.phrases', 1);
});

it('returns an empty page when there are no sessions', function (): void {
    $this->getJson(route('sessions.index'))
        ->assertOk()
        ->assertJsonPath('sessions', [])
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('next_cursor', null)
        ->assertJsonPath('total', 0);
});
