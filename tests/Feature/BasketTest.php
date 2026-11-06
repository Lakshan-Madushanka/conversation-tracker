<?php

declare(strict_types=1);

use App\Actions\Collections\SaveItemMark;
use App\Actions\PracticeQuestions\SavePracticeQuestions;
use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Models\BasketItem;
use App\Models\CollectionItemMark;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\Topic;
use App\Support\MistakeIdentity;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session at a known moment, with a topic the basket can name.
 *
 * @param  array<string, mixed>  $attributes
 */
function basketSession(
    string $topic = 'Ordering coffee',
    ?CarbonImmutable $at = null,
    array $attributes = [],
): PracticeSession {
    $at ??= CarbonImmutable::now();

    return PracticeSession::factory()
        ->finished()
        ->for(Topic::factory()->create(['name' => $topic]))
        ->create(array_merge([
            'created_at' => $at,
            'updated_at' => $at,
            'finished_at' => $at,
            'last_changed_at' => $at,
        ], $attributes));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function basketQuestion(
    PracticeSession $session,
    int $position = 1,
    array $attributes = [],
): PracticeQuestion {
    return PracticeQuestion::factory()->for($session)->create(array_merge([
        'position' => $position,
    ], $attributes));
}

/**
 * One row of a table box, with its place inside the box.
 *
 * @param  array<string, mixed>  $attributes
 */
function basketRow(
    PracticeQuestion $question,
    string $text,
    FeedbackType $type = FeedbackType::Vocabulary,
    string $correction = '',
    array $attributes = [],
): PracticeFeedbackEntry {
    $position = $question->feedbackEntries()->where('type', $type->value)->count();

    return PracticeFeedbackEntry::factory()->for($question)->create(array_merge([
        'type' => $type,
        'content_text' => $text,
        'correction_text' => $correction,
        'correction_html' => $correction === '' ? '' : '<p>'.e($correction).'</p>',
        'reason' => '',
        'example' => '',
        'example_html' => [],
        'position' => $position,
        'is_split' => true,
    ], $attributes));
}

/**
 * One grammar note in a question.
 *
 * @param  array<string, mixed>  $attributes
 */
function basketNote(
    PracticeQuestion $question,
    string $text,
    array $attributes = [],
): PracticeFeedbackEntry {
    return PracticeFeedbackEntry::factory()->for($question)->create(array_merge([
        'type' => FeedbackType::GrammarNote,
        'content_html' => '<p>'.$text.'</p>',
        'content_text' => $text,
        'position' => 0,
    ], $attributes));
}

it('adds a word once, however it is sent', function (): void {
    $session = basketSession();
    basketRow(basketQuestion($session), 'Café');

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => [' Café ', 'CAFE'],
    ])->assertOk()->assertJson([
        'added_count' => 1,
        'already_count' => 0,
        'total' => 1,
    ]);

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => ['cafe'],
    ])->assertOk()->assertJson([
        'added_count' => 0,
        'already_count' => 1,
        'total' => 1,
    ]);

    expect(BasketItem::query()->count())->toBe(1)
        ->and(BasketItem::query()->sole()->item_identity)->toBe('cafe');
});

it('adds every kind of item by its collection identity', function (): void {
    $session = basketSession();
    $question = basketQuestion($session);

    basketRow($question, 'relish', FeedbackType::Vocabulary);
    basketRow($question, 'break the ice', FeedbackType::Phrase);
    $note = basketNote($question, 'Use the past tense here.');
    basketRow($question, 'I goed', FeedbackType::Mistake, 'I went');

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => ['relish'],
    ])->assertOk();
    $this->postJson(route('basket.items.store'), [
        'kind' => 'phrase',
        'identities' => ['Break  The Ice'],
    ])->assertOk();
    $this->postJson(route('basket.items.store'), [
        'kind' => 'grammar_note',
        'identities' => ['note-'.$note->id],
    ])->assertOk();
    $this->postJson(route('basket.items.store'), [
        'kind' => 'mistake',
        'identities' => [MistakeIdentity::pairKey('I goed', 'I went')],
    ])->assertOk();

    expect(BasketItem::query()->orderBy('kind')->pluck('item_identity')->all())->toBe([
        'note-'.$note->id,
        MistakeIdentity::pairKey('I goed', 'I went'),
        'break the ice',
        'relish',
    ]);
});

it('keeps one item when the same word was said in several sessions', function (): void {
    basketRow(basketQuestion(basketSession('Morning coffee', CarbonImmutable::now()->subDay())), 'cafe');
    basketRow(basketQuestion(basketSession()), 'Café');

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => ['cafe'],
    ])->assertOk();

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => ['CAFE'],
    ])->assertOk()->assertJson(['added_count' => 0, 'already_count' => 1]);

    expect(BasketItem::query()->count())->toBe(1);
});

it('marks the items already in the basket on the collection page', function (): void {
    $session = basketSession();
    basketRow(basketQuestion($session), 'cafe');

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => ['cafe'],
    ])->assertOk();

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('basket_count', 1)
            ->where('items.0.key', 'cafe')
            ->where('items.0.in_basket', true));

    $this->get(route('collections.index', ['item' => 'cafe']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('open_item.in_basket', true));
});

it('lists the basket grouped in order, with its words, source and availability', function (): void {
    $active = basketSession(at: CarbonImmutable::now());
    $activeQuestion = basketQuestion($active, 2, [
        'question_html' => '<p>What would you order?</p>',
        'question_text' => 'What would you order?',
    ]);

    basketRow($activeQuestion, 'cafe');
    basketRow($activeQuestion, 'break the ice', FeedbackType::Phrase);
    basketNote($activeQuestion, 'Use the past tense here.');
    basketRow($activeQuestion, 'I goed', FeedbackType::Mistake, 'I went');

    $archived = basketSession(
        'A past day',
        CarbonImmutable::now()->subDay(),
        ['archived_at' => CarbonImmutable::now()],
    );
    basketRow(basketQuestion($archived), 'archivedword');

    $ghost = basketSession('Deleted day', CarbonImmutable::now()->subDays(2));
    basketRow(basketQuestion($ghost), 'ghostword');

    $add = function (FeedbackType $kind, array $identities): void {
        $this->postJson(route('basket.items.store'), [
            'kind' => $kind->value,
            'identities' => $identities,
        ])->assertOk();
    };

    $add(FeedbackType::Vocabulary, ['archivedword']);
    $add(FeedbackType::Vocabulary, ['cafe']);
    $add(FeedbackType::Phrase, ['break the ice']);
    $add(FeedbackType::GrammarNote, ['note-'.$activeQuestion->feedbackEntries()->where('type', 'grammar_note')->sole()->id]);
    $add(FeedbackType::Mistake, [MistakeIdentity::pairKey('I goed', 'I went')]);
    $add(FeedbackType::Question, [(string) $activeQuestion->id]);
    $add(FeedbackType::Vocabulary, ['ghostword']);

    $ghost->delete();

    $this->get(route('basket.index', ['from' => '/collections?tab=vocabulary']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('basket/Index')
            ->where('count', 6)
            ->where('back_url', '/collections?tab=vocabulary')
            ->has('groups', 5)
            ->where('groups.0.key', 'vocabulary')
            ->where('groups.0.label', 'Words')
            ->where('groups.0.count', 2)
            ->where('groups.0.items.0.title', 'cafe')
            ->where('groups.0.items.0.available', true)
            ->where('groups.0.items.0.topic', 'Ordering coffee')
            ->where('groups.0.items.1.title', 'archivedword')
            ->where('groups.0.items.1.available', false)
            ->where('groups.0.items.1.topic', 'A past day')
            ->where('groups.1.label', 'Phrases')
            ->where('groups.1.items.0.title', 'break the ice')
            ->where('groups.2.label', 'Grammar notes')
            ->where('groups.2.items.0.title', 'Use the past tense here.')
            ->where('groups.3.label', 'Mistakes')
            ->where('groups.3.items.0.title', 'I goed')
            ->where('groups.3.items.0.correction', 'I went')
            ->where('groups.4.label', 'Questions')
            ->where('groups.4.items.0.title', 'What would you order?'));

    expect(BasketItem::query()->where('item_identity', 'ghostword')->exists())->toBeFalse()
        ->and(BasketItem::query()->count())->toBe(6);

    $href = BasketItem::query()
        ->where('kind', FeedbackType::Question->value)
        ->value('item_identity');

    expect($href)->toBe((string) $activeQuestion->id);
});

it('removes selected items and clears the rest', function (): void {
    $session = basketSession();
    $question = basketQuestion($session);
    basketRow($question, 'relish');
    basketRow($question, 'cafe');

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => ['relish', 'cafe'],
    ])->assertOk()->assertJson(['total' => 2]);

    $this->deleteJson(route('basket.items.destroy'), [
        'kind' => 'vocabulary',
        'identities' => ['relish', 'not-there'],
    ])->assertOk()->assertJson([
        'removed_count' => 1,
        'removed_identities' => ['relish'],
        'total' => 1,
    ]);

    $this->deleteJson(route('basket.clear'))
        ->assertOk()
        ->assertJson(['cleared_count' => 1, 'total' => 0]);

    expect(BasketItem::query()->count())->toBe(0);
});

it('changes nothing else when the basket is added to, removed from or cleared', function (): void {
    $session = basketSession();
    $question = basketQuestion($session);
    basketRow($question, 'relish');
    basketRow($question, 'cafe', FeedbackType::Vocabulary, 'to eat slowly');
    basketNote($question, 'Use the past tense here.');

    app(SaveItemMark::class)->handle(
        FeedbackType::Vocabulary,
        'relish',
        true,
        CollectionItemStatus::StillDifficult,
    );

    $markBefore = CollectionItemMark::query()->sole()->toArray();
    $sessionBefore = $session->fresh()?->toArray();
    $entriesBefore = PracticeFeedbackEntry::query()->orderBy('id')->get()->toArray();
    $ratingsBefore = Rating::query()->count();
    $reviewsBefore = Review::query()->count();

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => ['relish', 'cafe'],
    ])->assertOk();

    $this->postJson(route('basket.items.store'), [
        'kind' => 'grammar_note',
        'identities' => ['note-'.$question->feedbackEntries()->where('type', 'grammar_note')->sole()->id],
    ])->assertOk();

    $this->deleteJson(route('basket.items.destroy'), [
        'kind' => 'vocabulary',
        'identities' => ['cafe'],
    ])->assertOk();

    $this->deleteJson(route('basket.clear'))->assertOk();

    expect(CollectionItemMark::query()->count())->toBe(1)
        ->and(CollectionItemMark::query()->sole()->toArray())->toBe($markBefore)
        ->and($session->fresh()?->toArray())->toBe($sessionBefore)
        ->and(PracticeFeedbackEntry::query()->orderBy('id')->get()->toArray())->toBe($entriesBefore)
        ->and(Rating::query()->count())->toBe($ratingsBefore)
        ->and(Review::query()->count())->toBe($reviewsBefore);
});

it('adds a question from the reading page and marks it added there', function (): void {
    $session = basketSession();
    $question = basketQuestion($session);

    $this->get(route('sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sessions/Show')
            ->where('basketQuestionIds', []));

    $this->postJson(route('basket.items.store'), [
        'kind' => 'question',
        'identities' => [(string) $question->id],
    ])->assertOk()->assertJson(['added_count' => 1, 'total' => 1]);

    $this->get(route('sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('basketQuestionIds', [$question->id]));

    $this->get(route('basket.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('groups.0.label', 'Questions')
            ->where('groups.0.items.0.title', 'Sample question'));
});

it('selects every matching collection item for a bulk add', function (): void {
    basketRow(basketQuestion(basketSession('First')), 'zebra');
    basketRow(basketQuestion(basketSession('Second')), 'apple');

    $this->getJson(route('collections.selection'))
        ->assertOk()
        ->assertJsonCount(2, 'keys')
        ->assertJson(['total' => 2]);

    $this->getJson(route('collections.selection', ['q' => 'zeb']))
        ->assertOk()
        ->assertJson(['keys' => ['zebra'], 'total' => 1]);

    $keys = $this->getJson(route('collections.selection'))->json('keys');

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => $keys,
    ])->assertOk()->assertJson(['added_count' => 2, 'total' => 2]);
});

it('follows an edited word in the basket by the typo rule', function (): void {
    $session = basketSession();
    $question = basketQuestion($session);
    $row = basketRow($question, 'teh', FeedbackType::Vocabulary, 'slow to act', [
        'client_id' => 'row-1',
    ]);

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => ['teh'],
    ])->assertOk();

    DB::transaction(fn (): mixed => app(SavePracticeQuestions::class)->handle($session, [
        [
            'question_html' => '<p>What did you notice?</p>',
            'answer_html' => '<p>A sentence.</p>',
            'feedback' => [
                'vocabulary' => [[
                    'id' => $row->client_id,
                    'text' => 'the',
                    'meaning' => 'slow to act',
                ]],
            ],
        ],
    ]));

    expect(BasketItem::query()->sole()->item_identity)->toBe('the');
});

it('rejects an identity that does not belong to its kind', function (): void {
    $this->postJson(route('basket.items.store'), [
        'kind' => 'natural_version',
        'identities' => ['anything'],
    ])->assertUnprocessable();

    $this->postJson(route('basket.items.store'), [
        'kind' => 'grammar_note',
        'identities' => ['12'],
    ])->assertUnprocessable();

    $this->postJson(route('basket.items.store'), [
        'kind' => 'question',
        'identities' => ['zero'],
    ])->assertUnprocessable();

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => ['   '],
    ])->assertUnprocessable();

    expect(BasketItem::query()->count())->toBe(0);

    // A word longer than the identity column is refused calmly too.
    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => [SearchText::normalize(str_repeat('a', 200))],
    ])->assertUnprocessable();
});

it('shows the count on Review and nowhere else', function (): void {
    $session = basketSession();
    basketRow(basketQuestion($session), 'cafe');

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => ['cafe'],
    ])->assertOk();

    $this->get(route('review.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('review/Index')
            ->where('basketCount', 1));

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->missing('basketCount'));

    $this->get(route('sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->missing('basketCount'));
});

it('reads the basket in the same number of queries however many items it holds', function (): void {
    $session = basketSession();
    $question = basketQuestion($session);

    foreach (range(1, 3) as $index) {
        basketRow($question, 'word'.$index);
    }

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => ['word1', 'word2', 'word3'],
    ])->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get(route('basket.index'))->assertOk();
    $few = count(DB::getQueryLog());

    foreach (range(4, 40) as $index) {
        basketRow($question, 'word'.$index);
    }

    $this->postJson(route('basket.items.store'), [
        'kind' => 'vocabulary',
        'identities' => array_map(fn (int $index): string => 'word'.$index, range(4, 40)),
    ])->assertOk();

    DB::flushQueryLog();
    $this->get(route('basket.index'))->assertOk();
    $many = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($many)->toBe($few);
});
