<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session at a known moment, with its own topic.
 *
 * @param  array<string, mixed>  $attributes
 */
function collectionSession(
    CarbonImmutable $at,
    ?Topic $topic = null,
    array $attributes = [],
): PracticeSession {
    $topic ??= Topic::factory()->create();

    return PracticeSession::factory()
        ->finished()
        ->for($topic)
        ->create(array_merge([
            'created_at' => $at,
            'updated_at' => $at,
            'finished_at' => $at,
            'last_changed_at' => $at,
        ], $attributes));
}

/**
 * One vocabulary row in a question.
 *
 * @param  array<string, mixed>  $attributes
 */
function vocabularyRow(
    PracticeQuestion $question,
    string $word,
    array $attributes = [],
): PracticeFeedbackEntry {
    return PracticeFeedbackEntry::factory()->for($question)->create(array_merge([
        'type' => FeedbackType::Vocabulary,
        'content_text' => $word,
        'correction_text' => '',
        'example' => '',
        'example_html' => [],
        'is_split' => true,
    ], $attributes));
}

it('gathers one item per word, ignoring capitals, accents and extra spaces', function (): void {
    $old = collectionSession(CarbonImmutable::now()->subDays(2));
    vocabularyRow(PracticeQuestion::factory()->for($old)->create(), '  Café  ', [
        'correction_text' => 'a place for coffee',
        'example' => 'We met at the café.',
    ]);

    $middle = collectionSession(CarbonImmutable::now()->subDay());
    vocabularyRow(PracticeQuestion::factory()->for($middle)->create(), 'CAFE', [
        'correction_text' => 'a coffee shop',
    ]);

    $newest = collectionSession(CarbonImmutable::now());
    vocabularyRow(PracticeQuestion::factory()->for($newest)->create(), 'cafe', [
        'example' => 'The cafe was busy.',
    ]);

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('tab', 'vocabulary')
            ->where('total', 1)
            ->count('items', 1)
            ->where('items.0.key', 'cafe')
            ->where('items.0.word', 'cafe')
            ->where('items.0.meaning', 'a coffee shop')
            ->where('items.0.example', 'The cafe was busy.')
            ->where('items.0.seen_count', 3)
            ->where('items.0.needs_attention', false));
});

it('keeps the newest spelling and the newest meaning and example that hold anything', function (): void {
    $newest = collectionSession(CarbonImmutable::now());
    vocabularyRow(PracticeQuestion::factory()->for($newest)->create(), 'RELISH');

    $middle = collectionSession(CarbonImmutable::now()->subDay());
    vocabularyRow(PracticeQuestion::factory()->for($middle)->create(), 'Relish', [
        'correction_text' => 'to enjoy',
        'example' => 'I relish a quiet evening.',
    ]);

    $oldest = collectionSession(CarbonImmutable::now()->subDays(2));
    vocabularyRow(PracticeQuestion::factory()->for($oldest)->create(), 'relish', [
        'correction_text' => 'to savour',
        'example' => 'She relishes the soup.',
    ]);

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('items.0.word', 'RELISH')
            ->where('items.0.meaning', 'to enjoy')
            ->where('items.0.example', 'I relish a quiet evening.')
            ->where('items.0.needs_attention', false));
});

it('asks for a meaning when no appearance has one', function (): void {
    $session = collectionSession(CarbonImmutable::now());
    vocabularyRow(PracticeQuestion::factory()->for($session)->create(), 'relish');

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('items.0.meaning', '')
            ->where('items.0.needs_attention', true));
});

it('counts a repeated row in the same box once but a different question separately', function (): void {
    $session = collectionSession(CarbonImmutable::now());
    $first = PracticeQuestion::factory()->for($session)->create(['position' => 1]);
    $second = PracticeQuestion::factory()->for($session)->create(['position' => 2]);

    vocabularyRow($first, 'soup', ['client_id' => 'soup-1']);
    vocabularyRow($first, 'Soup', ['client_id' => 'soup-2']);
    vocabularyRow($second, 'SOUP', ['client_id' => 'soup-3']);

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('items.0.seen_count', 2)
            ->count('items.0.appearances', 3));
});

it('leaves out blank words, unfinished sessions, archived sessions and other boxes', function (): void {
    $finished = collectionSession(CarbonImmutable::now());
    vocabularyRow(PracticeQuestion::factory()->for($finished)->create(), 'soup');

    $blank = collectionSession(CarbonImmutable::now());
    vocabularyRow(PracticeQuestion::factory()->for($blank)->create(), '   ');

    $draft = PracticeSession::factory()->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
    ]);
    vocabularyRow(PracticeQuestion::factory()->for($draft)->create(), 'draft word');

    $archived = collectionSession(CarbonImmutable::now()->subHour(), null, [
        'archived_at' => CarbonImmutable::now(),
    ]);
    vocabularyRow(PracticeQuestion::factory()->for($archived)->create(), 'archived word');

    $phrase = collectionSession(CarbonImmutable::now());
    PracticeFeedbackEntry::factory()->for(
        PracticeQuestion::factory()->for($phrase)->create(),
    )->create([
        'type' => FeedbackType::Phrase,
        'content_text' => 'catch up',
        'is_split' => true,
    ]);

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('total', 1)
            ->where('items.0.word', 'soup'));
});

it('orders newest session first and then alphabetically', function (): void {
    $old = collectionSession(CarbonImmutable::now()->subDay());
    vocabularyRow(PracticeQuestion::factory()->for($old)->create(), 'banana');

    $sameMoment = CarbonImmutable::now();
    $first = collectionSession($sameMoment);
    $second = collectionSession($sameMoment);
    vocabularyRow(PracticeQuestion::factory()->for($first)->create(), 'zebra');
    vocabularyRow(PracticeQuestion::factory()->for($second)->create(), 'apple');

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('items.0.word', 'apple')
            ->where('items.1.word', 'zebra')
            ->where('items.2.word', 'banana'));
});

it('loads the first 30 words and follows a stable cursor for the rest', function (): void {
    $base = CarbonImmutable::now();

    foreach (range(1, 35) as $index) {
        $session = collectionSession($base->subMinutes($index));
        vocabularyRow(
            PracticeQuestion::factory()->for($session)->create(),
            sprintf('word %02d', $index),
        );
    }

    $first = $this->getJson(route('collections.index'))->assertOk()->json();

    expect($first['items'])->toHaveCount(30)
        ->and($first['has_more'])->toBeTrue()
        ->and($first['total'])->toBe(35)
        ->and($first['next_cursor'])->toBeString();

    $second = $this->getJson(route('collections.index', [
        'cursor' => $first['next_cursor'],
    ]))->assertOk()->json();

    expect($second['items'])->toHaveCount(5)
        ->and($second['has_more'])->toBeFalse()
        ->and($second['next_cursor'])->toBeNull()
        ->and($second['total'])->toBe(35);

    $firstKeys = array_column($first['items'], 'key');
    $secondKeys = array_column($second['items'], 'key');

    expect(array_intersect($firstKeys, $secondKeys))->toBe([])
        ->and(array_merge($firstKeys, $secondKeys))->toHaveCount(35);
});

it('opens the word named in the address and says calmly when it is gone', function (): void {
    $session = collectionSession(CarbonImmutable::now());
    vocabularyRow(PracticeQuestion::factory()->for($session)->create(), 'Café');

    $this->get(route('collections.index', ['word' => 'cafe']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('open_word.key', 'cafe')
            ->where('open_word.word', 'Café')
            ->where('open_word_missing', false));

    $this->get(route('collections.index', ['word' => 'missing']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('open_word', null)
            ->where('open_word_missing', true));
});

it('falls back to vocabulary for a tab this page does not know', function (): void {
    $this->get(route('collections.index', ['tab' => 'banana']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('tab', 'vocabulary')
            ->where('open_word_missing', false));
});

it('marks only vocabulary as built and keeps the other tabs calm and empty', function (): void {
    $session = collectionSession(CarbonImmutable::now());
    vocabularyRow(PracticeQuestion::factory()->for($session)->create(), 'soup');

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->count('tabs', 4)
            ->where('tabs.0.key', 'vocabulary')
            ->where('tabs.0.label', 'Vocabulary')
            ->where('tabs.0.built', true)
            ->where('tabs.1.key', 'phrases')
            ->where('tabs.1.built', false)
            ->where('tabs.2.key', 'grammar')
            ->where('tabs.3.key', 'mistakes')
            ->where('tabs.3.label', 'My mistakes'));

    $this->get(route('collections.index', ['tab' => 'phrases']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('tab', 'phrases')
            ->where('total', 0)
            ->count('items', 0));
});

it('names the session, topic, question and row every appearance came from', function (): void {
    $topic = Topic::factory()->create(['name' => 'Ordering at a restaurant']);
    $session = collectionSession(CarbonImmutable::now(), $topic);

    $first = PracticeQuestion::factory()->for($session)->create(['position' => 1]);
    $second = PracticeQuestion::factory()->for($session)->create(['position' => 2]);

    vocabularyRow($first, 'relish', ['client_id' => 'relish-first']);
    vocabularyRow($first, 'relish', ['client_id' => 'relish-second']);
    vocabularyRow($second, 'relish', ['client_id' => 'relish-elsewhere']);

    $this->get(route('collections.index', ['word' => 'relish']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('open_word.appearances.0.session_id', $session->id)
            ->where('open_word.appearances.0.topic', 'Ordering at a restaurant')
            ->where('open_word.appearances.0.question_position', 2)
            ->where('open_word.appearances.0.entry_id', 'relish-elsewhere')
            ->where('open_word.appearances.0.entry_position', 0)
            ->where('open_word.appearances.1.question_position', 1)
            ->where('open_word.appearances.1.entry_id', 'relish-second')
            ->where('open_word.appearances.1.entry_position', 1));
});

it('says when there is nothing to gather yet and when there are no words yet', function (): void {
    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('has_finished_sessions', false)
            ->where('total', 0));

    $session = collectionSession(CarbonImmutable::now());

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('has_finished_sessions', true)
            ->where('total', 0)
            ->count('items', 0));
});

it('reflects finishing, editing, archiving, restoring and deleting straight away', function (): void {
    $draft = PracticeSession::factory()->create([
        'status' => PracticeSession::STATUS_UNFINISHED,
    ]);
    $row = vocabularyRow(PracticeQuestion::factory()->for($draft)->create(), 'relish');

    $this->get(route('collections.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('has_finished_sessions', false)
            ->where('total', 0));

    $draft->update([
        'status' => PracticeSession::STATUS_FINISHED,
        'finished_at' => CarbonImmutable::now(),
    ]);

    $this->get(route('collections.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('has_finished_sessions', true)
            ->where('total', 1)
            ->where('items.0.word', 'relish'));

    $row->update(['content_text' => 'savour']);

    $this->get(route('collections.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('total', 1)
            ->where('items.0.word', 'savour'));

    $draft->archived_at = CarbonImmutable::now();
    $draft->save();

    $this->get(route('collections.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('has_finished_sessions', false)
            ->where('total', 0));

    $draft->archived_at = null;
    $draft->save();

    $this->get(route('collections.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('total', 1)
            ->where('items.0.word', 'savour'));

    $draft->delete();

    $this->get(route('collections.index'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('has_finished_sessions', false)
            ->where('total', 0));
});
