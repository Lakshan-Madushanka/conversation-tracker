<?php

declare(strict_types=1);

use App\Actions\Collections\SaveItemMark;
use App\Actions\Collections\SaveItemNotes;
use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * A finished session at a known moment, with its own topic.
 *
 * @param  array<string, mixed>  $attributes
 */
function staysFreshSession(
    CarbonImmutable $at,
    array $attributes = [],
): PracticeSession {
    return PracticeSession::factory()
        ->finished()
        ->for(Topic::factory()->create())
        ->create(array_merge([
            'created_at' => $at,
            'updated_at' => $at,
            'finished_at' => $at,
            'last_changed_at' => $at,
        ], $attributes));
}

/**
 * One row of a table box, in its own question.
 *
 * @param  array<string, mixed>  $attributes
 */
function staysFreshRow(
    PracticeSession $session,
    FeedbackType $type,
    string $subject,
    array $attributes = [],
): PracticeFeedbackEntry {
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create(array_merge([
        'type' => $type,
        'content_text' => $subject,
        'correction_text' => '',
        'reason' => '',
        'example' => '',
        'example_html' => [],
        'position' => 0,
        'is_split' => true,
    ], $attributes));
}

/** One grammar note, always its own item. */
function staysFreshNote(PracticeSession $session, string $text): PracticeFeedbackEntry
{
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::GrammarNote,
        'content_html' => '',
        'content_text' => $text,
        'position' => 0,
    ]);
}

/** A JSON page from Collections, with the counts around the list when asked. */
function staysFreshCollectionPage(string $tab, bool $withMeta = true): array
{
    return test()->getJson(route('collections.index', array_filter([
        'tab' => $tab,
        'meta' => $withMeta ? 1 : null,
    ])))->assertOk()->json();
}

it('hides an archived session from collections and brings its marks back on restore', function (): void {
    $session = staysFreshSession(CarbonImmutable::now());
    $word = staysFreshRow($session, FeedbackType::Vocabulary, 'soup');
    $key = (string) $word->collection_key;

    $mark = app(SaveItemMark::class)->handle(
        FeedbackType::Vocabulary,
        $key,
        true,
        CollectionItemStatus::Learned,
    );

    app(SaveItemNotes::class)->handle(
        FeedbackType::Vocabulary,
        $key,
        '<p>Warm and filling.</p>',
        $mark->notes_version,
    );

    $before = staysFreshCollectionPage('vocabulary');
    expect($before['total'])->toBe(1)
        ->and($before['items'][0]['key'])->toBe($key)
        ->and($before['items'][0]['starred'])->toBeTrue()
        ->and($before['items'][0]['status'])->toBe('learned')
        ->and($before['items'][0]['has_notes'])->toBeTrue();

    $this->postJson(route('sessions.archive', $session))->assertOk();

    $hidden = staysFreshCollectionPage('vocabulary');
    expect($hidden['total'])->toBe(0)
        ->and($hidden['all_total'])->toBe(0)
        ->and($hidden['tabs'][0]['count'])->toBe(0);

    $this->postJson(route('sessions.restore', $session))->assertOk();

    $returned = staysFreshCollectionPage('vocabulary');
    expect($returned['total'])->toBe(1)
        ->and($returned['items'][0]['key'])->toBe($key)
        ->and($returned['items'][0]['starred'])->toBeTrue()
        ->and($returned['items'][0]['status'])->toBe('learned')
        ->and($returned['items'][0]['has_notes'])->toBeTrue()
        ->and($returned['items'][0])->not->toHaveKey('notes_html');
});

it('gives the same collections result whether sessions are changed one by one or in bulk', function (): void {
    $singleOne = staysFreshSession(CarbonImmutable::now()->subMinutes(4));
    staysFreshRow($singleOne, FeedbackType::Vocabulary, 'single one');

    $singleTwo = staysFreshSession(CarbonImmutable::now()->subMinutes(3));
    staysFreshRow($singleTwo, FeedbackType::Vocabulary, 'single two');

    $bulkOne = staysFreshSession(CarbonImmutable::now()->subMinutes(2));
    staysFreshRow($bulkOne, FeedbackType::Vocabulary, 'bulk one');

    $bulkTwo = staysFreshSession(CarbonImmutable::now()->subMinute());
    staysFreshRow($bulkTwo, FeedbackType::Vocabulary, 'bulk two');

    foreach ([$singleOne, $singleTwo] as $session) {
        $this->postJson(route('sessions.archive', $session))->assertOk();
    }

    $this->postJson(route('sessions.bulkArchive'), [
        'ids' => [$bulkOne->id, $bulkTwo->id],
    ])->assertOk()->assertJsonPath('archived_count', 2);

    expect(staysFreshCollectionPage('vocabulary')['total'])->toBe(0);

    foreach ([$singleOne, $singleTwo] as $session) {
        $this->postJson(route('sessions.restore', $session))->assertOk();
    }

    $this->postJson(route('sessions.bulkRestore'), [
        'ids' => [$bulkOne->id, $bulkTwo->id],
    ])->assertOk()->assertJsonPath('restored_count', 2);

    expect(staysFreshCollectionPage('vocabulary')['total'])->toBe(4);

    foreach ([$singleOne, $singleTwo] as $session) {
        $preview = $this->getJson(route('sessions.deletePreview', [
            'session' => $session,
        ]))->assertOk()->json();

        $this->deleteJson(route('sessions.deletePermanently', [
            'sessionId' => $session->id,
        ]), ['token' => $preview['token']])->assertOk();
    }

    $preview = $this->postJson(route('sessions.bulkDeletePreview'), [
        'ids' => [$bulkOne->id, $bulkTwo->id],
    ])->assertOk()->json();

    $this->deleteJson(route('sessions.bulkDelete'), [
        'ids' => [$bulkOne->id, $bulkTwo->id],
        'token' => $preview['token'],
    ])->assertOk()->assertJsonPath('deleted_count', 2);

    expect(staysFreshCollectionPage('vocabulary')['total'])->toBe(0)
        ->and(PracticeSession::query()->count())->toBe(0);
});

it('keeps every tab count equal to the collection it names after each change', function (): void {
    $session = staysFreshSession(CarbonImmutable::now());
    staysFreshRow($session, FeedbackType::Vocabulary, 'soup');
    staysFreshRow($session, FeedbackType::Phrase, 'Could I have');
    staysFreshRow($session, FeedbackType::Mistake, 'I go', [
        'correction_text' => 'I went',
    ]);
    staysFreshNote($session, 'Use the past tense here.');

    $counts = staysFreshCollectionPage('vocabulary')['tabs'];
    expect(array_column($counts, 'count', 'key'))->toBe([
        'vocabulary' => 1,
        'phrases' => 1,
        'grammar' => 1,
        'mistakes' => 1,
    ]);

    $this->postJson(route('sessions.archive', $session))->assertOk();

    $emptyCounts = staysFreshCollectionPage('vocabulary')['tabs'];
    expect(array_column($emptyCounts, 'count', 'key'))->toBe([
        'vocabulary' => 0,
        'phrases' => 0,
        'grammar' => 0,
        'mistakes' => 0,
    ]);

    $second = staysFreshSession(CarbonImmutable::now()->addMinute());
    staysFreshRow($second, FeedbackType::Vocabulary, 'bread');

    $after = staysFreshCollectionPage('vocabulary');
    expect(array_column($after['tabs'], 'count', 'key'))->toBe([
        'vocabulary' => 1,
        'phrases' => 0,
        'grammar' => 0,
        'mistakes' => 0,
    ])->and($after['total'])->toBe(1);
});

it('sends the surrounding counts only when a refresh asks for them', function (): void {
    $session = staysFreshSession(CarbonImmutable::now());
    staysFreshRow($session, FeedbackType::Vocabulary, 'soup');

    $plain = staysFreshCollectionPage('vocabulary', false);
    expect($plain)->not->toHaveKey('tabs')
        ->and($plain)->not->toHaveKey('all_total');

    $withMeta = staysFreshCollectionPage('vocabulary');
    expect($withMeta)->toHaveKey('tabs')
        ->and($withMeta['all_total'])->toBe(1)
        ->and($withMeta['has_finished_sessions'])->toBeTrue();
});

it('finds the same text in the Library and in Collections', function (): void {
    $session = staysFreshSession(CarbonImmutable::now());
    staysFreshRow($session, FeedbackType::Vocabulary, 'Crème brûlée', [
        'correction_text' => 'a baked custard',
    ]);

    $collections = $this->getJson(route('collections.index', [
        'q' => 'creme brulee',
    ]))->assertOk()->json();
    $library = $this->getJson(route('sessions.index', [
        'q' => 'creme brulee',
    ]))->assertOk()->json();

    expect($collections['total'])->toBe(1)
        ->and($library['total'])->toBe(1)
        ->and($library['sessions'][0]['id'])->toBe($session->id);
});
