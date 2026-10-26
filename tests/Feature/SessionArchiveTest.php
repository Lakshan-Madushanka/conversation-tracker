<?php

declare(strict_types=1);

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
});

function archivedSessionForArchiveTests(): PracticeSession
{
    return PracticeSession::factory()->finished()->create([
        'created_at' => now()->subDays(4),
        'updated_at' => now()->subDays(2),
        'last_changed_at' => now()->subDay(),
        'finished_at' => now()->subDays(3),
        'version' => 7,
    ]);
}

it('archives a finished session without touching anything else', function (): void {
    Carbon::setTestNow('2026-10-26 10:00:00');

    $session = archivedSessionForArchiveTests();
    $before = $session->fresh();

    $this->postJson(route('sessions.archive', $session))
        ->assertOk()
        ->assertJsonPath('archived', true)
        ->assertJsonPath('session.id', $session->id)
        ->assertJsonPath('session.archived_at', now()->toIso8601String());

    $after = $session->fresh();

    expect($after->archived_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and($after->status)->toBe($before->status)
        ->and($after->version)->toBe($before->version)
        ->and($after->finished_at?->toIso8601String())->toBe($before->finished_at?->toIso8601String())
        ->and($after->last_changed_at?->toIso8601String())->toBe($before->last_changed_at?->toIso8601String())
        ->and($after->created_at?->toIso8601String())->toBe($before->created_at?->toIso8601String())
        ->and($after->updated_at?->toIso8601String())->toBe($before->updated_at?->toIso8601String());
});

it('refuses to archive an unfinished session and changes nothing', function (): void {
    $session = PracticeSession::factory()->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
    ]);

    $this->postJson(route('sessions.archive', $session))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Finish this session to archive it.');

    expect($session->fresh()->archived_at)->toBeNull();
});

it('archives a session only once, even when the action is repeated', function (): void {
    Carbon::setTestNow('2026-10-26 10:00:00');

    $session = archivedSessionForArchiveTests();

    $this->postJson(route('sessions.archive', $session))->assertOk();

    $archivedAt = $session->fresh()->archived_at;

    Carbon::setTestNow('2026-10-26 11:00:00');

    $this->postJson(route('sessions.archive', $session))
        ->assertOk()
        ->assertJsonPath('session.archived_at', $archivedAt?->toIso8601String());

    expect($session->fresh()->archived_at?->toIso8601String())
        ->toBe($archivedAt?->toIso8601String());
});

it('restores an archived session exactly as it was', function (): void {
    $session = archivedSessionForArchiveTests()->fresh();
    $session->timestamps = false;
    $session->archived_at = now()->subHour();
    $session->save();
    $session->timestamps = true;

    $archived = $session->fresh();

    $this->postJson(route('sessions.restore', $session))
        ->assertOk()
        ->assertJsonPath('archived', false)
        ->assertJsonPath('session.archived_at', null);

    $restored = $session->fresh();

    expect($restored->archived_at)->toBeNull()
        ->and($restored->status)->toBe($archived->status)
        ->and($restored->version)->toBe($archived->version)
        ->and($restored->finished_at?->toIso8601String())->toBe($archived->finished_at?->toIso8601String())
        ->and($restored->last_changed_at?->toIso8601String())->toBe($archived->last_changed_at?->toIso8601String())
        ->and($restored->updated_at?->toIso8601String())->toBe($archived->updated_at?->toIso8601String());
});

it('leaves a session that is already in the library alone when restored again', function (): void {
    $session = archivedSessionForArchiveTests();

    $this->postJson(route('sessions.restore', $session))
        ->assertOk()
        ->assertJsonPath('session.archived_at', null);

    expect($session->fresh()->archived_at)->toBeNull();
});

it('hides archived sessions from the library, its totals and its topics', function (): void {
    $visibleTopic = Topic::factory()->create(['name' => 'Visible topic']);
    $archivedTopic = Topic::factory()->create(['name' => 'Archived topic']);

    $visible = PracticeSession::factory()->finished()->for($visibleTopic)->create();
    $archived = PracticeSession::factory()->archived()->for($archivedTopic)->create();

    $page = $this->getJson(route('sessions.index'))->assertOk()->json();

    expect(array_column($page['sessions'], 'id'))->toBe([$visible->id])
        ->and($page['total'])->toBe(1)
        ->and($page['all_total'])->toBe(1)
        ->and($page['archived_total'])->toBe(1)
        ->and(array_column($page['topics'], 'name'))->toBe(['Visible topic'])
        ->and($archived->id)->not->toBe($visible->id);
});

it('keeps archived sessions out of home and out of the study rule', function (): void {
    $archived = PracticeSession::factory()->archived()->create();

    $visible = PracticeSession::factory()->finished()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->count('recentSessions', 1)
            ->where('recentSessions.0.id', $visible->id)
            ->where('unfinishedSession', null));

    expect(PracticeSession::query()->study()->pluck('id')->all())->toBe([$visible->id])
        ->and(PracticeSession::query()->countable()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$visible->id, $archived->id])->sort()->values()->all())
        ->and(PracticeSession::query()->visible()->pluck('id')->all())->toBe([$visible->id])
        ->and(PracticeSession::query()->archived()->pluck('id')->all())->toBe([$archived->id]);
});

it('leaves archived entries out of collections but keeps them countable', function (): void {
    $archived = PracticeSession::factory()->archived()->create();
    $visible = PracticeSession::factory()->finished()->create();

    foreach ([$archived, $visible] as $session) {
        $question = PracticeQuestion::factory()->for($session)->create();

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => PracticeFeedbackEntry::TYPE_VOCABULARY,
            'content_text' => 'soup',
            'correction_text' => 'a liquid food',
            'is_split' => true,
        ]);
    }

    $entries = PracticeFeedbackEntry::collection(PracticeFeedbackEntry::TYPE_VOCABULARY, null);

    expect($entries->total())->toBe(1)
        ->and($entries->items()[0]->practiceQuestion->practiceSession->id)->toBe($visible->id)
        ->and(PracticeSession::query()->countable()->count())->toBe(2);
});

it('lists only archived finished sessions in the archived view', function (): void {
    $archivedTopic = Topic::factory()->create(['name' => 'Archived topic']);
    $visibleTopic = Topic::factory()->create(['name' => 'Visible topic']);

    $older = PracticeSession::factory()->archived()->for($archivedTopic)->create([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $newer = PracticeSession::factory()->archived()->for($archivedTopic)->create([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    PracticeSession::factory()->finished()->for($visibleTopic)->create();
    PracticeSession::factory()->create();

    $this->getJson(route('sessions.index', ['view' => 'archived']))
        ->assertOk()
        ->assertJsonPath('filters.view', 'archived')
        ->assertJsonPath('filters.status', 'all')
        ->assertJsonPath('total', 2)
        ->assertJsonPath('all_total', 2)
        ->assertJsonPath('archived_total', 2)
        ->assertJsonPath('sessions.0.id', $newer->id)
        ->assertJsonPath('sessions.1.id', $older->id)
        ->assertJsonPath('topics.0.name', 'Archived topic');
});

it('searches only visible sessions, and archived ones only in the archived view', function (): void {
    $archivedTopic = Topic::factory()->create(['name' => 'Hidden beach']);
    $visibleTopic = Topic::factory()->create(['name' => 'Visible beach']);

    $archived = PracticeSession::factory()->archived()->for($archivedTopic)->create();
    $visible = PracticeSession::factory()->finished()->for($visibleTopic)->create();

    $this->getJson(route('sessions.index', ['q' => 'beach']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('sessions.0.id', $visible->id);

    $this->getJson(route('sessions.index', ['view' => 'archived', 'aq' => 'beach']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('sessions.0.id', $archived->id);
});

it('keeps the Library and Archived searches apart', function (): void {
    PracticeSession::factory()->archived()
        ->for(Topic::factory()->create(['name' => 'Hidden beach']))
        ->create();

    $archivedSoup = PracticeSession::factory()->archived()
        ->for(Topic::factory()->create(['name' => 'Hidden soup']))
        ->create();

    $visibleBeach = PracticeSession::factory()->finished()
        ->for(Topic::factory()->create(['name' => 'Visible beach']))
        ->create();

    PracticeSession::factory()->finished()
        ->for(Topic::factory()->create(['name' => 'Visible soup']))
        ->create();

    $library = $this->getJson(route('sessions.index', ['q' => 'beach', 'aq' => 'soup']))
        ->assertOk()
        ->json();

    expect($library['total'])->toBe(1)
        ->and($library['sessions'][0]['id'])->toBe($visibleBeach->id)
        ->and($library['filters']['q'])->toBe('beach')
        ->and($library['filters']['archivedSearch'])->toBe('soup');

    $archivedPage = $this->getJson(route('sessions.index', [
        'view' => 'archived',
        'q' => 'beach',
        'aq' => 'soup',
    ]))->assertOk()->json();

    expect($archivedPage['total'])->toBe(1)
        ->and($archivedPage['sessions'][0]['id'])->toBe($archivedSoup->id);

    // Neither view borrows the other view's words.
    expect($this->getJson(route('sessions.index', ['view' => 'archived', 'q' => 'beach']))->json('total'))
        ->toBe(2)
        ->and($this->getJson(route('sessions.index', ['aq' => 'soup']))->json('total'))
        ->toBe(2);
});

it('opens archived sessions on the reading page and sends their editor to it', function (): void {
    $session = PracticeSession::factory()->archived()->create([
        'topic_id' => Topic::factory()->create(['name' => 'Reading topic'])->id,
    ]);

    $this->get(route('sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sessions/Show')
            ->where('session.id', $session->id)
            ->where('session.archived_at', $session->archived_at?->toIso8601String()));

    $this->get(route('sessions.edit', $session))
        ->assertRedirect(route('sessions.show', $session));
});

it('refuses a stale editor save to an archived session and keeps the stored content', function (): void {
    $session = PracticeSession::factory()->archived()->create();

    $this->putJson(route('sessions.autosave.update', $session), [
        'topic' => 'A changed topic',
        'version' => $session->version,
        'save_key' => 'stale-save',
        'questions' => [
            [
                'question_html' => '<p>A new question</p>',
                'answer_html' => '',
                'feedback' => [],
            ],
        ],
    ])
        ->assertStatus(423)
        ->assertJsonPath('archived', true)
        ->assertJsonPath('message', 'This session was archived. Restore it to keep editing.');

    $session->refresh();

    expect($session->topic->name)->not->toBe('A changed topic')
        ->and($session->questions)->toHaveCount(0)
        ->and($session->version)->toBe(1);
});

it('does not move an archived session into the library when it is finished again', function (): void {
    $session = PracticeSession::factory()->archived()->create([
        'created_at' => now()->subDay(),
        'last_changed_at' => now()->subDay(),
    ]);

    $this->postJson(route('sessions.finish', $session))->assertOk();

    expect($session->fresh()->archived_at)->not->toBeNull()
        ->and($session->fresh()->last_changed_at?->toIso8601String())
        ->toBe($session->last_changed_at?->toIso8601String());
});
