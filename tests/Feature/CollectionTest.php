<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Support\NotePreview;
use App\Support\RowAttention;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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
 * One question in a finished session.
 *
 * @param  array<string, mixed>  $attributes
 */
function collectionQuestion(
    PracticeSession $session,
    int $position = 1,
    array $attributes = [],
): PracticeQuestion {
    return PracticeQuestion::factory()->for($session)->create(array_merge([
        'position' => $position,
    ], $attributes));
}

/**
 * One row of a table box (a word, a phrase or a mistake).
 *
 * @param  array<string, mixed>  $attributes
 */
function collectionRow(
    PracticeQuestion $question,
    string $subject,
    FeedbackType $type = FeedbackType::Vocabulary,
    array $attributes = [],
): PracticeFeedbackEntry {
    return PracticeFeedbackEntry::factory()->for($question)->create(array_merge([
        'type' => $type,
        'content_text' => $subject,
        'correction_text' => '',
        'example' => '',
        'example_html' => [],
        'is_split' => true,
    ], $attributes));
}

/**
 * One grammar note. The markup is stored beside its plain text, the way the
 * editor saves it.
 *
 * @param  array<string, mixed>  $attributes
 */
function collectionNote(
    PracticeQuestion $question,
    string $text,
    string $html = '',
    array $attributes = [],
): PracticeFeedbackEntry {
    return PracticeFeedbackEntry::factory()->for($question)->create(array_merge([
        'type' => FeedbackType::GrammarNote,
        'content_html' => $html,
        'content_text' => $text,
        'position' => 0,
    ], $attributes));
}

describe('vocabulary', function (): void {
    it('gathers one item per word, ignoring capitals, accents and extra spaces', function (): void {
        $old = collectionSession(CarbonImmutable::now()->subDays(2));
        collectionRow(collectionQuestion($old), '  Café  ', FeedbackType::Vocabulary, [
            'correction_text' => 'a place for coffee',
            'example' => 'We met at the café.',
        ]);

        $middle = collectionSession(CarbonImmutable::now()->subDay());
        collectionRow(collectionQuestion($middle), 'CAFE', FeedbackType::Vocabulary, [
            'correction_text' => 'a coffee shop',
        ]);

        $newest = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($newest), 'cafe', FeedbackType::Vocabulary, [
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
                ->where('items.0.title', 'cafe')
                ->where('items.0.meaning', 'a coffee shop')
                ->where('items.0.example', 'The cafe was busy.')
                ->where('items.0.example_html', '')
                ->where('items.0.seen_count', 3)
                ->where('items.0.needs_attention', false));
    });

    it('keeps the newest spelling and the newest meaning and example that hold anything', function (): void {
        $newest = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($newest), 'RELISH');

        $middle = collectionSession(CarbonImmutable::now()->subDay());
        collectionRow(collectionQuestion($middle), 'Relish', FeedbackType::Vocabulary, [
            'correction_text' => 'to enjoy',
            'example' => 'I relish a quiet evening.',
        ]);

        $oldest = collectionSession(CarbonImmutable::now()->subDays(2));
        collectionRow(collectionQuestion($oldest), 'relish', FeedbackType::Vocabulary, [
            'correction_text' => 'to savour',
            'example' => 'She relishes the soup.',
        ]);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.title', 'RELISH')
                ->where('items.0.meaning', 'to enjoy')
                ->where('items.0.example', 'I relish a quiet evening.')
                ->where('items.0.needs_attention', false));
    });

    it('asks for a meaning when no appearance has one', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($session), 'relish');

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.meaning', '')
                ->where('items.0.needs_attention', true)
                ->where('items.0.attention_reason', RowAttention::ADD_MEANING));
    });

    it('names a line that held more than one separator instead of asking for a meaning', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($session), 'see -> watch -> observe');

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.needs_attention', true)
                ->where('items.0.attention_reason', RowAttention::MORE_THAN_ONE));
    });

    it('stays calm once a split line was given a meaning', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($session), 'see -> watch -> observe', FeedbackType::Vocabulary, [
            'correction_text' => 'first look, then look',
        ]);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.needs_attention', false)
                ->where('items.0.attention_reason', null));
    });

    it('counts a repeated row in the same box once but a different question separately', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        $first = collectionQuestion($session, 1);
        $second = collectionQuestion($session, 2);

        collectionRow($first, 'soup', FeedbackType::Vocabulary, ['client_id' => 'soup-1']);
        collectionRow($first, 'Soup', FeedbackType::Vocabulary, ['client_id' => 'soup-2']);
        collectionRow($second, 'SOUP', FeedbackType::Vocabulary, ['client_id' => 'soup-3']);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.seen_count', 2)
                ->count('items.0.appearances', 3));
    });

    it('leaves out blank words, unfinished sessions, archived sessions and other boxes', function (): void {
        $finished = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($finished), 'soup');

        $blank = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($blank), '   ');

        $draft = PracticeSession::factory()->create([
            'status' => PracticeSession::STATUS_UNFINISHED,
        ]);
        collectionRow(collectionQuestion($draft), 'draft word');

        $archived = collectionSession(CarbonImmutable::now()->subHour(), null, [
            'archived_at' => CarbonImmutable::now(),
        ]);
        collectionRow(collectionQuestion($archived), 'archived word');

        $phrase = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($phrase), 'catch up', FeedbackType::Phrase);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.title', 'soup'));
    });

    it('orders newest session first and then alphabetically', function (): void {
        $old = collectionSession(CarbonImmutable::now()->subDay());
        collectionRow(collectionQuestion($old), 'banana');

        $sameMoment = CarbonImmutable::now();
        $first = collectionSession($sameMoment);
        $second = collectionSession($sameMoment);
        collectionRow(collectionQuestion($first), 'zebra');
        collectionRow(collectionQuestion($second), 'apple');

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.title', 'apple')
                ->where('items.1.title', 'zebra')
                ->where('items.2.title', 'banana'));
    });

    it('loads the first 30 words and follows a stable cursor for the rest', function (): void {
        $base = CarbonImmutable::now();

        foreach (range(1, 35) as $index) {
            $session = collectionSession($base->subMinutes($index));
            collectionRow(
                collectionQuestion($session),
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

    it('opens the item named in the address and says calmly when it is gone', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($session), 'Café');

        $this->get(route('collections.index', ['item' => 'cafe']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item.key', 'cafe')
                ->where('open_item.title', 'Café')
                ->where('open_item_missing', false));

        $this->get(route('collections.index', ['item' => 'missing']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item', null)
                ->where('open_item_missing', true));

        // The old address asked by word; nothing opens for it any more, and
        // the page stays calm rather than reporting a missing item.
        $this->get(route('collections.index', ['word' => 'cafe']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item', null)
                ->where('open_item_missing', false));
    });

    it('falls back to vocabulary for a tab this page does not know', function (): void {
        $this->get(route('collections.index', ['tab' => 'banana']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('tab', 'vocabulary')
                ->where('open_item_missing', false));
    });

    it('names the session, topic, question and row every appearance came from', function (): void {
        $topic = Topic::factory()->create(['name' => 'Ordering at a restaurant']);
        $session = collectionSession(CarbonImmutable::now(), $topic);

        $first = collectionQuestion($session, 1);
        $second = collectionQuestion($session, 2);

        collectionRow($first, 'relish', FeedbackType::Vocabulary, ['client_id' => 'relish-first']);
        collectionRow($first, 'relish', FeedbackType::Vocabulary, ['client_id' => 'relish-second']);
        collectionRow($second, 'relish', FeedbackType::Vocabulary, ['client_id' => 'relish-elsewhere']);

        $this->get(route('collections.index', ['item' => 'relish']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item.appearances.0.session_id', $session->id)
                ->where('open_item.appearances.0.topic', 'Ordering at a restaurant')
                ->where('open_item.appearances.0.question_position', 2)
                ->where('open_item.appearances.0.entry_id', 'relish-elsewhere')
                ->where('open_item.appearances.0.entry_position', 0)
                ->where('open_item.appearances.1.question_position', 1)
                ->where('open_item.appearances.1.entry_id', 'relish-second')
                ->where('open_item.appearances.1.entry_position', 1));
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
        $row = collectionRow(collectionQuestion($draft), 'relish');

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
                ->where('items.0.title', 'relish'));

        $row->update(['content_text' => 'savour']);

        $this->get(route('collections.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.title', 'savour'));

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
                ->where('items.0.title', 'savour'));

        $draft->delete();

        $this->get(route('collections.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('has_finished_sessions', false)
                ->where('total', 0));
    });
});

describe('tab counts', function (): void {
    it('counts each built collection on its tab and keeps My mistakes calm', function (): void {
        $sameMoment = CarbonImmutable::now();
        $wordSession = collectionSession($sameMoment);
        $words = collectionQuestion($wordSession);
        $wordsAgain = collectionQuestion($wordSession, 2);

        collectionRow($words, 'cafe', FeedbackType::Vocabulary, ['client_id' => 'cafe-1']);
        collectionRow($wordsAgain, 'CAFE', FeedbackType::Vocabulary, ['client_id' => 'cafe-2']);
        collectionRow($words, 'soup', FeedbackType::Vocabulary);
        collectionRow($words, 'catch up', FeedbackType::Phrase);
        collectionNote($words, 'The past tense is used here.');
        collectionNote($words, 'Never drop the article.', '', ['position' => 1]);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->count('tabs', 4)
                ->where('tabs.0.key', 'vocabulary')
                ->where('tabs.0.label', 'Vocabulary')
                ->where('tabs.0.built', true)
                ->where('tabs.0.count', 2)
                ->where('tabs.1.key', 'phrases')
                ->where('tabs.1.built', true)
                ->where('tabs.1.count', 1)
                ->where('tabs.2.key', 'grammar')
                ->where('tabs.2.label', 'Grammar notes')
                ->where('tabs.2.built', true)
                ->where('tabs.2.count', 2)
                ->where('tabs.3.key', 'mistakes')
                ->where('tabs.3.label', 'My mistakes')
                ->where('tabs.3.built', false)
                ->where('tabs.3.count', null));

        // The mistakes page still keeps its own calm state, whatever rows it
        // would one day gather.
        $this->get(route('collections.index', ['tab' => 'mistakes']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('tab', 'mistakes'));
    });

    it('counts a word once even when the same session wrote it repeatedly', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        $question = collectionQuestion($session);

        collectionRow($question, 'soup', FeedbackType::Vocabulary, ['client_id' => 'soup-1']);
        collectionRow($question, 'Soup', FeedbackType::Vocabulary, ['client_id' => 'soup-2']);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('tabs.0.count', 1));
    });
});

describe('phrases', function (): void {
    it('gathers phrases like words and stays calm when a meaning is missing', function (): void {
        $sameMoment = CarbonImmutable::now();
        $first = collectionSession($sameMoment);
        $second = collectionSession($sameMoment);

        collectionRow(collectionQuestion($first), '  CHASE  UP  ', FeedbackType::Phrase);
        collectionRow(collectionQuestion($second), 'chase up', FeedbackType::Phrase, [
            'correction_text' => 'to hurry to meet someone',
            'example' => 'I will chase him up.',
        ]);

        $this->get(route('collections.index', ['tab' => 'phrases']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('tab', 'phrases')
                ->where('total', 1)
                ->count('items', 1)
                ->where('items.0.key', 'chase up')
                ->where('items.0.title', 'chase up')
                ->where('items.0.meaning', 'to hurry to meet someone')
                ->where('items.0.example', 'I will chase him up.')
                ->where('items.0.seen_count', 2)
                ->where('items.0.needs_attention', false)
                ->where('items.0.attention_reason', null));
    });

    it('names a phrase line that could not be split and holds nothing else', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($session), 'catch -> run after -> chase up', FeedbackType::Phrase);

        $this->get(route('collections.index', ['tab' => 'phrases']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.title', 'catch -> run after -> chase up')
                ->where('items.0.needs_attention', true)
                ->where('items.0.attention_reason', RowAttention::MORE_THAN_ONE));
    });

    it('keeps a phrase with an example calm, whatever it could not be split', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($session), 'catch -> run after -> chase up', FeedbackType::Phrase, [
            'example' => 'I will chase him up.',
        ]);

        $this->get(route('collections.index', ['tab' => 'phrases']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.needs_attention', false));
    });

    it('cleans a formatted example down to bold and italic words', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        collectionRow(collectionQuestion($session), 'chase up', FeedbackType::Phrase, [
            'example' => 'I caught him up.',
            'example_html' => [[
                'id' => 'example-1',
                'text' => 'I caught him up.',
                'html' => 'I <script>alert(1)</script><strong>caught</strong> him <em>up</em>.',
            ]],
        ]);

        $this->get(route('collections.index', ['tab' => 'phrases', 'item' => 'chase up']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item.example', 'I caught him up.')
                ->where('open_item.example_html', 'I <strong>caught</strong> him <em>up</em>.')
                ->where('open_item.needs_attention', false));
    });
});

describe('grammar notes', function (): void {
    it('keeps two identical notes as their own items, never merged', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        $question = collectionQuestion($session);

        $first = collectionNote($question, 'Use the past tense here.', '', ['position' => 0]);
        $second = collectionNote($question, 'Use the past tense here.', '', ['position' => 1]);

        $response = $this->getJson(route('collections.index', ['tab' => 'grammar']))
            ->assertOk();

        // Each note answers by its own identity, not by what it says.
        expect($response->json('total'))->toBe(2)
            ->and(count($response->json('items')))->toBe(2)
            ->and($response->json('items.0.key'))->toBe('note-'.$first->id)
            ->and($response->json('items.1.key'))->toBe('note-'.$second->id)
            ->and($response->json('items.0.preview'))->toBe('Use the past tense here.')
            ->and($response->json('items.1.preview'))->toBe('Use the past tense here.');
    });

    it('shows a short plain preview in the list and the full note, cleaned, in the panel', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        $question = collectionQuestion($session);

        $long = trim(str_repeat('Remember the about-to-be past tense rule. ', 10));
        $note = collectionNote($question, $long, '<p>Remember the <strong>past tense</strong>'
            .'</p><script>alert(1)</script><ul><li>Rule one</li></ul>');

        $this->get(route('collections.index', ['tab' => 'grammar']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->count('items', 1)
                ->where('items.0.key', 'note-'.$note->id));

        // The list keeps only plain words, cut at a word boundary.
        $list = $this->getJson(route('collections.index', ['tab' => 'grammar']))
            ->json('items.0.preview');

        expect($list)
            ->not->toContain('<')
            ->not->toContain('alert')
            ->toStartWith('Remember the about-to-be past tense rule.')
            ->toEndWith('…')
            ->and(mb_strlen($list))->toBeLessThanOrEqual(NotePreview::LENGTH + 1)
            ->and(str_starts_with($long, rtrim(mb_substr($list, 0, -1))))->toBeTrue();

        // The panel keeps the full note, reduced again to the safe formatting
        // inside the page props.
        $this->get(route(
            'collections.index',
            ['tab' => 'grammar', 'item' => 'note-'.$note->id],
        ))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item.key', 'note-'.$note->id)
                ->where('open_item.text', $long)
                ->where('open_item.session_id', $session->id)
                ->where('open_item.html', fn (string $html): bool => str_contains($html, '<p>')
                    && str_contains($html, '<strong>past tense</strong>')
                    && str_contains($html, '<ul><li>Rule one</li></ul>')
                    && ! str_contains($html, 'alert')));
    });

    it('names the session, topic, question and row one note came from', function (): void {
        $topic = Topic::factory()->create(['name' => 'Tenses']);
        $session = collectionSession(CarbonImmutable::now(), $topic);

        $first = collectionQuestion($session, 1);
        $second = collectionQuestion($session, 2);

        $note = collectionNote($first, 'First note', '', [
            'position' => 1,
            'client_id' => 'note-a',
        ]);
        collectionNote($first, 'First note, one row earlier', '', ['position' => 0]);
        collectionNote($second, 'Second note', '', ['position' => 0, 'client_id' => 'note-b']);

        $this->get(route('collections.index', ['tab' => 'grammar', 'item' => 'note-'.$note->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item.session_id', $session->id)
                ->where('open_item.topic', 'Tenses')
                ->where('open_item.question_position', 1)
                ->where('open_item.entry_id', 'note-a')
                ->where('open_item.entry_position', 1)
                ->where('open_item.session_date', $session->created_at?->toIso8601String()));
    });

    it('orders newest session first, then by question and note position, on a stable cursor', function (): void {
        $sameMoment = CarbonImmutable::now();
        $newest = collectionSession($sameMoment);
        $questionOne = collectionQuestion($newest, 1);
        $questionTwo = collectionQuestion($newest, 2);

        $older = collectionSession($sameMoment->subMinutes(5));
        $oldQuestion = collectionQuestion($older, 1);

        $order = [
            'newest-second-question' => collectionNote($questionTwo, 'second question', '', ['position' => 0]),
            'newest-first-row-3' => collectionNote($questionOne, 'first question, third row', '', ['position' => 2]),
            'newest-first-row-2' => collectionNote($questionOne, 'first question, second row', '', ['position' => 1]),
            'newest-first-row-1' => collectionNote($questionOne, 'first question, first row', '', ['position' => 0]),
            'older' => collectionNote($oldQuestion, 'an older session'),
        ];

        $response = $this->getJson(route('collections.index', ['tab' => 'grammar']))
            ->assertOk();

        expect($response->json('total'))->toBe(5);

        $keys = array_column($response->json('items'), 'key');

        // Question order comes first, then the place of the row in the box.
        expect($keys)->toBe([
            'note-'.$order['newest-first-row-1']->id,
            'note-'.$order['newest-first-row-2']->id,
            'note-'.$order['newest-first-row-3']->id,
            'note-'.$order['newest-second-question']->id,
            'note-'.$order['older']->id,
        ]);
    });

    it('pages grammar notes without repeating or skipping one', function (): void {
        $session = collectionSession(CarbonImmutable::now());
        $question = collectionQuestion($session);

        foreach (range(1, 35) as $index) {
            collectionNote(
                $question,
                sprintf('Note number %02d about the rule.', $index),
                '',
                ['position' => $index - 1],
            );
        }

        $first = $this->getJson(route('collections.index', ['tab' => 'grammar']))->assertOk()->json();

        expect($first['items'])->toHaveCount(30)
            ->and($first['has_more'])->toBeTrue()
            ->and($first['total'])->toBe(35)
            ->and($first['next_cursor'])->toBeString();

        $second = $this->getJson(route('collections.index', [
            'tab' => 'grammar',
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

    it('says calmly when a note in the address is gone', function (): void {
        $this->get(route('collections.index', ['tab' => 'grammar', 'item' => 'note-9999']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item', null)
                ->where('open_item_missing', true));
    });
});

describe('live queries', function (): void {
    it('keeps the number of queries flat as the collections grow', function (): void {
        $base = CarbonImmutable::now();

        $startSession = collectionSession($base->subMinutes(1));
        $startQuestion = collectionQuestion($startSession);
        collectionRow($startQuestion, 'relish');
        collectionNote($startQuestion, 'The first rule.');

        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->getJson(route('collections.index', ['tab' => 'vocabulary']))->assertOk();
            $this->getJson(route('collections.index', ['tab' => 'grammar']))->assertOk();
            $this->get(route('collections.index'))->assertOk();

            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $small = $measure();

        foreach (range(1, 40) as $index) {
            $session = collectionSession($base->subMinutes(100 + $index));
            $question = collectionQuestion($session);
            collectionRow($question, sprintf('new word %02d', $index));
            collectionRow($question, sprintf('new phrase %02d', $index), FeedbackType::Phrase);
            collectionNote($question, sprintf('New rule number %02d.', $index));
        }

        $large = $measure();

        expect($large)->toBe($small);
    });
});
