<?php

declare(strict_types=1);

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

function librarySession(
    Topic $topic,
    CarbonImmutable $created,
    string $status = PracticeSession::STATUS_FINISHED,
    ?CarbonImmutable $edited = null,
): PracticeSession {
    return PracticeSession::factory()->for($topic)->create([
        'status' => $status,
        'finished_at' => $status === PracticeSession::STATUS_FINISHED ? $created : null,
        'created_at' => $created,
        'updated_at' => $created,
        'last_changed_at' => $edited ?? $created,
    ]);
}

function libraryEntry(PracticeSession $session, string $type, array $values = []): void
{
    $question = PracticeQuestion::factory()->for($session)->create();

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => $type,
        ...$values,
    ]);
}

/**
 * @return list<int>
 */
function libraryIds(array $response): array
{
    return array_map(intval(...), array_column($response['sessions'], 'id'));
}

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-13 10:00:00', 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('filters by one topic and by several topics at once', function (): void {
    $travel = Topic::factory()->create(['name' => 'Travel']);
    $food = Topic::factory()->create(['name' => 'Food']);
    $work = Topic::factory()->create(['name' => 'Work']);

    $travelSession = librarySession($travel, now()->subDay()->toImmutable());
    $foodSession = librarySession($food, now()->subDays(2)->toImmutable());
    $workSession = librarySession($work, now()->subDays(3)->toImmutable());

    $one = $this->getJson(route('sessions.index', ['topics' => $travel->id]))
        ->assertOk()
        ->json();

    expect(libraryIds($one))->toBe([$travelSession->id]);

    $several = $this->getJson(route('sessions.index', [
        'topics' => "{$travel->id},{$food->id}",
    ]))->assertOk()->json();

    expect(libraryIds($several))->toBe([$travelSession->id, $foodSession->id])
        ->and($several['total'])->toBe(2)
        ->and($several['all_total'])->toBe(3);

    // The same choice may arrive as repeated values instead of one string.
    $array = $this->getJson(route('sessions.index', [
        'topics' => [$travel->id, $work->id],
    ]))->assertOk()->json();

    expect(libraryIds($array))->toBe([$travelSession->id, $workSession->id]);
});

it('filters by each quick date choice', function (): void {
    $topic = Topic::factory()->create();

    $today = librarySession($topic, now()->startOfDay()->addHours(9)->toImmutable());
    $yesterday = librarySession($topic, now()->subDay()->startOfDay()->addHours(9)->toImmutable());
    $threeWeeksAgo = librarySession($topic, now()->subDays(23)->toImmutable());
    $lastYear = librarySession($topic, now()->subYear()->toImmutable());

    $todayIds = libraryIds($this->getJson(route('sessions.index', [
        'date' => 'today',
    ]))->assertOk()->json());

    $weekIds = libraryIds($this->getJson(route('sessions.index', [
        'date' => 'last7',
    ]))->assertOk()->json());

    $monthIds = libraryIds($this->getJson(route('sessions.index', [
        'date' => 'last30',
    ]))->assertOk()->json());

    expect($todayIds)->toBe([$today->id])
        ->and($weekIds)->toBe([$today->id, $yesterday->id])
        ->and($monthIds)->toBe([$today->id, $yesterday->id, $threeWeeksAgo->id])
        ->and($monthIds)->not->toContain($lastYear->id);
});

it('filters by a custom range and includes both end days', function (): void {
    $topic = Topic::factory()->create();

    $first = librarySession($topic, CarbonImmutable::parse('2026-10-01 00:00:00', 'UTC'));
    $middle = librarySession($topic, CarbonImmutable::parse('2026-10-05 12:00:00', 'UTC'));
    $last = librarySession($topic, CarbonImmutable::parse('2026-10-12 23:59:00', 'UTC'));
    $outside = librarySession($topic, CarbonImmutable::parse('2026-10-13 00:01:00', 'UTC'));

    $response = $this->getJson(route('sessions.index', [
        'date' => 'custom',
        'from' => '2026-10-01',
        'to' => '2026-10-12',
    ]))->assertOk()->json();

    expect(libraryIds($response))->toBe([$last->id, $middle->id, $first->id])
        ->and(libraryIds($response))->not->toContain($outside->id);
});

it('corrects a custom range whose end is before its start', function (): void {
    $topic = Topic::factory()->create();

    $inRange = librarySession($topic, CarbonImmutable::parse('2026-10-05 12:00:00', 'UTC'));
    $outside = librarySession($topic, CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC'));

    $response = $this->getJson(route('sessions.index', [
        'date' => 'custom',
        'from' => '2026-10-12',
        'to' => '2026-10-01',
    ]))->assertOk()->json();

    expect(libraryIds($response))->toBe([$inRange->id])
        ->and(libraryIds($response))->not->toContain($outside->id)
        ->and($response['filters']['from'])->toBe('2026-10-01')
        ->and($response['filters']['to'])->toBe('2026-10-12');
});

it('shows a session on the day the learner experienced it near midnight', function (): void {
    $topic = Topic::factory()->create();

    // 23:30 UTC on the 12th is already the 13th in Sri Lanka.
    $session = librarySession($topic, CarbonImmutable::parse('2026-10-12 23:30:00', 'UTC'));

    $utc = $this->getJson(route('sessions.index', [
        'date' => 'today',
        'tz' => 'UTC',
    ]))->assertOk()->json();

    $local = $this->getJson(route('sessions.index', [
        'date' => 'today',
        'tz' => 'Asia/Colombo',
    ]))->assertOk()->json();

    expect(libraryIds($utc))->toBe([])
        ->and(libraryIds($local))->toBe([$session->id]);
});

it('filters by each content type and by several at once', function (): void {
    $topic = Topic::factory()->create();

    $mistake = librarySession($topic, now()->subMinutes(1)->toImmutable());
    libraryEntry($mistake, PracticeFeedbackEntry::TYPE_MISTAKE, [
        'content_text' => 'I go',
        'correction_text' => 'I went',
    ]);

    $vocabulary = librarySession($topic, now()->subMinutes(2)->toImmutable());
    libraryEntry($vocabulary, PracticeFeedbackEntry::TYPE_VOCABULARY, [
        'content_text' => 'soup',
        'correction_text' => 'a liquid food',
    ]);

    $phrase = librarySession($topic, now()->subMinutes(3)->toImmutable());
    libraryEntry($phrase, PracticeFeedbackEntry::TYPE_PHRASE, [
        'content_text' => 'Could I have',
        'correction_text' => 'a polite request',
    ]);

    $grammar = librarySession($topic, now()->subMinutes(4)->toImmutable());
    libraryEntry($grammar, PracticeFeedbackEntry::TYPE_GRAMMAR_NOTE, [
        'content_html' => '<p>Use the past tense.</p>',
        'content_text' => 'Use the past tense.',
    ]);

    $natural = librarySession($topic, now()->subMinutes(5)->toImmutable());
    libraryEntry($natural, PracticeFeedbackEntry::TYPE_NATURAL_VERSION, [
        'content_html' => '<p>I would like the soup.</p>',
        'content_text' => 'I would like the soup.',
    ]);

    $blank = librarySession($topic, now()->subMinutes(6)->toImmutable());
    libraryEntry($blank, PracticeFeedbackEntry::TYPE_MISTAKE, [
        'content_text' => '',
        'content_html' => '',
        'correction_text' => '',
        'correction_html' => '',
        'reason' => '',
        'example' => '',
    ]);

    $expected = [
        'mistake' => [$mistake->id],
        'vocabulary' => [$vocabulary->id],
        'phrase' => [$phrase->id],
        'grammar_note' => [$grammar->id],
        'natural_version' => [$natural->id],
    ];

    foreach ($expected as $type => $ids) {
        expect(libraryIds($this->getJson(route('sessions.index', [
            'types' => $type,
        ]))->assertOk()->json()))->toBe($ids);
    }

    $several = $this->getJson(route('sessions.index', [
        'types' => 'mistake,vocabulary',
    ]))->assertOk()->json();

    expect(libraryIds($several))->toBe([$mistake->id, $vocabulary->id])
        ->and(libraryIds($several))->not->toContain($blank->id);
});

it('filters by status without mixing unfinished sessions in', function (): void {
    $topic = Topic::factory()->create();

    $finished = librarySession($topic, now()->subDay()->toImmutable());
    $unfinished = librarySession(
        $topic,
        now()->subDays(2)->toImmutable(),
        PracticeSession::STATUS_UNFINISHED,
    );

    expect(libraryIds($this->getJson(route('sessions.index', [
        'status' => 'finished',
    ]))->assertOk()->json()))->toBe([$finished->id])
        ->and(libraryIds($this->getJson(route('sessions.index', [
            'status' => 'unfinished',
        ]))->assertOk()->json()))->toBe([$unfinished->id])
        ->and(libraryIds($this->getJson(route('sessions.index'))->assertOk()->json()))
        ->toBe([$finished->id, $unfinished->id]);
});

it('combines every kind of filter', function (): void {
    $travel = Topic::factory()->create();
    $food = Topic::factory()->create();

    $matching = librarySession($travel, CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC'));
    libraryEntry($matching, PracticeFeedbackEntry::TYPE_MISTAKE);

    $wrongTopic = librarySession($food, CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC'));
    libraryEntry($wrongTopic, PracticeFeedbackEntry::TYPE_MISTAKE);

    $wrongDate = librarySession($travel, CarbonImmutable::parse('2026-09-01 12:00:00', 'UTC'));
    libraryEntry($wrongDate, PracticeFeedbackEntry::TYPE_MISTAKE);

    $wrongStatus = librarySession(
        $travel,
        CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC'),
        PracticeSession::STATUS_UNFINISHED,
    );
    libraryEntry($wrongStatus, PracticeFeedbackEntry::TYPE_MISTAKE);

    $wrongType = librarySession($travel, CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC'));
    libraryEntry($wrongType, PracticeFeedbackEntry::TYPE_VOCABULARY);

    $response = $this->getJson(route('sessions.index', [
        'topics' => $travel->id,
        'types' => 'mistake',
        'status' => 'finished',
        'date' => 'custom',
        'from' => '2026-10-01',
        'to' => '2026-10-31',
    ]))->assertOk()->json();

    expect(libraryIds($response))->toBe([$matching->id])
        ->and($response['total'])->toBe(1);
});

it('sorts newest, oldest and recently edited', function (): void {
    $topic = Topic::factory()->create();

    $oldButEdited = librarySession(
        $topic,
        CarbonImmutable::parse('2026-10-01 09:00:00', 'UTC'),
        PracticeSession::STATUS_FINISHED,
        CarbonImmutable::parse('2026-10-12 09:00:00', 'UTC'),
    );
    $middle = librarySession($topic, CarbonImmutable::parse('2026-10-05 09:00:00', 'UTC'));
    $newest = librarySession($topic, CarbonImmutable::parse('2026-10-10 09:00:00', 'UTC'));

    $newestFirst = libraryIds($this->getJson(route('sessions.index'))->assertOk()->json());
    $oldestFirst = libraryIds($this->getJson(route('sessions.index', [
        'sort' => 'oldest',
    ]))->assertOk()->json());
    $recentlyEdited = libraryIds($this->getJson(route('sessions.index', [
        'sort' => 'recently_edited',
    ]))->assertOk()->json());

    expect($newestFirst)->toBe([$newest->id, $middle->id, $oldButEdited->id])
        ->and($oldestFirst)->toBe([$oldButEdited->id, $middle->id, $newest->id])
        ->and($recentlyEdited)->toBe([$oldButEdited->id, $newest->id, $middle->id]);
});

it('counts the matching sessions and the whole Library separately', function (): void {
    $topic = Topic::factory()->create();

    foreach (range(1, 3) as $index) {
        $session = librarySession($topic, now()->subDays($index)->toImmutable());
        libraryEntry($session, PracticeFeedbackEntry::TYPE_MISTAKE);
    }

    foreach (range(4, 6) as $index) {
        librarySession($topic, now()->subDays($index)->toImmutable());
    }

    $filtered = $this->getJson(route('sessions.index', [
        'types' => 'mistake',
    ]))->assertOk()->json();

    expect($filtered['total'])->toBe(3)
        ->and($filtered['all_total'])->toBe(6)
        ->and($filtered['has_more'])->toBeFalse();
});

it('ignores values it cannot understand and offers only topics in use', function (): void {
    $used = Topic::factory()->create(['name' => 'Travel']);
    $unused = Topic::factory()->create(['name' => 'Unused']);

    $session = librarySession($used, now()->subDay()->toImmutable());
    libraryEntry($session, PracticeFeedbackEntry::TYPE_MISTAKE);

    $response = $this->getJson(route('sessions.index', [
        'topics' => "{$used->id},999999,not-a-number",
        'types' => 'mistake,not-a-type',
        'date' => 'someday',
        'status' => 'maybe',
        'sort' => 'sideways',
        'tz' => 'Mars/Olympus',
    ]))->assertOk()->json();

    expect($response['sessions'])->toHaveCount(1)
        ->and($response['filters'])->toBe([
            'topics' => [$used->id],
            'types' => ['mistake'],
            'date' => 'any',
            'from' => null,
            'to' => null,
            'status' => 'all',
            'sort' => 'newest',
            'q' => '',
            'archivedSearch' => '',
            'view' => 'library',
        ])
        ->and($response['timezone'])->toBe(config('app.timezone'))
        ->and(array_column($response['topics'], 'id'))->toBe([$used->id])
        ->and(array_column($response['topics'], 'id'))->not->toContain($unused->id);
});

it('loads more within the chosen filters without leaking other sessions', function (): void {
    $topic = Topic::factory()->create();

    $matching = [];

    foreach (range(0, 24) as $index) {
        $session = librarySession($topic, now()->subMinutes($index + 1)->toImmutable());
        libraryEntry($session, PracticeFeedbackEntry::TYPE_MISTAKE);
        $matching[] = $session->id;
    }

    foreach (range(0, 4) as $index) {
        librarySession($topic, now()->subMinutes($index + 1)->toImmutable());
    }

    $first = $this->getJson(route('sessions.index', [
        'types' => 'mistake',
    ]))->assertOk()->json();

    $second = $this->getJson(route('sessions.index', [
        'types' => 'mistake',
        'cursor' => $first['next_cursor'],
    ]))->assertOk()->json();

    $ids = [...libraryIds($first), ...libraryIds($second)];

    expect($first['sessions'])->toHaveCount(20)
        ->and($second['sessions'])->toHaveCount(5)
        ->and($ids)->toBe($matching)
        ->and($first['total'])->toBe(25)
        ->and($first['all_total'])->toBe(30);
});

it('keeps the number of queries flat as filtered sessions grow', function (): void {
    $topic = Topic::factory()->create();

    $makeSession = function () use ($topic): void {
        $session = librarySession($topic, now()->subMinute()->toImmutable());
        libraryEntry($session, PracticeFeedbackEntry::TYPE_MISTAKE);
    };

    $makeSession();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson(route('sessions.index', ['types' => 'mistake']))->assertOk();

    $small = count(DB::getQueryLog());

    foreach (range(1, 19) as $index) {
        $makeSession();
    }

    DB::flushQueryLog();

    $this->getJson(route('sessions.index', ['types' => 'mistake']))->assertOk();

    expect(count(DB::getQueryLog()))->toBe($small);
});

it('sends the chosen filters to the page so a refresh or bookmark keeps them', function (): void {
    $topic = Topic::factory()->create(['name' => 'Travel']);
    librarySession($topic, now()->subDay()->toImmutable());

    $this->get(route('sessions.index', [
        'topics' => $topic->id,
        'date' => 'today',
        'status' => 'finished',
        'sort' => 'oldest',
        'tz' => 'Asia/Colombo',
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('sessions/Index')
            ->where('filters.topics', [$topic->id])
            ->where('filters.date', 'today')
            ->where('filters.status', 'finished')
            ->where('filters.sort', 'oldest')
            ->where('timezone', 'Asia/Colombo')
            ->where('all_total', 1)
            ->has('topics', 1));
});
