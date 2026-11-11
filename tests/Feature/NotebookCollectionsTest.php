<?php

declare(strict_types=1);

use App\Actions\Collections\SaveItemMark;
use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;
use App\Models\CustomReview;
use App\Models\CustomReviewItem;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Support\NotebookIdentity;
use App\Support\RowAttention;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session at a known moment, with its own topic.
 *
 * @param  array<string, mixed>  $attributes
 */
function notebookCollectionsSession(
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
 * One row of a session's table box.
 *
 * @param  array<string, mixed>  $attributes
 */
function notebookCollectionsSessionRow(
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

/**
 * One saved row of a notebook's box, in the order the editor stores it.
 *
 * @param  array<string, mixed>  $attributes
 */
function notebookCollectionsRow(
    Notebook $notebook,
    string $subject,
    FeedbackType $type = FeedbackType::Vocabulary,
    array $attributes = [],
): PracticeFeedbackEntry {
    $next = (int) $notebook->entries()->where('type', $type->value)->max('position') + 1;

    return PracticeFeedbackEntry::factory()
        ->for($notebook)
        ->create(array_merge([
            'practice_question_id' => null,
            'type' => $type,
            'position' => $next,
            'client_id' => (string) Str::uuid(),
            'content_html' => '',
            'content_text' => $subject,
            'correction_html' => '',
            'correction_text' => '',
            'reason' => '',
            'example' => '',
            'example_html' => [],
            'is_split' => true,
        ], $attributes));
}

/** The scoped identity one notebook word or phrase carries. */
function notebookCollectionsKey(Notebook $notebook, string $subject): string
{
    return NotebookIdentity::wordKey($notebook->id, $subject);
}

/** Put a star and status on one scoped item the way the panel does. */
function notebookCollectionsMark(
    FeedbackType $kind,
    string $itemKey,
    ?bool $starred = null,
    ?CollectionItemStatus $status = null,
): void {
    app(SaveItemMark::class)->handle($kind, $itemKey, $starred, $status);
}

describe('notebook sources in Collections', function (): void {
    it('keeps a session word and the same word in two notebooks as three scoped items', function (): void {
        $sessionTopic = Topic::factory()->create(['name' => 'Study']);
        $travelTopic = Topic::factory()->create(['name' => 'Travel']);
        $workTopic = Topic::factory()->create(['name' => 'Work']);

        $session = notebookCollectionsSession(CarbonImmutable::now()->subDays(2), $sessionTopic);
        notebookCollectionsSessionRow($session, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'a financial institution',
        ]);

        $travel = Notebook::factory()->for($travelTopic)->create(['name' => 'Travel notes']);
        notebookCollectionsRow($travel, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'the side of a river',
        ]);

        $work = Notebook::factory()->for($workTopic)->create(['name' => 'Work notes']);
        notebookCollectionsRow($work, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'a place for money',
        ]);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.source', 'all')
                ->where('filters.notebook', null)
                ->where('total', 3)
                ->where('all_total', 3)
                ->count('items', 3)
                ->has('notebook_options', 2)
                ->where('notebook_options.0.name', 'Travel notes')
                ->where('notebook_options.0.count', 1)
                ->where('notebook_options.1.name', 'Work notes')
                ->where('notebook_options.1.count', 1)
                ->where('items', fn (Collection $items): bool => $items
                    ->pluck('source')
                    ->sort()
                    ->values()
                    ->all() === ['notebook', 'notebook', 'session']));

        $this->get(route('collections.index', ['source' => 'sessions']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.source', 'sessions')
                ->where('total', 1)
                ->where('items.0.source', 'session')
                ->where('items.0.notebook', null)
                ->where('items.0.key', 'bank'));

        $this->get(route('collections.index', ['source' => 'notebooks']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 2)
                ->where('items', fn (Collection $items): bool => $items
                    ->every(fn (array $item): bool => $item['source'] === 'notebook')));

        $this->get(route('collections.index', ['notebook' => $travel->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.source', 'notebooks')
                ->where('filters.notebook', $travel->id)
                ->where('total', 1)
                ->where('items.0.source', 'notebook')
                ->where('items.0.key', notebookCollectionsKey($travel, 'bank'))
                ->where('items.0.notebook.id', $travel->id)
                ->where('items.0.notebook.name', 'Travel notes')
                ->where('items.0.notebook.topic', 'Travel')
                ->where('items.0.meaning', 'the side of a river'));

        // A notebook choice cannot survive a switch to session-only items.
        $this->get(route('collections.index', [
            'source' => 'sessions',
            'notebook' => $travel->id,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('filters.source', 'sessions')
                ->where('filters.notebook', null)
                ->where('total', 1)
                ->where('items.0.source', 'session'));
    });

    it('lets a notebook-only learner see their saved content instead of the start-a-session state', function (): void {
        $notebook = Notebook::factory()->create(['name' => 'From my book']);
        notebookCollectionsRow($notebook, 'serendipity', FeedbackType::Vocabulary, [
            'correction_text' => 'a happy accident',
        ]);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('has_finished_sessions', false)
                ->where('has_sources', true)
                ->where('total', 1)
                ->where('items.0.source', 'notebook')
                ->where('items.0.notebook.name', 'From my book'));
    });

    it('offers both starting paths when neither source holds anything', function (): void {
        Notebook::factory()->create();

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('has_finished_sessions', false)
                ->where('has_sources', false)
                ->where('total', 0)
                ->has('items', 0));
    });
});

describe('scoped filters and counts', function (): void {
    it('narrows by scope, topic, search, status and attention on the scoped item', function (): void {
        $travelTopic = Topic::factory()->create(['name' => 'Travel']);
        $studyTopic = Topic::factory()->create(['name' => 'Study']);

        $session = notebookCollectionsSession(CarbonImmutable::now()->subDay(), $studyTopic);
        notebookCollectionsSessionRow($session, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'a financial institution',
        ]);

        $travel = Notebook::factory()->for($travelTopic)->create(['name' => 'Travel notes']);
        notebookCollectionsRow($travel, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'money',
        ]);
        // A duplicate row is still one study identity.
        notebookCollectionsRow($travel, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => '',
            'example' => 'The river bank was quiet.',
        ]);

        $study = Notebook::factory()->for($studyTopic)->create(['name' => 'Study notes']);
        notebookCollectionsRow($study, 'bank');

        notebookCollectionsMark(FeedbackType::Vocabulary, notebookCollectionsKey($travel, 'bank'), true, CollectionItemStatus::Learned);
        notebookCollectionsMark(FeedbackType::Vocabulary, notebookCollectionsKey($study, 'bank'), false, CollectionItemStatus::StillDifficult);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 3)
                ->where('all_total', 3)
                ->where('tabs.0.count', 3)
                ->where('attention_count', 1));

        $this->get(route('collections.index', ['notebook' => $travel->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('all_total', 1)
                ->where('tabs.0.count', 1)
                ->where('attention_count', 0)
                ->where('items.0.key', notebookCollectionsKey($travel, 'bank'))
                ->where('items.0.meaning', 'money')
                ->where('items.0.needs_attention', false));

        $this->get(route('collections.index', ['notebook' => $travel->id, 'q' => 'money']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', notebookCollectionsKey($travel, 'bank')));

        $this->get(route('collections.index', ['q' => 'quiet']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', notebookCollectionsKey($travel, 'bank')));

        $this->get(route('collections.index', ['topics' => (string) $travelTopic->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.notebook.id', $travel->id));

        $this->get(route('collections.index', ['status' => 'learned']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', notebookCollectionsKey($travel, 'bank')));

        $this->get(route('collections.index', ['attention' => '1']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', notebookCollectionsKey($study, 'bank'))
                ->where('items.0.attention_reason', RowAttention::ADD_MEANING));

        $this->get(route('collections.index', ['notebook' => $study->id, 'attention' => '1']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('attention_count', 1)
                ->where('items.0.needs_attention', true));
    });

    it('selects the same scoped keys the list shows', function (): void {
        $session = notebookCollectionsSession(CarbonImmutable::now()->subDay());
        notebookCollectionsSessionRow($session, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'a financial institution',
        ]);

        $travel = Notebook::factory()->create(['name' => 'Travel notes']);
        notebookCollectionsRow($travel, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'the side of a river',
        ]);

        $work = Notebook::factory()->create(['name' => 'Work notes']);
        notebookCollectionsRow($work, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'a place for money',
        ]);

        $all = $this->getJson(route('collections.selection'))
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->json('keys');

        expect($all)->toEqualCanonicalizing([
            'bank',
            notebookCollectionsKey($travel, 'bank'),
            notebookCollectionsKey($work, 'bank'),
        ]);

        $notebookOnly = $this->getJson(route('collections.selection', [
            'notebook' => $travel->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->json('keys');

        expect($notebookOnly)->toBe([notebookCollectionsKey($travel, 'bank')]);

        $eligible = $this->getJson(route('collections.selection', [
            'source' => 'notebooks',
            'q' => 'river',
            'eligible' => 1,
            'review' => CustomReview::factory()->draft()->create()->id,
        ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->json('keys');

        expect($eligible)->toBe([notebookCollectionsKey($travel, 'bank')]);
    });

    it('applies eligibility to the scoped item without borrowing from another scope', function (): void {
        $session = notebookCollectionsSession(CarbonImmutable::now()->subDay());
        notebookCollectionsSessionRow($session, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'a financial institution',
        ]);

        $complete = Notebook::factory()->create(['name' => 'Complete notes']);
        notebookCollectionsRow($complete, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'the side of a river',
        ]);
        notebookCollectionsRow($complete, 'bank', FeedbackType::Vocabulary);

        $incomplete = Notebook::factory()->create(['name' => 'Incomplete notes']);
        notebookCollectionsRow($incomplete, 'bank');

        $review = CustomReview::factory()->draft()->create();

        $this->get(route('collections.index', [
            'review' => $review->id,
            'eligible' => 1,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 2)
                ->where('all_total', 2)
                ->where('tabs.0.count', 2)
                ->where('items', fn (Collection $items): bool => $items
                    ->pluck('key')
                    ->all() !== [notebookCollectionsKey($incomplete, 'bank')]
                    && $items->contains(
                        fn (array $item): bool => $item['key'] === notebookCollectionsKey($complete, 'bank'),
                    )));

        $this->get(route('collections.index', [
            'source' => 'notebooks',
            'review' => $review->id,
            'eligible' => 1,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', notebookCollectionsKey($complete, 'bank')));
    });
});

describe('opening a notebook item', function (): void {
    it('shows the notebook panel its own rows and no session navigation', function (): void {
        $topic = Topic::factory()->create(['name' => 'Travel']);
        $notebook = Notebook::factory()->for($topic)->create(['name' => 'Travel notes']);

        $first = notebookCollectionsRow($notebook, 'bank', FeedbackType::Vocabulary, [
            'position' => 0,
            'created_at' => CarbonImmutable::now()->subDay(),
            'correction_text' => '',
            'example' => 'The river bank was quiet.',
        ]);
        $second = notebookCollectionsRow($notebook, 'bank', FeedbackType::Vocabulary, [
            'position' => 1,
            'correction_text' => 'money',
        ]);

        $key = notebookCollectionsKey($notebook, 'bank');

        $this->get(route('collections.index', [
            'notebook' => $notebook->id,
            'item' => $key,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item.source', 'notebook')
                ->where('open_item.key', $key)
                ->where('open_item.notebook.id', $notebook->id)
                ->where('open_item.notebook.name', 'Travel notes')
                ->where('open_item.notebook.topic', 'Travel')
                ->where('open_item.session_id', null)
                ->where('open_item.session_date', null)
                ->where('open_item.question_position', null)
                ->where('open_item.latest_date', $second->created_at?->toIso8601String())
                ->has('open_item.appearances', 0)
                ->has('open_item.rows', 2)
                ->where('open_item.rows.0.entry_position', 0)
                ->where('open_item.rows.0.example', 'The river bank was quiet.')
                ->where('open_item.rows.1.entry_position', 1)
                ->where('open_item.rows.1.meaning', 'money'));
    });

    it('keeps session panels on their sessions', function (): void {
        $topic = Topic::factory()->create(['name' => 'Ordering at a restaurant']);
        $session = notebookCollectionsSession(CarbonImmutable::now(), $topic);
        notebookCollectionsSessionRow($session, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'a financial institution',
        ]);

        $this->get(route('collections.index', ['item' => 'bank']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item.source', 'session')
                ->where('open_item.notebook', null)
                ->where('open_item.session_id', $session->id)
                ->where('open_item.topic', 'Ordering at a restaurant')
                ->has('open_item.appearances', 1)
                ->where('open_item.appearances.0.session_id', $session->id));
    });
});

describe('notebook duplicate rows', function (): void {
    it('uses saved editor order for the list and every distinct row in the panel', function (): void {
        $travelTopic = Topic::factory()->create(['name' => 'Travel']);
        $notebook = Notebook::factory()->for($travelTopic)->create(['name' => 'Travel notes']);

        notebookCollectionsRow($notebook, 'bank', FeedbackType::Vocabulary, [
            'position' => 0,
            'example' => 'first example',
        ]);
        notebookCollectionsRow($notebook, 'bank', FeedbackType::Vocabulary, [
            'position' => 1,
            'correction_text' => 'money',
        ]);
        notebookCollectionsRow($notebook, 'bank', FeedbackType::Vocabulary, [
            'position' => 2,
            'correction_text' => 'river bank',
            'example' => 'second example',
        ]);

        $otherNotebook = Notebook::factory()->create(['name' => 'Other notes']);
        notebookCollectionsRow($otherNotebook, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'other scope meaning',
        ]);

        $session = notebookCollectionsSession(CarbonImmutable::now()->subDay());
        notebookCollectionsSessionRow($session, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'session scope meaning',
        ]);

        $key = notebookCollectionsKey($notebook, 'bank');

        $this->get(route('collections.index', ['notebook' => $notebook->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.title', 'bank')
                ->where('items.0.meaning', 'money')
                ->where('items.0.example', 'first example')
                ->where('items.0.seen_count', null)
                ->where('items.0.needs_attention', false));

        $this->get(route('collections.index', [
            'notebook' => $notebook->id,
            'item' => $key,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('open_item.meaning', 'money')
                ->where('open_item.example', 'first example')
                ->has('open_item.rows', 3)
                ->where('open_item.rows.0.example', 'first example')
                ->where('open_item.rows.1.meaning', 'money')
                ->where('open_item.rows.2.meaning', 'river bank')
                ->where('open_item.rows.2.example', 'second example'));
    });

    it('stays calm when any duplicate row is complete and asks for a meaning otherwise', function (): void {
        $complete = Notebook::factory()->create(['name' => 'Complete notes']);
        notebookCollectionsRow($complete, 'bank');
        notebookCollectionsRow($complete, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'money',
        ]);

        $incomplete = Notebook::factory()->create(['name' => 'Incomplete notes']);
        notebookCollectionsRow($incomplete, 'bank');

        $this->get(route('collections.index', ['notebook' => $complete->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.meaning', 'money')
                ->where('items.0.needs_attention', false)
                ->where('items.0.attention_reason', null));

        $this->get(route('collections.index', ['notebook' => $incomplete->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.needs_attention', true)
                ->where('items.0.attention_reason', RowAttention::ADD_MEANING));
    });
});

describe('marks on notebook items', function (): void {
    it('shares a mark across surfaces of one scoped item and leaves other scopes alone', function (): void {
        $session = notebookCollectionsSession(CarbonImmutable::now()->subDay());
        notebookCollectionsSessionRow($session, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'a financial institution',
        ]);

        $travel = Notebook::factory()->create(['name' => 'Travel notes']);
        notebookCollectionsRow($travel, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'the side of a river',
        ]);

        $work = Notebook::factory()->create(['name' => 'Work notes']);
        notebookCollectionsRow($work, 'bank', FeedbackType::Vocabulary, [
            'correction_text' => 'a place for money',
        ]);

        $key = notebookCollectionsKey($travel, 'bank');

        $this->putJson(route('collections.marks.store'), [
            'kind' => FeedbackType::Vocabulary->value,
            'item' => $key,
            'starred' => true,
            'status' => CollectionItemStatus::Learned->value,
        ])->assertOk();

        $this->putJson(route('collections.notes.store'), [
            'kind' => FeedbackType::Vocabulary->value,
            'item' => $key,
            'html' => '<p>garden plan</p>',
            'version' => 0,
        ])->assertOk();

        $this->get(route('collections.index', ['notebook' => $travel->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.starred', true)
                ->where('items.0.status', 'learned')
                ->where('items.0.has_notes', true));

        $this->get(route('collections.index', ['source' => 'sessions']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.starred', false)
                ->where('items.0.status', null)
                ->where('items.0.has_notes', false));

        $this->get(route('collections.index', ['notebook' => $work->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.starred', false)
                ->where('items.0.status', null));

        $this->get(route('collections.index', ['q' => 'garden']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.key', $key));
    });

    it('keeps marks when the notebook is renamed or its topic changes', function (): void {
        $oldTopic = Topic::factory()->create(['name' => 'Travel']);
        $newTopic = Topic::factory()->create(['name' => 'Work']);
        $notebook = Notebook::factory()->for($oldTopic)->create(['name' => 'Travel notes']);

        $row = notebookCollectionsRow($notebook, 'bank', FeedbackType::Vocabulary, [
            'client_id' => 'w1',
            'correction_text' => 'money',
        ]);

        $key = notebookCollectionsKey($notebook, 'bank');
        notebookCollectionsMark(FeedbackType::Vocabulary, $key, true, CollectionItemStatus::Learned);

        $this->putJson(route('notebooks.autosave.update', $notebook), [
            'name' => 'Renamed notes',
            'topic' => $newTopic->name,
            'description' => '',
            'version' => $notebook->version,
            'save_key' => (string) Str::uuid(),
            'feedback' => [
                'vocabulary' => [[
                    'id' => 'w1',
                    'text' => 'bank',
                    'meaning' => 'money',
                    'example_html' => '',
                ]],
                'phrase' => [],
                'grammar_note' => [],
            ],
        ])->assertOk();

        expect($row->fresh()?->id)->toBe($row->id);

        $this->get(route('collections.index', ['notebook' => $notebook->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.key', $key)
                ->where('items.0.starred', true)
                ->where('items.0.status', 'learned')
                ->where('items.0.notebook.name', 'Renamed notes')
                ->where('items.0.notebook.topic', 'Work'));
    });
});

describe('a notebook typo edit', function (): void {
    it('moves marks and review references to the new scoped identity', function (): void {
        $notebook = Notebook::factory()->create(['name' => 'Travel notes']);
        $row = notebookCollectionsRow($notebook, 'bank', FeedbackType::Vocabulary, [
            'client_id' => 'w1',
            'correction_text' => 'money',
        ]);

        $from = notebookCollectionsKey($notebook, 'bank');
        $to = notebookCollectionsKey($notebook, 'bench');

        notebookCollectionsMark(FeedbackType::Vocabulary, $from, true, CollectionItemStatus::Learned);

        $review = CustomReview::factory()->draft()->create();
        CustomReviewItem::factory()->create([
            'custom_review_id' => $review->id,
            'kind' => FeedbackType::Vocabulary,
            'item_identity' => $from,
        ]);

        $this->putJson(route('notebooks.autosave.update', $notebook), [
            'name' => $notebook->name,
            'topic' => $notebook->topic->name,
            'description' => '',
            'version' => $notebook->version,
            'save_key' => (string) Str::uuid(),
            'feedback' => [
                'vocabulary' => [[
                    'id' => 'w1',
                    'text' => 'bench',
                    'meaning' => 'money',
                    'example_html' => '',
                ]],
                'phrase' => [],
                'grammar_note' => [],
            ],
        ])->assertOk();

        expect($row->fresh()?->collection_key)->toBe($to);

        expect(CollectionItemMark::query()
            ->where('kind', FeedbackType::Vocabulary->value)
            ->where('group_key', $to)
            ->where('starred', true)
            ->where('status', CollectionItemStatus::Learned->value)
            ->exists())->toBeTrue();

        expect(CollectionItemMark::query()
            ->where('kind', FeedbackType::Vocabulary->value)
            ->where('group_key', $from)
            ->exists())->toBeFalse();

        expect(CustomReviewItem::query()
            ->where('custom_review_id', $review->id)
            ->where('kind', FeedbackType::Vocabulary->value)
            ->value('item_identity'))->toBe($to);
    });

    it('keeps the old marks while a duplicate row still carries the old identity', function (): void {
        $notebook = Notebook::factory()->create(['name' => 'Travel notes']);
        notebookCollectionsRow($notebook, 'bank', FeedbackType::Vocabulary, [
            'position' => 0,
            'client_id' => 'w1',
            'correction_text' => 'money',
        ]);
        notebookCollectionsRow($notebook, 'bank', FeedbackType::Vocabulary, [
            'position' => 1,
            'client_id' => 'w2',
            'correction_text' => 'river bank',
        ]);

        $from = notebookCollectionsKey($notebook, 'bank');
        $to = notebookCollectionsKey($notebook, 'bench');

        notebookCollectionsMark(FeedbackType::Vocabulary, $from, true, CollectionItemStatus::Learned);

        $review = CustomReview::factory()->draft()->create();
        CustomReviewItem::factory()->create([
            'custom_review_id' => $review->id,
            'kind' => FeedbackType::Vocabulary,
            'item_identity' => $from,
        ]);

        $this->putJson(route('notebooks.autosave.update', $notebook), [
            'name' => $notebook->name,
            'topic' => $notebook->topic->name,
            'description' => '',
            'version' => $notebook->version,
            'save_key' => (string) Str::uuid(),
            'feedback' => [
                'vocabulary' => [
                    [
                        'id' => 'w1',
                        'text' => 'bench',
                        'meaning' => 'money',
                        'example_html' => '',
                    ],
                    [
                        'id' => 'w2',
                        'text' => 'bank',
                        'meaning' => 'river bank',
                        'example_html' => '',
                    ],
                ],
                'phrase' => [],
                'grammar_note' => [],
            ],
        ])->assertOk();

        expect(CollectionItemMark::query()
            ->where('kind', FeedbackType::Vocabulary->value)
            ->where('group_key', $from)
            ->where('starred', true)
            ->exists())->toBeTrue();

        expect(CollectionItemMark::query()
            ->where('kind', FeedbackType::Vocabulary->value)
            ->where('group_key', $to)
            ->exists())->toBeFalse();

        expect(CustomReviewItem::query()
            ->where('custom_review_id', $review->id)
            ->where('kind', FeedbackType::Vocabulary->value)
            ->value('item_identity'))->toBe($from);
    });

    it('never overwrites marks that the destination already holds', function (): void {
        $notebook = Notebook::factory()->create(['name' => 'Travel notes']);
        notebookCollectionsRow($notebook, 'bank', FeedbackType::Vocabulary, [
            'position' => 0,
            'client_id' => 'w1',
            'correction_text' => 'money',
        ]);
        notebookCollectionsRow($notebook, 'bench', FeedbackType::Vocabulary, [
            'position' => 1,
            'client_id' => 'w2',
            'correction_text' => 'a seat',
        ]);

        $from = notebookCollectionsKey($notebook, 'bank');
        $to = notebookCollectionsKey($notebook, 'bench');

        notebookCollectionsMark(FeedbackType::Vocabulary, $from, true, CollectionItemStatus::Learned);
        notebookCollectionsMark(FeedbackType::Vocabulary, $to, false, CollectionItemStatus::StillDifficult);

        $this->putJson(route('notebooks.autosave.update', $notebook), [
            'name' => $notebook->name,
            'topic' => $notebook->topic->name,
            'description' => '',
            'version' => $notebook->version,
            'save_key' => (string) Str::uuid(),
            'feedback' => [
                'vocabulary' => [
                    [
                        'id' => 'w1',
                        'text' => 'bench',
                        'meaning' => 'money',
                        'example_html' => '',
                    ],
                    [
                        'id' => 'w2',
                        'text' => 'bench',
                        'meaning' => 'a seat',
                        'example_html' => '',
                    ],
                ],
                'phrase' => [],
                'grammar_note' => [],
            ],
        ])->assertOk();

        expect(CollectionItemMark::query()
            ->where('kind', FeedbackType::Vocabulary->value)
            ->where('group_key', $to)
            ->where('starred', false)
            ->where('status', CollectionItemStatus::StillDifficult->value)
            ->exists())->toBeTrue();

        // The old marks stay quietly stored under the old identity.
        expect(CollectionItemMark::query()
            ->where('kind', FeedbackType::Vocabulary->value)
            ->where('group_key', $from)
            ->where('starred', true)
            ->exists())->toBeTrue();
    });
});

describe('mixed-source ordering', function (): void {
    it('interleaves session dates and notebook added dates under newest', function (): void {
        $session = notebookCollectionsSession(CarbonImmutable::now()->subDay());
        notebookCollectionsSessionRow($session, 'session word', FeedbackType::Vocabulary, [
            'correction_text' => 'a word',
        ]);

        $older = Notebook::factory()->create(['name' => 'Older notes']);
        notebookCollectionsRow($older, 'older word', FeedbackType::Vocabulary, [
            'correction_text' => 'an older word',
            'created_at' => CarbonImmutable::now()->subDays(3),
        ]);

        $newer = Notebook::factory()->create(['name' => 'Newer notes']);
        notebookCollectionsRow($newer, 'newer word', FeedbackType::Vocabulary, [
            'correction_text' => 'a newer word',
            'created_at' => CarbonImmutable::now()->subHours(2),
        ]);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.title', 'newer word')
                ->where('items.0.source', 'notebook')
                ->where('items.1.title', 'session word')
                ->where('items.1.source', 'session')
                ->where('items.2.title', 'older word')
                ->where('items.2.source', 'notebook'));
    });

    it('puts notebook items after counted session items under most often seen', function (): void {
        $first = notebookCollectionsSession(CarbonImmutable::now()->subDays(2));
        notebookCollectionsSessionRow($first, 'seen twice', FeedbackType::Vocabulary, [
            'correction_text' => 'a repeated word',
        ]);
        $second = notebookCollectionsSession(CarbonImmutable::now()->subDay());
        notebookCollectionsSessionRow($second, 'seen twice', FeedbackType::Vocabulary, [
            'correction_text' => 'a repeated word',
        ]);

        $notebook = Notebook::factory()->create(['name' => 'Notes']);
        notebookCollectionsRow($notebook, 'notebook word', FeedbackType::Vocabulary, [
            'correction_text' => 'a saved word',
            'created_at' => CarbonImmutable::now(),
        ]);

        $this->get(route('collections.index', ['sort' => 'most_seen']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('items.0.title', 'seen twice')
                ->where('items.0.seen_count', 2)
                ->where('items.1.title', 'notebook word')
                ->where('items.1.seen_count', null));
    });

    it('pages mixed sources without repeating or skipping a scoped item', function (): void {
        $session = notebookCollectionsSession(CarbonImmutable::now()->subDays(6));
        notebookCollectionsSessionRow($session, 'session one', FeedbackType::Vocabulary, [
            'correction_text' => 'one',
        ]);
        notebookCollectionsSessionRow($session, 'session two', FeedbackType::Vocabulary, [
            'correction_text' => 'two',
        ]);

        $notebook = Notebook::factory()->create(['name' => 'Notes']);

        foreach (range(1, 4) as $index) {
            notebookCollectionsRow($notebook, 'notebook '.$index, FeedbackType::Vocabulary, [
                'correction_text' => 'notebook '.$index,
                'created_at' => CarbonImmutable::now()->subDays($index),
            ]);
        }

        $keys = [];
        $cursor = null;
        $total = null;

        do {
            $params = ['per_page' => 2];

            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }

            $response = $this->getJson(route('collections.index', $params))->assertOk();

            $total = $response->json('total');
            $keys = [...$keys, ...$response->json('items') ?: []];
            $cursor = $response->json('next_cursor');
        } while ($cursor !== null);

        $titles = array_column($keys, 'title');

        expect($total)->toBe(6);
        expect(count($titles))->toBe(6);
        expect(array_unique($titles))->toHaveCount(6);
        expect($titles)->toBe([
            'notebook 1',
            'notebook 2',
            'notebook 3',
            'notebook 4',
            'session one',
            'session two',
        ]);
    });

    it('dates a notebook grammar note by when it was added', function (): void {
        $topic = Topic::factory()->create(['name' => 'Tenses']);
        $notebook = Notebook::factory()->for($topic)->create(['name' => 'Tense notes']);

        $added = CarbonImmutable::now()->subDay();

        notebookCollectionsRow($notebook, 'Use the past perfect.', FeedbackType::GrammarNote, [
            'content_html' => '<p>Use the past perfect.</p>',
            'created_at' => $added,
        ]);

        $this->get(route('collections.index', [
            'tab' => 'grammar',
            'source' => 'notebooks',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 1)
                ->where('items.0.source', 'notebook')
                ->where('items.0.session_id', null)
                ->where('items.0.session_date', null)
                ->where('items.0.question_position', null)
                ->where('items.0.topic', 'Tenses')
                ->where('items.0.notebook.name', 'Tense notes')
                ->where('items.0.latest_date', $added->toIso8601String()));
    });

    it('keeps notebook mistakes out of My mistakes', function (): void {
        $session = notebookCollectionsSession(CarbonImmutable::now()->subDay());
        notebookCollectionsSessionRow($session, 'I am agree', FeedbackType::Mistake, [
            'correction_text' => 'I agree',
        ]);

        $notebook = Notebook::factory()->create(['name' => 'Notes']);
        notebookCollectionsRow($notebook, 'a word');

        $this->get(route('collections.index', [
            'tab' => 'mistakes',
            'source' => 'notebooks',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('total', 0)
                ->has('items', 0)
                ->where('has_sources', true));
    });
});
