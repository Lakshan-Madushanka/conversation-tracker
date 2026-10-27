<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Queries\LibrarySessions;
use App\Support\LibraryFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Builds a session the learner would have saved, with one entry of the chosen
 * kind per call. Counts are always read from the stored content.
 */
function addBulkTestEntry(
    PracticeSession $session,
    FeedbackType $type,
    string $content,
): void {
    $question = $session->questions()->first()
        ?? PracticeQuestion::factory()->for($session)->create();

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => $type,
        'content_text' => $content,
        'content_html' => '',
    ]);
}

function bulkTestSessionAt(
    string $createdAt,
    bool $finished = true,
    int $words = 1,
): PracticeSession {
    $session = $finished
        ? PracticeSession::factory()->finished()->create([
            'finished_at' => Carbon::parse($createdAt),
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ])
        : PracticeSession::factory()->create([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);

    for ($index = 0; $index < $words; $index++) {
        addBulkTestEntry($session, FeedbackType::Vocabulary, 'word '.$index);
    }

    return $session;
}

function bulkPreviewFor(array $ids, string $timezone = 'UTC'): array
{
    return test()->postJson(route('sessions.bulkDeletePreview'), [
        'ids' => $ids,
        'tz' => $timezone,
    ])->assertOk()->json();
}

function bulkDeleteWithToken(array $ids, string $token, string $timezone = 'UTC')
{
    return test()->deleteJson(route('sessions.bulkDelete'), [
        'ids' => $ids,
        'token' => $token,
        'tz' => $timezone,
    ]);
}

it('selects every matching session, not only the loaded page', function (): void {
    $topic = Topic::factory()->create(['name' => 'Ordering at a restaurant']);

    $sessions = collect(range(1, 25))
        ->map(fn (int $index): PracticeSession => PracticeSession::factory()
            ->finished()
            ->for($topic)
            ->create([
                'created_at' => now()->subMinutes($index),
                'updated_at' => now()->subMinutes($index),
            ]));

    $finished = $sessions->first();
    $unfinished = PracticeSession::factory()->for($topic)->create([
        'created_at' => now()->addMinute(),
        'updated_at' => now()->addMinute(),
    ]);

    $selection = $this->getJson(route('sessions.selection'))->assertOk()->json();

    expect($selection['total'])->toBe(26)
        ->and($selection['limit'])->toBe(LibrarySessions::SELECTION_LIMIT)
        ->and($selection['truncated'])->toBeFalse()
        ->and($selection['ids'])->toHaveCount(26)
        ->and($selection['ids'][0])->toBe($unfinished->id)
        ->and($selection['finished_ids'])->toHaveCount(25)
        ->and($selection['finished_ids'][0])->toBe($finished->id);
});

it('selects only what the current filters and search match', function (): void {
    $beach = Topic::factory()->create(['name' => 'Beach holiday']);
    $work = Topic::factory()->create(['name' => 'Work meeting']);

    $matching = PracticeSession::factory()->finished()->for($beach)->create();
    $other = PracticeSession::factory()->finished()->for($work)->create();

    $selection = $this->getJson(route('sessions.selection', [
        'topics' => $beach->id,
        'q' => 'beach',
    ]))->assertOk()->json();

    expect($selection['ids'])->toBe([$matching->id])
        ->and($selection['finished_ids'])->toBe([$matching->id])
        ->and($selection['ids'])->not->toContain($other->id);
});

it('refuses a selection larger than the limit as a whole', function (): void {
    $topic = Topic::factory()->create();

    PracticeSession::factory()->count(LibrarySessions::SELECTION_LIMIT + 1)->finished()->for($topic)->create();

    $selection = $this->getJson(route('sessions.selection'))->assertOk()->json();

    expect($selection['truncated'])->toBeTrue()
        ->and($selection['ids'])->toBe([])
        ->and($selection['finished_ids'])->toBe([])
        ->and($selection['total'])->toBe(LibrarySessions::SELECTION_LIMIT + 1)
        ->and($selection['limit'])->toBe(LibrarySessions::SELECTION_LIMIT);
});

it('archives only the finished sessions in a selection without touching anything else', function (): void {
    Carbon::setTestNow('2026-10-26 10:00:00');

    $finished = PracticeSession::factory()->finished()->create([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
        'last_changed_at' => now()->subDay(),
        'finished_at' => now()->subDays(2),
        'version' => 4,
    ]);

    $before = $finished->fresh();

    $unfinished = PracticeSession::factory()->create();

    $alreadyArchived = PracticeSession::factory()->archived()->create();

    $response = $this->postJson(route('sessions.bulkArchive'), [
        'ids' => [$finished->id, $unfinished->id, $alreadyArchived->id, 999999],
    ])->assertOk();

    $response
        ->assertJsonPath('archived', true)
        ->assertJsonPath('archived_count', 1)
        ->assertJsonPath('archived_ids', [$finished->id])
        ->assertJsonPath('skipped_unfinished', 1)
        ->assertJsonPath('skipped_archived', 1)
        ->assertJsonPath('skipped_missing', 1);

    $after = $finished->fresh();

    expect($after->archived_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and($after->status)->toBe($before->status)
        ->and($after->version)->toBe($before->version)
        ->and($after->finished_at?->toIso8601String())->toBe($before->finished_at?->toIso8601String())
        ->and($after->last_changed_at?->toIso8601String())->toBe($before->last_changed_at?->toIso8601String())
        ->and($after->created_at?->toIso8601String())->toBe($before->created_at?->toIso8601String())
        ->and($after->updated_at?->toIso8601String())->toBe($before->updated_at?->toIso8601String())
        ->and($unfinished->fresh()->archived_at)->toBeNull();
});

it('restores only the archived sessions in a selection and leaves the rest alone', function (): void {
    $archived = PracticeSession::factory()->archived()->create([
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(2),
        'last_changed_at' => now()->subDay(),
        'finished_at' => now()->subDays(3),
        'version' => 8,
    ]);

    $before = $archived->fresh();
    $visible = PracticeSession::factory()->finished()->create();

    $this->postJson(route('sessions.bulkRestore'), [
        'ids' => [$archived->id, $visible->id, 999999],
    ])
        ->assertOk()
        ->assertJsonPath('restored', true)
        ->assertJsonPath('restored_count', 1)
        ->assertJsonPath('restored_ids', [$archived->id])
        ->assertJsonPath('skipped_not_archived', 1)
        ->assertJsonPath('skipped_missing', 1);

    $after = $archived->fresh();

    expect($after->archived_at)->toBeNull()
        ->and($after->status)->toBe($before->status)
        ->and($after->version)->toBe($before->version)
        ->and($after->finished_at?->toIso8601String())->toBe($before->finished_at?->toIso8601String())
        ->and($after->updated_at?->toIso8601String())->toBe($before->updated_at?->toIso8601String());
});

it('previews the whole selection with combined counts and progress', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $finished = bulkTestSessionAt('2026-10-26 09:00:00', words: 2);
    $unfinished = bulkTestSessionAt('2026-10-25 09:00:00', finished: false, words: 1);
    $archived = PracticeSession::factory()->archived()->create([
        'created_at' => Carbon::parse('2026-10-24 09:00:00'),
        'finished_at' => Carbon::parse('2026-10-24 09:00:00'),
    ]);
    addBulkTestEntry($archived, FeedbackType::Vocabulary, 'soup');

    $preview = bulkPreviewFor([$finished->id, $unfinished->id, $archived->id, 999999]);

    expect($preview['requested_count'])->toBe(4)
        ->and($preview['session_count'])->toBe(3)
        ->and($preview['missing_count'])->toBe(1)
        ->and($preview['finished_count'])->toBe(2)
        ->and($preview['unfinished_count'])->toBe(1)
        ->and($preview['archivable_count'])->toBe(1)
        ->and($preview['all_finished_archived'])->toBeFalse()
        ->and($preview['names'])->toHaveCount(3)
        ->and($preview['more_count'])->toBe(0)
        ->and($preview['counts'])->toBe([
            'questions' => 3,
            'words' => 4,
            'phrases' => 0,
            'mistakes' => 0,
            'grammar_notes' => 0,
        ])
        ->and($preview['progress']['before']['words'])->toBe(3)
        ->and($preview['progress']['after']['words'])->toBe(0)
        ->and($preview['token'])->toBeString()->toHaveLength(64);
});

it('deletes every selected session and everything inside them', function (): void {
    $finished = bulkTestSessionAt('2026-10-26 09:00:00', words: 2);
    $unfinished = bulkTestSessionAt('2026-10-25 09:00:00', finished: false, words: 1);

    $questionIds = PracticeQuestion::query()
        ->whereIn('practice_session_id', [$finished->id, $unfinished->id])
        ->pluck('id')
        ->all();

    $preview = bulkPreviewFor([$finished->id, $unfinished->id]);

    bulkDeleteWithToken([$finished->id, $unfinished->id], $preview['token'])
        ->assertOk()
        ->assertJsonPath('deleted', true)
        ->assertJsonPath('already_deleted', false)
        ->assertJsonPath('deleted_count', 2)
        ->assertJsonPath('skipped_missing', 0);

    expect(PracticeSession::query()->whereIn('id', [$finished->id, $unfinished->id])->exists())->toBeFalse()
        ->and(PracticeQuestion::query()->whereIn('id', $questionIds)->exists())->toBeFalse()
        ->and(PracticeFeedbackEntry::query()->whereIn('practice_question_id', $questionIds)->exists())->toBeFalse();
});

it('treats a repeated bulk confirmation as success and deletes only once', function (): void {
    $first = bulkTestSessionAt('2026-10-26 09:00:00');
    $second = bulkTestSessionAt('2026-10-25 09:00:00');

    $preview = bulkPreviewFor([$first->id, $second->id]);

    bulkDeleteWithToken([$first->id, $second->id], $preview['token'])->assertOk();

    bulkDeleteWithToken([$first->id, $second->id], $preview['token'])
        ->assertOk()
        ->assertJsonPath('deleted', true)
        ->assertJsonPath('already_deleted', true)
        ->assertJsonPath('deleted_count', 0)
        ->assertJsonPath('skipped_missing', 2);
});

it('refuses a stale bulk confirmation and deletes nothing', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $target = bulkTestSessionAt('2026-10-26 09:00:00', words: 1);
    $other = bulkTestSessionAt('2026-10-25 09:00:00');

    $preview = bulkPreviewFor([$target->id, $other->id]);

    // The target gains a word after the warning was opened.
    addBulkTestEntry($target, FeedbackType::Vocabulary, 'napkin');

    $response = bulkDeleteWithToken([$target->id, $other->id], $preview['token'])
        ->assertStatus(409)
        ->assertJsonPath('stale', true)
        ->assertJsonPath('preview.counts.words', 3)
        ->assertJsonPath('preview.progress.after.words', 0);

    expect(PracticeSession::query()->whereIn('id', [$target->id, $other->id])->count())->toBe(2);

    $fresh = $response->json('preview');

    bulkDeleteWithToken([$target->id, $other->id], $fresh['token'])
        ->assertOk()
        ->assertJsonPath('deleted_count', 2);
});

it('refuses a confirmation that changed because one session was deleted elsewhere', function (): void {
    $first = bulkTestSessionAt('2026-10-26 09:00:00');
    $second = bulkTestSessionAt('2026-10-25 09:00:00');

    $preview = bulkPreviewFor([$first->id, $second->id]);

    // Another tab deletes one of the selected sessions.
    PracticeSession::query()->whereKey($first->id)->delete();

    $response = bulkDeleteWithToken([$first->id, $second->id], $preview['token'])
        ->assertStatus(409)
        ->assertJsonPath('stale', true)
        ->assertJsonPath('preview.session_count', 1)
        ->assertJsonPath('preview.missing_count', 1);

    expect(PracticeSession::query()->whereKey($second->id)->exists())->toBeTrue();

    $fresh = $response->json('preview');

    bulkDeleteWithToken([$first->id, $second->id], $fresh['token'])
        ->assertOk()
        ->assertJsonPath('deleted_count', 1)
        ->assertJsonPath('skipped_missing', 1);
});

it('never deletes a different selection with a valid token', function (): void {
    $first = bulkTestSessionAt('2026-10-26 09:00:00');
    $second = bulkTestSessionAt('2026-10-25 09:00:00');

    $preview = bulkPreviewFor([$first->id]);

    bulkDeleteWithToken([$second->id], $preview['token'])->assertStatus(409);

    expect(PracticeSession::query()->whereIn('id', [$first->id, $second->id])->count())->toBe(2);
});

it('leaves progress exactly where the combined warning said after a bulk delete', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $target = bulkTestSessionAt('2026-10-26 09:00:00', words: 2);
    $alsoLeaving = bulkTestSessionAt('2026-10-25 09:00:00');
    $remaining = bulkTestSessionAt('2026-10-24 09:00:00');

    $preview = bulkPreviewFor([$target->id, $alsoLeaving->id]);

    $afterwards = bulkPreviewFor([$remaining->id]);

    bulkDeleteWithToken([$target->id, $alsoLeaving->id], $preview['token'])->assertOk();

    // Looking at what is left gives the same numbers the warning promised.
    $remainingPreview = bulkPreviewFor([$remaining->id]);

    expect($remainingPreview['progress']['before'])->toBe($preview['progress']['after'])
        ->and($afterwards['progress']['before'])->toBe($preview['progress']['before']);
});

it('keeps the bulk warning exact for a large library without a query per session', function (): void {
    $topic = Topic::factory()->create();

    PracticeSession::factory()->count(500)->finished()->for($topic)->create();

    $target = bulkTestSessionAt('2026-10-26 09:00:00', words: 2);

    DB::flushQueryLog();
    DB::enableQueryLog();

    bulkPreviewFor([$target->id]);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThan(15);
});

it('rejects an empty or duplicated selection', function (): void {
    $session = PracticeSession::factory()->finished()->create();

    $this->postJson(route('sessions.bulkArchive'), ['ids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');

    $this->postJson(route('sessions.bulkArchive'), ['ids' => [$session->id, $session->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids.1');
});

it('reads the same filters for selection as the Library list', function (): void {
    $topic = Topic::factory()->create();

    PracticeSession::factory()->count(3)->finished()->for($topic)->create();

    $filters = LibraryFilters::fromArray(['topics' => (string) $topic->id]);

    expect(app(LibrarySessions::class)->selection($filters)['total'])->toBe(3);
});
