<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Adds one non-empty entry of the chosen box to a session, creating its
 * question once. Counts are always worked out from stored content, so the
 * tests build the content a learner would have saved.
 */
function addDeleteTestEntry(
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

function deleteTestSessionAt(string $finishedAt, int $words = 1): PracticeSession
{
    $session = PracticeSession::factory()->finished()->create([
        'finished_at' => Carbon::parse($finishedAt),
        'created_at' => Carbon::parse($finishedAt),
        'updated_at' => Carbon::parse($finishedAt),
    ]);

    if ($words > 0) {
        addDeleteTestEntry($session, FeedbackType::Vocabulary, 'soup');
    }

    return $session;
}

function deletePreviewFor(PracticeSession $session, string $timezone = 'UTC'): array
{
    return test()->getJson(route('sessions.deletePreview', [
        'session' => $session,
        'tz' => $timezone,
    ]))->assertOk()->json();
}

function deleteWithToken(int $sessionId, string $token, string $timezone = 'UTC')
{
    return test()->deleteJson(route('sessions.deletePermanently', [
        'sessionId' => $sessionId,
    ]), [
        'token' => $token,
        'tz' => $timezone,
    ]);
}

it('previews exactly what a finished deletion removes and changes', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $target = PracticeSession::factory()->finished()->create([
        'finished_at' => Carbon::parse('2026-10-26 09:00:00'),
        'created_at' => Carbon::parse('2026-10-26 09:00:00'),
    ]);

    addDeleteTestEntry($target, FeedbackType::Vocabulary, 'soup');
    addDeleteTestEntry($target, FeedbackType::Vocabulary, 'napkin');
    addDeleteTestEntry($target, FeedbackType::Phrase, 'Could I have');
    addDeleteTestEntry($target, FeedbackType::Mistake, 'I go');
    addDeleteTestEntry($target, FeedbackType::GrammarNote, 'Use the past tense');

    deleteTestSessionAt('2026-10-25 09:00:00');
    deleteTestSessionAt('2026-10-24 09:00:00');

    $preview = deletePreviewFor($target);

    expect($preview['session']['id'])->toBe($target->id)
        ->and($preview['session']['topic'])->toBe($target->topic->name)
        ->and($preview['session']['finished'])->toBeTrue()
        ->and($preview['session']['archived'])->toBeFalse()
        ->and($preview['counts'])->toBe([
            'questions' => 1,
            'words' => 2,
            'phrases' => 1,
            'mistakes' => 1,
            'grammar_notes' => 1,
        ])
        ->and($preview['progress']['before'])->toBe([
            'words' => 4,
            'phrases' => 1,
            'mistakes' => 1,
            'grammar_notes' => 1,
            'practice_days' => 3,
            'current_streak' => 3,
        ])
        ->and($preview['progress']['after'])->toBe([
            'words' => 2,
            'phrases' => 0,
            'mistakes' => 0,
            'grammar_notes' => 0,
            'practice_days' => 2,
            // Today's only session is the one leaving; yesterday keeps it alive.
            'current_streak' => 2,
        ])
        ->and($preview['token'])->toBeString()->toHaveLength(64);
});

it('previews an unfinished deletion as leaving progress untouched', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    deleteTestSessionAt('2026-10-25 09:00:00');
    deleteTestSessionAt('2026-10-24 09:00:00');

    $unfinished = PracticeSession::factory()->create();
    addDeleteTestEntry($unfinished, FeedbackType::Vocabulary, 'soup');

    $preview = deletePreviewFor($unfinished);

    expect($preview['session']['finished'])->toBeFalse()
        ->and($preview['counts']['words'])->toBe(1)
        ->and($preview['progress']['before'])->toBe($preview['progress']['after']);
});

it('deletes the session and every row inside it without leaving orphans', function (): void {
    $target = deleteTestSessionAt('2026-10-26 09:00:00');
    $question = $target->questions()->firstOrFail();
    addDeleteTestEntry($target, FeedbackType::Phrase, 'Could I have');

    $other = deleteTestSessionAt('2026-10-25 09:00:00');

    $preview = deletePreviewFor($target);

    deleteWithToken($target->id, $preview['token'])
        ->assertOk()
        ->assertJsonPath('deleted', true)
        ->assertJsonPath('already_deleted', false)
        ->assertJsonPath('session.id', $target->id)
        ->assertJsonPath('counts.words', 1)
        ->assertJsonPath('counts.phrases', 1);

    expect(PracticeSession::query()->whereKey($target->id)->exists())->toBeFalse()
        ->and(PracticeQuestion::query()->where('practice_session_id', $target->id)->exists())->toBeFalse()
        ->and(PracticeFeedbackEntry::query()->where('practice_question_id', $question->id)->exists())->toBeFalse()
        ->and(PracticeSession::query()->whereKey($other->id)->exists())->toBeTrue()
        ->and(PracticeQuestion::query()->where('practice_session_id', $other->id)->count())->toBe(1);
});

it('treats a repeated confirmation as success and deletes only once', function (): void {
    $target = deleteTestSessionAt('2026-10-26 09:00:00');
    $preview = deletePreviewFor($target);

    deleteWithToken($target->id, $preview['token'])->assertOk();

    deleteWithToken($target->id, $preview['token'])
        ->assertOk()
        ->assertJsonPath('deleted', true)
        ->assertJsonPath('already_deleted', true);

    expect(PracticeSession::query()->whereKey($target->id)->exists())->toBeFalse();
});

it('refuses a stale confirmation and returns the fresh numbers', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $target = deleteTestSessionAt('2026-10-26 09:00:00');
    deleteTestSessionAt('2026-10-25 09:00:00');

    $preview = deletePreviewFor($target);

    // The session gains a word after the warning was opened.
    addDeleteTestEntry($target, FeedbackType::Vocabulary, 'napkin');

    deleteWithToken($target->id, $preview['token'])
        ->assertStatus(409)
        ->assertJsonPath('stale', true)
        ->assertJsonPath('preview.counts.words', 2)
        ->assertJsonPath('preview.progress.before.words', 3)
        ->assertJsonPath('preview.progress.after.words', 1);

    expect(PracticeSession::query()->whereKey($target->id)->exists())->toBeTrue();

    $fresh = test()->deleteJson(route('sessions.deletePermanently', [
        'sessionId' => $target->id,
    ]), [
        'token' => $preview['token'],
        'tz' => 'UTC',
    ])->assertStatus(409)->json('preview');

    deleteWithToken($target->id, $fresh['token'])
        ->assertOk()
        ->assertJsonPath('deleted', true);

    expect(PracticeSession::query()->whereKey($target->id)->exists())->toBeFalse();
});

it('never deletes a different session with a valid token', function (): void {
    $first = deleteTestSessionAt('2026-10-26 09:00:00');
    $second = deleteTestSessionAt('2026-10-25 09:00:00');

    $preview = deletePreviewFor($first);

    deleteWithToken($second->id, $preview['token'])->assertStatus(409);

    expect(PracticeSession::query()->whereKey($first->id)->exists())->toBeTrue()
        ->and(PracticeSession::query()->whereKey($second->id)->exists())->toBeTrue();
});

it('leaves progress exactly where the warning said after a finished deletion', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $target = deleteTestSessionAt('2026-10-26 09:00:00', words: 2);
    addDeleteTestEntry($target, FeedbackType::Mistake, 'I go');

    $remaining = deleteTestSessionAt('2026-10-25 09:00:00');
    deleteTestSessionAt('2026-10-24 09:00:00');

    $preview = deletePreviewFor($target);

    deleteWithToken($target->id, $preview['token'])->assertOk();

    // Looking at what is left gives the same numbers the warning promised.
    $afterwards = deletePreviewFor($remaining);

    expect($afterwards['progress']['before'])->toBe($preview['progress']['after']);
});

it('removes an unfinished or finished session from the Library, topics, search and Home', function (): void {
    $topic = Topic::factory()->create(['name' => 'Only topic']);

    $unfinished = PracticeSession::factory()->for($topic)->create();
    addDeleteTestEntry($unfinished, FeedbackType::Vocabulary, 'soup');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('unfinishedSession.id', $unfinished->id));

    $this->getJson(route('sessions.index', ['q' => 'soup']))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $preview = deletePreviewFor($unfinished);
    deleteWithToken($unfinished->id, $preview['token'])->assertOk();

    $library = $this->getJson(route('sessions.index'))->assertOk()->json();

    expect($library['sessions'])->toBe([])
        ->and($library['total'])->toBe(0)
        ->and($library['topics'])->toBe([]);

    $this->getJson(route('sessions.index', ['q' => 'soup']))
        ->assertOk()
        ->assertJsonPath('total', 0);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('unfinishedSession', null));
});

it('deletes an archived session and lowers the archived total', function (): void {
    $archived = PracticeSession::factory()->archived()->create([
        'created_at' => Carbon::parse('2026-10-26 09:00:00'),
        'finished_at' => Carbon::parse('2026-10-26 09:00:00'),
    ]);
    addDeleteTestEntry($archived, FeedbackType::Vocabulary, 'soup');

    $preview = deletePreviewFor($archived);

    expect($preview['session']['archived'])->toBeTrue()
        ->and($preview['progress']['before']['words'])->toBe(1)
        ->and($preview['progress']['after']['words'])->toBe(0);

    deleteWithToken($archived->id, $preview['token'])->assertOk();

    $this->getJson(route('sessions.index', ['view' => 'archived']))
        ->assertOk()
        ->assertJsonPath('total', 0)
        ->assertJsonPath('archived_total', 0);
});

it('keeps the warning numbers exact for a large library without a query per session', function (): void {
    $topic = Topic::factory()->create();

    PracticeSession::factory()->count(500)->finished()->for($topic)->create();

    $target = deleteTestSessionAt('2026-10-26 09:00:00');
    addDeleteTestEntry($target, FeedbackType::Vocabulary, 'napkin');

    DB::flushQueryLog();
    DB::enableQueryLog();

    deletePreviewFor($target);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThan(15);
});
