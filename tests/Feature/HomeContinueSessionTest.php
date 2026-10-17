<?php

declare(strict_types=1);

use App\Models\PracticeSession;
use App\Models\Topic;
use Inertia\Testing\AssertableInertia as Assert;

it('offers to continue the most recent unfinished session', function (): void {
    $topic = Topic::factory()->create(['name' => 'Travel']);
    $older = PracticeSession::factory()->for($topic)->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
        'updated_at' => now()->subHour(),
    ]);
    $newer = PracticeSession::factory()->for($topic)->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
        'updated_at' => now(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Home')
            ->where('unfinishedSession.id', $newer->id)
            ->where('unfinishedSession.topic', 'Travel')
            ->where('unfinishedSession.updated_at', $newer->updated_at?->toIso8601String()));
});

it('shows no continue card when every session is finished or there are none', function (): void {
    PracticeSession::factory()->for(Topic::factory())->create([
        'status' => 'finished',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Home')
            ->where('unfinishedSession', null));
});
