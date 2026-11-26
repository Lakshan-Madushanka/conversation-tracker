<?php

declare(strict_types=1);

use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Records a complete conversation on the session at the given moment: a
 * question with an answer and a natural version, plus the moment it was
 * completed. Its position follows the questions already on the session.
 *
 * @param  list<FeedbackType>  $types  extra entries, so the counts are easy to predict
 */
function completeProgressQuestion(
    PracticeSession $session,
    string $completedAt,
    array $types = [],
): PracticeQuestion {
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
        'completed_at' => Carbon::parse($completedAt),
    ]);

    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::NaturalVersion,
        'content_html' => '<p>Here is a more natural version.</p>',
        'content_text' => 'Here is a more natural version.',
    ]);

    foreach ($types as $type) {
        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => $type,
            'content_html' => '',
            'content_text' => 'entry '.fake()->unique()->numberBetween(1, 999999),
        ]);
    }

    return $question;
}

/**
 * A finished session around the given moment, holding one complete
 * conversation at that moment and one non-empty entry of each requested type.
 *
 * @param  list<FeedbackType>  $types
 */
function progressSessionAt(
    string $finishedAt,
    array $types = [],
    bool $archived = false,
): PracticeSession {
    $factory = $archived
        ? PracticeSession::factory()->archived()
        : PracticeSession::factory()->finished();

    $session = $factory->create([
        'created_at' => Carbon::parse($finishedAt),
        'finished_at' => Carbon::parse($finishedAt),
        'updated_at' => Carbon::parse($finishedAt),
    ]);

    completeProgressQuestion($session, $finishedAt, $types);

    return $session;
}

/**
 * A session the learner has not finished. It holds a complete conversation
 * only when a moment is given.
 *
 * @param  list<FeedbackType>  $types
 */
function unfinishedProgressSession(?string $completedAt = null, array $types = []): PracticeSession
{
    $session = PracticeSession::factory()->create();

    if ($completedAt !== null) {
        completeProgressQuestion($session, $completedAt, $types);
    }

    return $session;
}

/**
 * The editor payload for one answered question with an optional natural
 * version.
 *
 * @return array{topic: string, questions: list<array{question_html: string, answer_html: string, feedback: array{natural_version: string}}>}
 */
function progressConversationPayload(string $answer, string $naturalVersion = ''): array
{
    return [
        'topic' => 'Travel',
        'questions' => [[
            'question_html' => '<p>Where did you go?</p>',
            'answer_html' => $answer,
            'feedback' => [
                'natural_version' => $naturalVersion,
            ],
        ]],
    ];
}

it('shows real session-derived progress, including archived sessions', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    progressSessionAt('2026-10-24 09:00:00', [FeedbackType::Vocabulary, FeedbackType::GrammarNote], archived: true);
    progressSessionAt('2026-10-25 09:00:00', [FeedbackType::Phrase]);
    progressSessionAt('2026-10-26 09:00:00', [FeedbackType::Vocabulary]);

    $this->get(route('progress.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('progress/Index')
            ->where('progress.sessions', 3)
            ->where('progress.words', 2)
            ->where('progress.phrases', 1)
            ->where('progress.grammar_notes', 1)
            ->where('progress.practice_days', 3)
            ->where('progress.current_streak', 3));
});

it('counts several completed conversations on one day as one practice day', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    progressSessionAt('2026-10-26 08:00:00');
    progressSessionAt('2026-10-26 18:00:00');

    $this->get(route('progress.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 2)
            ->where('progress.practice_days', 1)
            ->where('progress.current_streak', 1));
});

it('counts each day a single session was practised on', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $session = unfinishedProgressSession('2026-10-24 09:00:00');
    completeProgressQuestion($session, '2026-10-25 09:00:00');
    completeProgressQuestion($session, '2026-10-26 09:00:00');

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 0)
            ->where('progress.practice_days', 3)
            ->where('progress.current_streak', 3));
});

it('counts an unfinished session day without counting the session or its totals', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    unfinishedProgressSession('2026-10-26 09:00:00', [FeedbackType::Vocabulary]);

    $this->get(route('progress.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 0)
            ->where('progress.words', 0)
            ->where('progress.practice_days', 1)
            ->where('progress.current_streak', 1));
});

it('does not count a day just because a session was finished', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $session = PracticeSession::factory()->finished()->create([
        'created_at' => Carbon::parse('2026-10-25 09:00:00'),
        'finished_at' => Carbon::parse('2026-10-26 09:00:00'),
    ]);

    // An answer on its own is not a complete conversation.
    PracticeQuestion::factory()->for($session)->create();

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 1)
            ->where('progress.words', 0)
            ->where('progress.practice_days', 0)
            ->where('progress.current_streak', 0));
});

it('keeps a streak alive through yesterday and ends it after a missed day', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    progressSessionAt('2026-10-24 09:00:00');
    progressSessionAt('2026-10-25 09:00:00');

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.current_streak', 2));

    Carbon::setTestNow('2026-10-27 12:00:00');

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.practice_days', 2)
            ->where('progress.current_streak', 0));
});

it('uses the learner timezone to decide the practice day', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    progressSessionAt('2026-10-25 12:00:00');
    progressSessionAt('2026-10-25 23:30:00');

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.practice_days', 1)
            ->where('progress.current_streak', 1));

    $this->get(route('progress.index', ['tz' => 'Asia/Tokyo']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.practice_days', 2)
            ->where('progress.current_streak', 2));
});

it('leaves an unfinished session without a complete conversation out of every measure', function (): void {
    $session = PracticeSession::factory()->create();

    $question = PracticeQuestion::factory()->for($session)->create();
    PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::Vocabulary,
        'content_html' => '',
        'content_text' => 'soup',
    ]);

    $this->get(route('progress.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 0)
            ->where('progress.words', 0)
            ->where('progress.phrases', 0)
            ->where('progress.grammar_notes', 0)
            ->where('progress.practice_days', 0)
            ->where('progress.current_streak', 0));
});

it('keeps session-derived measures at zero for a notebook-only learner', function (): void {
    $notebook = Notebook::factory()->create();

    PracticeFeedbackEntry::factory()->create([
        'practice_question_id' => null,
        'notebook_id' => $notebook->id,
        'type' => FeedbackType::Vocabulary,
        'content_html' => '',
        'content_text' => 'bank',
        'correction_text' => 'river edge',
    ]);

    $this->get(route('progress.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('progress/Index')
            ->where('progress.sessions', 0)
            ->where('progress.words', 0)
            ->where('progress.practice_days', 0)
            ->where('progress.current_streak', 0));
});

it('does not change progress when a finished session is archived or restored', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $session = progressSessionAt('2026-10-26 09:00:00', [FeedbackType::Vocabulary, FeedbackType::Phrase]);

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 1)
            ->where('progress.words', 1)
            ->where('progress.phrases', 1)
            ->where('progress.current_streak', 1));

    $session->forceFill(['archived_at' => Carbon::now()])->save();

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 1)
            ->where('progress.words', 1)
            ->where('progress.phrases', 1)
            ->where('progress.current_streak', 1));

    $session->forceFill(['archived_at' => null])->save();

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 1)
            ->where('progress.words', 1)
            ->where('progress.phrases', 1)
            ->where('progress.current_streak', 1));
});

it('reflects a finished deletion and ignores an unfinished session with no conversation', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $leaving = progressSessionAt('2026-10-26 09:00:00', [FeedbackType::Vocabulary]);
    progressSessionAt('2026-10-25 09:00:00', [FeedbackType::Phrase]);

    $leaving->delete();

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 1)
            ->where('progress.words', 0)
            ->where('progress.phrases', 1)
            ->where('progress.practice_days', 1)
            ->where('progress.current_streak', 1));

    $unfinished = unfinishedProgressSession();

    $unfinished->delete();

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 1)
            ->where('progress.words', 0)
            ->where('progress.phrases', 1)
            ->where('progress.practice_days', 1)
            ->where('progress.current_streak', 1));
});

it('removes a practice day when an unfinished session holding a conversation is deleted', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $leaving = unfinishedProgressSession('2026-10-26 09:00:00');
    progressSessionAt('2026-10-25 09:00:00');

    $leaving->delete();

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 1)
            ->where('progress.practice_days', 1)
            ->where('progress.current_streak', 1));
});

it('does not change progress when a collection item is marked learned', function (): void {
    $session = progressSessionAt('2026-10-26 09:00:00');

    $session->questions()->firstOrFail()->feedbackEntries()->create([
        'type' => FeedbackType::Vocabulary,
        'content_html' => '',
        'content_text' => 'bank',
    ]);

    CollectionItemMark::query()->create([
        'kind' => FeedbackType::Vocabulary,
        'item_key' => CollectionItemMark::keyFor(FeedbackType::Vocabulary, 'bank'),
        'starred' => true,
        'status' => CollectionItemStatus::Learned,
        'notes_html' => '',
        'notes_text' => '',
    ]);

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.sessions', 1)
            ->where('progress.words', 1));
});

it('shows the same streak and practice days on Home and Progress', function (): void {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.current_streak', 0)
            ->where('progress.practice_days', 0));

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.current_streak', 0)
            ->where('progress.practice_days', 0));

    progressSessionAt('2026-10-25 09:00:00');
    progressSessionAt('2026-10-26 09:00:00');

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.current_streak', 2)
            ->where('progress.practice_days', 2));

    $this->get(route('progress.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('progress.current_streak', 2)
            ->where('progress.practice_days', 2));
});

it('performs no writes when Progress is opened', function (): void {
    progressSessionAt('2026-10-26 09:00:00', [FeedbackType::Vocabulary]);

    DB::enableQueryLog();

    $this->get(route('progress.index'))->assertOk();

    $writes = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => ! str_starts_with(strtolower(ltrim((string) $query['query'])), 'select'));

    expect($writes)->toBeEmpty();

    DB::disableQueryLog();
});

describe('recording when a conversation was completed', function (): void {
    it('stamps the day the answer and its natural version are saved together', function (): void {
        Carbon::setTestNow('2026-10-24 15:00:00');

        $session = PracticeSession::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('sessions.update', $session), progressConversationPayload(
                '<p>I went to the market.</p>',
                '<p>I ended up at the market.</p>',
            ))
            ->assertRedirect(route('sessions.edit', $session));

        expect($session->questions()->firstOrFail()->completed_at?->toDateTimeString())
            ->toBe('2026-10-24 15:00:00');

        $this->get(route('progress.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('progress.sessions', 0)
                ->where('progress.practice_days', 1)
                ->where('progress.current_streak', 1));
    });

    it('keeps the first completion moment when the question is edited later', function (): void {
        Carbon::setTestNow('2026-10-24 15:00:00');

        $session = PracticeSession::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('sessions.update', $session), progressConversationPayload(
                '<p>I went to the market.</p>',
                '<p>I ended up at the market.</p>',
            ))
            ->assertRedirect();

        Carbon::setTestNow('2026-10-26 09:00:00');

        $this->actingAs($user)
            ->put(route('sessions.update', $session), progressConversationPayload(
                '<p>I went to the market with my brother.</p>',
                '<p>I ended up at the market with my brother.</p>',
            ))
            ->assertRedirect();

        expect($session->questions()->firstOrFail()->completed_at?->toDateTimeString())
            ->toBe('2026-10-24 15:00:00');

        $this->get(route('progress.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('progress.practice_days', 1)
                ->where('progress.current_streak', 0));
    });

    it('does not stamp a question that has an answer but no natural version', function (): void {
        Carbon::setTestNow('2026-10-24 15:00:00');

        $session = PracticeSession::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('sessions.update', $session), progressConversationPayload('<p>I went to the market.</p>'))
            ->assertRedirect();

        expect($session->questions()->firstOrFail()->completed_at)->toBeNull();

        $this->get(route('progress.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('progress.practice_days', 0)
                ->where('progress.current_streak', 0));
    });

    it('does not stamp a question that has a natural version but no answer', function (): void {
        Carbon::setTestNow('2026-10-24 15:00:00');

        $session = PracticeSession::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('sessions.update', $session), progressConversationPayload('', '<p>I ended up at the market.</p>'))
            ->assertRedirect();

        expect($session->questions()->firstOrFail()->completed_at)->toBeNull();

        $this->get(route('progress.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('progress.practice_days', 0)
                ->where('progress.current_streak', 0));
    });

    it('stamps the day the missing half arrives', function (): void {
        Carbon::setTestNow('2026-10-24 15:00:00');

        $session = PracticeSession::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('sessions.update', $session), progressConversationPayload('<p>I went to the market.</p>'))
            ->assertRedirect();

        Carbon::setTestNow('2026-10-26 09:00:00');

        $this->actingAs($user)
            ->put(route('sessions.update', $session), progressConversationPayload(
                '<p>I went to the market.</p>',
                '<p>I ended up at the market.</p>',
            ))
            ->assertRedirect();

        expect($session->questions()->firstOrFail()->completed_at?->toDateTimeString())
            ->toBe('2026-10-26 09:00:00');

        $this->get(route('progress.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('progress.practice_days', 1)
                ->where('progress.current_streak', 1));
    });
});
