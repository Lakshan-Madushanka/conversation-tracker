<?php

declare(strict_types=1);

use App\Actions\Collections\SaveItemMark;
use App\Actions\Collections\SaveItemNotes;
use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;
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
function browseSession(
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
 * One row of a table box, in its own question.
 *
 * @param  array<string, mixed>  $attributes
 */
function browseRow(
    PracticeSession $session,
    string $subject,
    FeedbackType $type = FeedbackType::Vocabulary,
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
function browseNote(PracticeSession $session, string $text, string $html = ''): PracticeFeedbackEntry
{
    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => $session->questions()->count() + 1,
    ]);

    return PracticeFeedbackEntry::factory()->for($question)->create([
        'type' => FeedbackType::GrammarNote,
        'content_html' => $html,
        'content_text' => $text,
        'position' => 0,
    ]);
}

/** Save a star and status the way the screen does. */
function browseMark(
    FeedbackType $kind,
    string $itemKey,
    ?bool $starred = null,
    ?CollectionItemStatus $status = null,
): CollectionItemMark {
    return app(SaveItemMark::class)->handle($kind, $itemKey, $starred, $status);
}

describe('searching a collection', function (): void {
    it('finds a word while ignoring capitals, accents and extra spaces, and says where it matched', function (): void {
        $session = browseSession(CarbonImmutable::now());
        browseRow($session, '  Café  ', FeedbackType::Vocabulary, [
            'correction_text' => 'a place for coffee',
            'example' => 'We met at the café.',
        ]);
        browseRow($session, 'tea', FeedbackType::Vocabulary, [
            'correction_text' => 'a hot drink',
        ]);

        $this->get(route('collections.index', ['q' => 'CAFE']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->count('items', 1)
                ->where('items.0.key', 'cafe')
                ->where('items.0.match.label', 'Word')
                ->where('items.0.match.segments.0.text', 'Café')
                ->where('items.0.match.segments.0.match', true)
                ->where('filters.q', 'CAFE'));
    });

    it('needs every search word in the same item', function (): void {
        $session = browseSession(CarbonImmutable::now());
        browseRow($session, 'coffee', FeedbackType::Vocabulary, [
            'correction_text' => 'a hot drink',
            'example' => 'I drink coffee every morning.',
        ]);
        browseRow($session, 'shop', FeedbackType::Vocabulary, [
            'correction_text' => 'a place to buy things',
        ]);

        $this->get(route('collections.index', ['q' => 'coffee morning']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', 'coffee')
                ->where('items.0.match.label', 'Example'));
    });

    it('searches the learner’s own notes and never shows saved markup as a highlight', function (): void {
        $session = browseSession(CarbonImmutable::now());
        browseRow($session, 'reluctant', FeedbackType::Vocabulary, [
            'correction_text' => 'slow to act',
        ]);

        app(SaveItemNotes::class)->handle(
            FeedbackType::Vocabulary,
            'reluctant',
            '<p>My <strong>favourite</strong> word</p>',
            0,
        );

        $this->get(route('collections.index', ['q' => 'favourite']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.match.label', 'Your notes')
                ->where('items.0.match.segments.0.text', 'My ')
                ->where('items.0.match.segments.0.match', false)
                ->where('items.0.match.segments.1.text', 'favourite')
                ->where('items.0.match.segments.1.match', true)
                ->where('items.0.match.segments.2.text', ' word')
                ->where('items.0.match.segments.2.match', false)
                ->where('filters.q', 'favourite'));
    });

    it('ignores a search too short to be useful', function (): void {
        $session = browseSession(CarbonImmutable::now());
        browseRow($session, 'tea', FeedbackType::Vocabulary, ['correction_text' => 'a hot drink']);
        browseRow($session, 'soup', FeedbackType::Vocabulary, ['correction_text' => 'a liquid food']);

        $this->get(route('collections.index', ['q' => 't']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 2)
                ->where('filters.q', '')
                ->where('items.0.match', null));
    });

    it('searches grammar notes and mistakes in their own words', function (): void {
        $session = browseSession(CarbonImmutable::now());
        browseNote($session, 'Use the past perfect for earlier actions.');
        browseRow($session, 'I am agree', FeedbackType::Mistake, [
            'correction_text' => 'I agree',
            'reason' => 'Verb form',
        ]);

        $this->get(route('collections.index', ['tab' => 'grammar', 'q' => 'earlier']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.match.label', 'Note'));

        $this->get(route('collections.index', ['tab' => 'mistakes', 'q' => 'verb']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.match.label', 'Why'));
    });
});

describe('filtering a collection', function (): void {
    it('narrows by star, status and both at once', function (): void {
        $session = browseSession(CarbonImmutable::now());
        browseRow($session, 'apple', FeedbackType::Vocabulary, ['correction_text' => 'a fruit']);
        browseRow($session, 'pear', FeedbackType::Vocabulary, ['correction_text' => 'a fruit']);
        browseRow($session, 'plum', FeedbackType::Vocabulary, ['correction_text' => 'a fruit']);

        browseMark(FeedbackType::Vocabulary, 'apple', true, CollectionItemStatus::Learned);
        browseMark(FeedbackType::Vocabulary, 'pear', true, CollectionItemStatus::StillDifficult);

        $this->get(route('collections.index', ['starred' => '1']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 2)
                ->where('items.0.key', 'apple')
                ->where('items.1.key', 'pear'));

        $this->get(route('collections.index', ['status' => 'learned']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', 'apple'));

        $this->get(route('collections.index', ['status' => 'none']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', 'plum'));

        $this->get(route('collections.index', [
            'starred' => '1',
            'status' => 'still_difficult',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', 'pear'));
    });

    it('shows only the items that need attention, with the whole collection count', function (): void {
        $session = browseSession(CarbonImmutable::now());
        browseRow($session, 'apple', FeedbackType::Vocabulary, ['correction_text' => 'a fruit']);
        browseRow($session, 'pear', FeedbackType::Vocabulary);
        browseRow($session, 'plum', FeedbackType::Vocabulary);

        $this->get(route('collections.index', ['attention' => '1']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 2)
                ->where('all_total', 3)
                ->where('attention_count', 2)
                ->where('items.0.needs_attention', true)
                ->where('items.1.needs_attention', true));
    });

    it('narrows by topic while keeping every appearance of a matching item', function (): void {
        $cooking = Topic::factory()->create(['name' => 'Cooking']);
        $travel = Topic::factory()->create(['name' => 'Travel']);

        $old = browseSession(CarbonImmutable::now()->subDay(), $cooking);
        browseRow($old, 'soup', FeedbackType::Vocabulary, [
            'correction_text' => 'a liquid food',
        ]);

        $new = browseSession(CarbonImmutable::now(), $travel);
        browseRow($new, 'soup', FeedbackType::Vocabulary, [
            'correction_text' => 'a warm dish',
        ]);

        $this->get(route('collections.index', ['topics' => (string) $cooking->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', 'soup')
                ->where('items.0.seen_count', 2)
                ->where('items.0.meaning', 'a warm dish')
                ->count('items.0.appearances', 2)
                ->where('filters.topics', [$cooking->id]));

        $this->get(route('collections.index', [
            'topics' => $cooking->id.','.$travel->id,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', 'soup'));
    });

    it('offers the topics that feed the collection, alphabetically, with their counts', function (): void {
        $travel = Topic::factory()->create(['name' => 'Travel']);
        $cooking = Topic::factory()->create(['name' => 'Cooking']);

        $first = browseSession(CarbonImmutable::now(), $travel);
        browseRow($first, 'soup', FeedbackType::Vocabulary, ['correction_text' => 'a liquid food']);
        browseRow($first, 'tea', FeedbackType::Vocabulary, ['correction_text' => 'a drink']);

        $second = browseSession(CarbonImmutable::now(), $cooking);
        browseRow($second, 'pan', FeedbackType::Vocabulary, ['correction_text' => 'a pot']);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('topic_options.0.name', 'Cooking')
                ->where('topic_options.0.id', $cooking->id)
                ->where('topic_options.0.count', 1)
                ->where('topic_options.1.name', 'Travel')
                ->where('topic_options.1.count', 2));
    });

    it('keeps tab counts whole while the list is narrowed', function (): void {
        $session = browseSession(CarbonImmutable::now());
        browseRow($session, 'apple', FeedbackType::Vocabulary, ['correction_text' => 'a fruit']);
        browseRow($session, 'pear', FeedbackType::Vocabulary, ['correction_text' => 'a fruit']);
        browseRow($session, 'hello', FeedbackType::Phrase, ['correction_text' => 'a greeting']);

        $this->get(route('collections.index', ['q' => 'apple']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('all_total', 2)
                ->where('tabs.0.count', 2)
                ->where('tabs.1.count', 1));
    });
});

describe('ordering a collection', function (): void {
    it('starts with the newest word and can order accent-insensitively from A to Z', function (): void {
        $old = browseSession(CarbonImmutable::now()->subDay());
        browseRow($old, 'Éclair', FeedbackType::Vocabulary, ['correction_text' => 'a pastry']);

        $new = browseSession(CarbonImmutable::now());
        browseRow($new, 'apple', FeedbackType::Vocabulary, ['correction_text' => 'a fruit']);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.sort', 'newest')
                ->where('items.0.key', 'apple')
                ->where('items.1.key', 'eclair'));

        $this->get(route('collections.index', ['sort' => 'az']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.sort', 'az')
                ->where('items.0.key', 'apple')
                ->where('items.1.key', 'eclair'));
    });

    it('starts mistakes by how often they were seen and can order them from A to Z', function (): void {
        $first = browseSession(CarbonImmutable::now()->subDay());
        browseRow($first, 'I am agree', FeedbackType::Mistake, [
            'correction_text' => 'I agree',
        ]);

        $second = browseSession(CarbonImmutable::now());
        browseRow($second, 'I am agree', FeedbackType::Mistake, [
            'correction_text' => 'I agree',
        ]);
        browseRow($second, 'He go', FeedbackType::Mistake, [
            'correction_text' => 'He goes',
        ]);

        $this->get(route('collections.index', ['tab' => 'mistakes']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.sort', 'most_seen')
                ->where('items.0.title', 'I am agree')
                ->where('items.0.seen_count', 2)
                ->where('items.1.title', 'He go'));

        $this->get(route('collections.index', ['tab' => 'mistakes', 'sort' => 'az']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.sort', 'az')
                ->where('items.0.title', 'He go'));
    });

    it('quietly refuses an order a grammar note cannot honour', function (): void {
        $session = browseSession(CarbonImmutable::now());
        browseNote($session, 'Use the past perfect.');

        $this->get(route('collections.index', [
            'tab' => 'grammar',
            'sort' => 'most_seen',
            'attention' => '1',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.sort', 'newest')
                ->where('filters.attention', false)
                ->where('total', 1));
    });
});

describe('a stale collection address', function (): void {
    it('ignores unknown values and opens a useful list', function (): void {
        $session = browseSession(CarbonImmutable::now());
        browseRow($session, 'apple', FeedbackType::Vocabulary, ['correction_text' => 'a fruit']);
        browseNote($session, 'Use the past perfect.');

        $this->get(route('collections.index', [
            'tab' => 'grammar',
            'q' => 'a',
            'status' => 'banana',
            'starred' => 'maybe',
            'attention' => 'sometimes',
            'topics' => '999999',
            'sort' => 'most_seen',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('filters.q', '')
                ->where('filters.status', 'all')
                ->where('filters.starred', false)
                ->where('filters.attention', false)
                ->where('filters.topics', [])
                ->where('filters.sort', 'newest'));
    });
});
