<?php

declare(strict_types=1);

use App\Actions\Collections\SaveItemMark;
use App\Actions\PracticeQuestions\SavePracticeQuestions;
use App\Enums\CollectionItemStatus;
use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;
use App\Models\CustomReview;
use App\Models\CustomReviewItem;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\Topic;
use App\Support\CustomReviewRules;
use App\Support\MistakeIdentity;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session at a known moment, with a topic the review can name.
 *
 * @param  array<string, mixed>  $attributes
 */
function buildingSession(
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
function buildingQuestion(
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
function buildingRow(
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
function buildingNote(
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

/**
 * The one way items reach a review: the request "Add to review" makes.
 *
 * @param  list<string>  $identities
 */
function addToReview(
    object $test,
    FeedbackType $kind,
    array $identities,
    ?int $reviewId = null,
    bool $confirmed = false,
): TestResponse {
    $payload = [
        'kind' => $kind->value,
        'identities' => $identities,
    ];

    if ($reviewId !== null) {
        $payload['review'] = $reviewId;
    }

    if ($confirmed) {
        $payload['confirmed'] = true;
    }

    return $test->postJson(route('customReviews.items.store'), $payload);
}

/** The review started the way the learner starts one, so it carries its number. */
function startReview(object $test): CustomReview
{
    $test->post(route('customReviews.store'))->assertRedirect();

    $review = CustomReview::query()->orderByDesc('id')->first();
    expect($review)->toBeInstanceOf(CustomReview::class);

    /** @var CustomReview $review */
    return $review;
}

it('names each new review with the next number none of them uses', function (): void {
    $first = $this->post(route('customReviews.store'));

    $review = CustomReview::query()->sole();
    expect($review->name)->toBe('Custom Review 1')
        ->and($review->status)->toBe(CustomReviewStatus::Empty);

    $first->assertRedirect(route('customReviews.show', $review));

    $this->post(route('customReviews.store'))->assertRedirect();
    expect(CustomReview::query()->orderBy('id')->pluck('name')->all())
        ->toBe(['Custom Review 1', 'Custom Review 2']);

    // The number a deleted review leaves behind is used again, so two reviews
    // never end up with the same default name.
    $review->delete();

    $this->post(route('customReviews.store'))->assertRedirect();

    expect(CustomReview::query()->orderBy('id')->pluck('name')->all())
        ->toBe(['Custom Review 2', 'Custom Review 1'])
        ->and(CustomReview::query()->pluck('name')->unique()->count())
        ->toBe(CustomReview::query()->count());
});

it('fills the review from its first item and empties it again with its last', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);
    buildingRow($question, 'cafe');
    buildingRow($question, 'relish');

    $review = startReview($this);

    addToReview($this, FeedbackType::Vocabulary, ['cafe'], $review->id)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'added_count' => 1, 'total' => 1]);

    expect($review->fresh()?->status)->toBe(CustomReviewStatus::Draft);

    $this->deleteJson(route('customReviews.items.destroy', $review), [
        'kind' => 'vocabulary',
        'identities' => ['cafe'],
    ])->assertOk()->assertJson(['removed_count' => 1, 'total' => 0]);

    expect($review->fresh()?->status)->toBe(CustomReviewStatus::Empty);

    addToReview($this, FeedbackType::Vocabulary, ['relish'], $review->id)->assertOk();

    expect($review->fresh()?->status)->toBe(CustomReviewStatus::Draft);
});

it('keeps a review the learner finished marked finished until it is empty', function (): void {
    $session = buildingSession();
    buildingRow(buildingQuestion($session), 'cafe');

    $review = CustomReview::factory()->withItem()->finished()->create();
    $held = CustomReviewItem::query()->where('custom_review_id', $review->id)->pluck('item_identity')->all();

    addToReview($this, FeedbackType::Vocabulary, ['cafe'], $review->id)
        ->assertOk()
        ->assertJson(['outcome' => 'added']);

    expect($review->fresh()?->status)->toBe(CustomReviewStatus::Finished);

    $this->deleteJson(route('customReviews.items.destroy', $review), [
        'kind' => 'vocabulary',
        'identities' => [...$held, 'cafe'],
    ])->assertOk()->assertJson(['total' => 0]);

    expect($review->fresh()?->status)->toBe(CustomReviewStatus::Empty);
});

it('asks which review when several are being built and offers the newest first', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);
    buildingRow($question, 'cafe');
    buildingRow($question, 'relish');

    $older = CustomReview::factory()->withItem()->create();
    $newer = CustomReview::factory()->withItem()->withItem()->create();
    $finished = CustomReview::factory()->withItem()->finished()->create();

    $answer = addToReview($this, FeedbackType::Vocabulary, ['cafe'])->assertOk();

    expect($answer->json('outcome'))->toBe('needs_review_choice')
        ->and(array_column($answer->json('reviews'), 'id'))->toBe([$newer->id, $older->id])
        ->and($answer->json('reviews.0.item_count'))->toBe(2)
        ->and($answer->json('reviews.1.item_count'))->toBe(1);

    // Nothing was written by the question itself.
    expect(CustomReviewItem::query()->where('item_identity', 'cafe')->exists())->toBeFalse();

    addToReview($this, FeedbackType::Vocabulary, ['cafe'], $older->id)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'total' => 2]);

    expect($older->fresh()?->status)->toBe(CustomReviewStatus::Draft)
        ->and($newer->fresh()?->status)->toBe(CustomReviewStatus::Draft)
        ->and($finished->fresh()?->status)->toBe(CustomReviewStatus::Finished);

    // A review the learner finished is never one of the choices.
    expect(array_column(
        addToReview($this, FeedbackType::Vocabulary, ['relish'])->json('reviews'),
        'id',
    ))->not->toContain($finished->id);
});

it('makes a review and names it when none is being built', function (): void {
    $session = buildingSession();
    buildingRow(buildingQuestion($session), 'cafe');

    addToReview($this, FeedbackType::Vocabulary, ['cafe'])
        ->assertOk()
        ->assertJson([
            'outcome' => 'added',
            'added_count' => 1,
            'total' => 1,
            'review' => ['name' => 'Custom Review 1', 'status' => 'draft', 'item_count' => 1],
        ]);

    expect(CustomReview::query()->count())->toBe(1)
        ->and(CustomReviewItem::query()->count())->toBe(1);
});

it('adds a word once, however it is sent', function (): void {
    $session = buildingSession();
    buildingRow(buildingQuestion($session), 'Café');

    addToReview($this, FeedbackType::Vocabulary, [' Café ', 'CAFE'])
        ->assertOk()
        ->assertJson(['added_count' => 1, 'already_count' => 0, 'total' => 1]);

    addToReview($this, FeedbackType::Vocabulary, ['cafe'])
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'added_count' => 0, 'already_count' => 1, 'total' => 1]);

    expect(CustomReviewItem::query()->count())->toBe(1)
        ->and(CustomReviewItem::query()->sole()->item_identity)->toBe('cafe');
});

it('adds every kind of item by its collection identity', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);

    buildingRow($question, 'relish', FeedbackType::Vocabulary);
    buildingRow($question, 'break the ice', FeedbackType::Phrase);
    $note = buildingNote($question, 'Use the past tense here.');
    buildingRow($question, 'I goed', FeedbackType::Mistake, 'I went');

    addToReview($this, FeedbackType::Vocabulary, ['relish'])->assertOk();
    addToReview($this, FeedbackType::Phrase, ['Break  The Ice'])->assertOk();
    addToReview($this, FeedbackType::GrammarNote, ['note-'.$note->id])->assertOk();
    addToReview($this, FeedbackType::Mistake, [MistakeIdentity::pairKey('I goed', 'I went')])->assertOk();

    expect(CustomReviewItem::query()->orderBy('kind')->pluck('item_identity')->all())->toBe([
        'note-'.$note->id,
        MistakeIdentity::pairKey('I goed', 'I went'),
        'break the ice',
        'relish',
    ]);
});

it('keeps one item when the same word was said in several sessions', function (): void {
    buildingRow(buildingQuestion(buildingSession('Morning coffee', CarbonImmutable::now()->subDay())), 'cafe');
    buildingRow(buildingQuestion(buildingSession()), 'Café');

    addToReview($this, FeedbackType::Vocabulary, ['cafe'])->assertOk();
    addToReview($this, FeedbackType::Vocabulary, ['CAFE'])
        ->assertOk()
        ->assertJson(['added_count' => 0, 'already_count' => 1]);

    expect(CustomReviewItem::query()->count())->toBe(1);
});

it('warns before an add that takes a review past its recommended size', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);

    $existing = array_map(fn (int $index): string => 'word'.$index, range(1, CustomReviewRules::RECOMMENDED_ITEMS));
    $further = array_map(fn (int $index): string => 'word'.$index, range(21, 26));

    foreach ([...$existing, ...$further] as $text) {
        buildingRow($question, $text);
    }

    buildingRow($question, 'take it or leave it', FeedbackType::Phrase);

    $review = startReview($this);

    // Reaching the recommended size is not yet crossing it, so this says nothing.
    addToReview($this, FeedbackType::Vocabulary, $existing, $review->id)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'total' => 20]);

    $answer = addToReview($this, FeedbackType::Vocabulary, $further, $review->id)
        ->assertOk()
        ->assertJson(['outcome' => 'needs_confirmation', 'resulting_count' => 26]);

    expect($answer->json('review.name'))->toBe('Custom Review 1')
        // The review shown with the warning is the review as it would be: the
        // message names the count the learner is about to reach.
        ->and($answer->json('review.item_count'))->toBe(26)
        ->and(CustomReviewItem::query()->count())->toBe(20);

    addToReview($this, FeedbackType::Vocabulary, $further, $review->id, true)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'added_count' => 6, 'total' => 26]);

    // And it asks again about the next add, because the review is still long.
    $later = addToReview($this, FeedbackType::Phrase, ['take it or leave it'], $review->id)
        ->assertOk()
        ->assertJson(['outcome' => 'needs_confirmation', 'resulting_count' => 27]);

    expect($later->json('review.item_count'))->toBe(27);

    addToReview($this, FeedbackType::Phrase, ['take it or leave it'], $review->id, true)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'total' => 27]);
});

it('asks about a long add before any review exists and leaves nothing behind', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);

    $texts = array_map(fn (int $index): string => 'word'.$index, range(1, 40));
    foreach ($texts as $text) {
        buildingRow($question, $text);
    }

    $answer = addToReview($this, FeedbackType::Vocabulary, $texts)
        ->assertOk()
        ->assertJson(['outcome' => 'needs_confirmation', 'resulting_count' => 40]);

    expect($answer->json('review'))->toBeNull()
        // Cancelling the warning leaves no half-started review behind.
        ->and(CustomReview::query()->count())->toBe(0);

    addToReview($this, FeedbackType::Vocabulary, $texts, null, true)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'added_count' => 40, 'total' => 40]);

    expect(CustomReview::query()->sole()->status)->toBe(CustomReviewStatus::Draft)
        ->and(CustomReview::query()->sole()->name)->toBe('Custom Review 1');
});

it('says nothing when an add only repeats what a long review already holds', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);

    $texts = array_map(fn (int $index): string => 'word'.$index, range(1, 24));
    foreach ($texts as $text) {
        buildingRow($question, $text);
    }

    addToReview($this, FeedbackType::Vocabulary, $texts, null, true)->assertOk();
    $review = CustomReview::query()->sole();

    addToReview($this, FeedbackType::Vocabulary, $texts, $review->id)
        ->assertOk()
        ->assertJson([
            'outcome' => 'added',
            'added_count' => 0,
            'already_count' => 24,
            'total' => 24,
        ]);
});

it('refuses an add that would cross the maximum as a whole and names the room left', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);

    $further = array_map(fn (int $index): string => 'extra'.$index, range(1, 6));
    foreach ($further as $text) {
        buildingRow($question, $text);
    }

    // Every kind held counts toward the same maximum: 30 words and 15 phrases.
    $review = CustomReview::factory()->draft()->create();
    CustomReviewItem::factory()->count(30)->create(['custom_review_id' => $review->id]);
    foreach (range(1, 15) as $index) {
        CustomReviewItem::factory()->phrase('phrase '.$index)->create(['custom_review_id' => $review->id]);
    }

    addToReview($this, FeedbackType::Vocabulary, $further, $review->id)
        ->assertOk()
        ->assertJson([
            'outcome' => 'refused_maximum',
            'message' => 'This review has room for 5 more items.',
            'review' => ['item_count' => 45],
        ]);

    // The whole add is refused: no partial subset slipped in.
    expect(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(45)
        ->and($review->fresh()?->status)->toBe(CustomReviewStatus::Draft);
});

it('allows an add that lands exactly on the maximum', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);

    $further = array_map(fn (int $index): string => 'extra'.$index, range(1, 5));
    foreach ($further as $text) {
        buildingRow($question, $text);
    }

    $review = CustomReview::factory()->draft()->create();
    CustomReviewItem::factory()->count(45)->create(['custom_review_id' => $review->id]);

    addToReview($this, FeedbackType::Vocabulary, $further, $review->id, true)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'added_count' => 5, 'total' => 50]);

    expect(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(50);
});

it('refuses a confirmed add that would cross the maximum', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);

    $further = array_map(fn (int $index): string => 'extra'.$index, range(1, 3));
    foreach ($further as $text) {
        buildingRow($question, $text);
    }

    $review = CustomReview::factory()->draft()->create();
    CustomReviewItem::factory()->count(48)->create(['custom_review_id' => $review->id]);

    // Answering the size warning never answers the maximum.
    addToReview($this, FeedbackType::Vocabulary, $further, $review->id, true)
        ->assertOk()
        ->assertJson([
            'outcome' => 'refused_maximum',
            'message' => 'This review has room for 2 more items.',
        ]);

    expect(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(48);
});

it('counts items in archived sessions toward the maximum', function (): void {
    $session = buildingSession(attributes: ['archived_at' => CarbonImmutable::now()]);
    $question = buildingQuestion($session);

    $held = array_map(fn (int $index): string => 'word'.$index, range(1, 50));
    foreach ($held as $text) {
        buildingRow($question, $text);
    }

    addToReview($this, FeedbackType::Vocabulary, $held, null, true)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'total' => 50]);

    $review = CustomReview::query()->sole();

    buildingRow(buildingQuestion(buildingSession('Live topic')), 'newcomer');

    // The review holds 50, though none of them can be practised right now.
    addToReview($this, FeedbackType::Vocabulary, ['newcomer'], $review->id, true)
        ->assertOk()
        ->assertJson([
            'outcome' => 'refused_maximum',
            'message' => 'This review is full at 50 items.',
        ]);

    expect(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(50);
});

it('refuses an add too long for any review and creates nothing', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);

    $texts = array_map(fn (int $index): string => 'word'.$index, range(1, 51));
    foreach ($texts as $text) {
        buildingRow($question, $text);
    }

    addToReview($this, FeedbackType::Vocabulary, $texts)
        ->assertOk()
        ->assertJson([
            'outcome' => 'refused_maximum',
            'message' => 'This review has room for 50 more items.',
            'review' => null,
        ]);

    expect(CustomReview::query()->count())->toBe(0)
        ->and(CustomReviewItem::query()->count())->toBe(0);
});

it('refuses an add that would overflow the review chosen in the picker', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);
    buildingRow($question, 'newcomer');
    buildingRow($question, 'another');

    $fullish = CustomReview::factory()->draft()->create();
    CustomReviewItem::factory()->count(49)->create(['custom_review_id' => $fullish->id]);
    $other = CustomReview::factory()->draft()->create();

    addToReview($this, FeedbackType::Vocabulary, ['newcomer', 'another'])
        ->assertOk()
        ->assertJson(['outcome' => 'needs_review_choice']);

    addToReview($this, FeedbackType::Vocabulary, ['newcomer', 'another'], $fullish->id)
        ->assertOk()
        ->assertJson([
            'outcome' => 'refused_maximum',
            'message' => 'This review has room for 1 more item.',
        ]);

    expect(CustomReviewItem::query()->where('custom_review_id', $fullish->id)->count())->toBe(49);

    // A refusal names one review's room; another review still takes the items.
    addToReview($this, FeedbackType::Vocabulary, ['newcomer', 'another'], $other->id)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'total' => 2]);
});

it('still warns below the maximum and lets a confirmed add reach it', function (): void {
    $session = buildingSession();
    buildingRow(buildingQuestion($session), 'newcomer');

    $review = CustomReview::factory()->draft()->create();
    CustomReviewItem::factory()->count(49)->create(['custom_review_id' => $review->id]);

    addToReview($this, FeedbackType::Vocabulary, ['newcomer'], $review->id)
        ->assertOk()
        ->assertJson(['outcome' => 'needs_confirmation', 'resulting_count' => 50]);

    addToReview($this, FeedbackType::Vocabulary, ['newcomer'], $review->id, true)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'total' => 50]);
});

it('lets a full review hear again what it already holds', function (): void {
    $review = CustomReview::factory()->draft()->create();
    CustomReviewItem::factory()->count(50)->create(['custom_review_id' => $review->id]);

    $held = CustomReviewItem::query()
        ->where('custom_review_id', $review->id)
        ->pluck('item_identity')
        ->all();

    addToReview($this, FeedbackType::Vocabulary, $held, $review->id)
        ->assertOk()
        ->assertJson([
            'outcome' => 'added',
            'added_count' => 0,
            'already_count' => 50,
            'total' => 50,
        ]);
});

it('marks the items of the only review being built on the collections page', function (): void {
    $session = buildingSession();
    buildingRow(buildingQuestion($session), 'cafe');

    $review = startReview($this);
    addToReview($this, FeedbackType::Vocabulary, ['cafe'], $review->id)->assertOk();

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('collections/Index')
            ->where('review_target.id', $review->id)
            ->where('review_target.name', 'Custom Review 1')
            ->where('review_target.item_count', 1)
            ->where('items.0.key', 'cafe')
            ->where('items.0.in_review', true));

    $this->get(route('collections.index', ['item' => 'cafe']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('open_item.in_review', true));

    // With more than one under way the page cannot say which review an item is in.
    CustomReview::factory()->create();

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review_target', null)
            ->where('items.0.in_review', false));
});

it('adds a question from the reading page and marks it added there', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);

    $this->get(route('sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sessions/Show')
            ->where('reviewQuestionIds', [])
            ->where('reviewTarget', null));

    addToReview($this, FeedbackType::Question, [(string) $question->id])
        ->assertOk()
        ->assertJson(['added_count' => 1, 'total' => 1]);

    $this->get(route('sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reviewQuestionIds', [$question->id])
            ->where('reviewTarget.name', 'Custom Review 1'));
});

it('lists a review grouped by collection, with its words, source and availability', function (): void {
    $active = buildingSession(at: CarbonImmutable::now());
    $question = buildingQuestion($active, 2, [
        'question_html' => '<p>What would you order?</p>',
        'question_text' => 'What would you order?',
    ]);

    buildingRow($question, 'cafe');
    buildingRow($question, 'break the ice', FeedbackType::Phrase);
    $note = buildingNote($question, 'Use the past tense here.');
    buildingRow($question, 'I goed', FeedbackType::Mistake, 'I went');

    $archived = buildingSession(
        'A past day',
        CarbonImmutable::now()->subDay(),
        ['archived_at' => CarbonImmutable::now()],
    );
    buildingRow(buildingQuestion($archived), 'archivedword');

    $review = startReview($this);

    addToReview($this, FeedbackType::Vocabulary, ['archivedword'], $review->id)->assertOk();
    addToReview($this, FeedbackType::Vocabulary, ['cafe'], $review->id)->assertOk();
    addToReview($this, FeedbackType::Phrase, ['break the ice'], $review->id)->assertOk();
    addToReview($this, FeedbackType::GrammarNote, ['note-'.$note->id], $review->id)->assertOk();
    addToReview($this, FeedbackType::Mistake, [MistakeIdentity::pairKey('I goed', 'I went')], $review->id)->assertOk();
    addToReview($this, FeedbackType::Question, [(string) $question->id], $review->id)->assertOk();

    $this->get(route('customReviews.show', [
        'customReview' => $review,
        'from' => '/collections?tab=vocabulary',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('custom-reviews/Show')
            ->where('review.id', $review->id)
            ->where('review.name', 'Custom Review 1')
            ->where('review.status', 'draft')
            ->where('review.status_label', 'Draft')
            ->where('review.item_count', 6)
            ->where('review.item_count_label', '6 items')
            ->where('back_url', '/collections?tab=vocabulary')
            ->has('groups', 5)
            ->where('groups.0.key', 'vocabulary')
            ->where('groups.0.label', 'Words')
            ->where('groups.0.count', 2)
            ->where('groups.0.items.0.identity', 'cafe')
            ->where('groups.0.items.0.title', 'cafe')
            ->where('groups.0.items.0.available', true)
            ->where('groups.0.items.0.topic', 'Ordering coffee')
            ->whereNotNull('groups.0.items.0.session_date')
            ->where('groups.0.items.1.identity', 'archivedword')
            ->where('groups.0.items.1.available', false)
            ->where('groups.0.items.1.topic', 'A past day')
            ->where('groups.1.label', 'Phrases')
            ->where('groups.1.items.0.title', 'break the ice')
            ->where('groups.2.label', 'Grammar notes')
            ->where('groups.2.items.0.title', 'Use the past tense here.')
            ->where('groups.3.label', 'Mistakes')
            ->where('groups.3.items.0.title', 'I goed')
            ->where('groups.3.items.0.correction', 'I went')
            ->where('groups.3.items.0.correction_html', 'I went')
            ->where('groups.4.label', 'Questions')
            ->where('groups.4.items.0.title', 'What would you order?')
            ->where('groups.4.items.0.href', route('sessions.show', [
                'session' => $active,
                'from' => 'review',
                'question' => '2',
                'field' => 'question',
            ])));

    expect(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(6);
});

it('says a review with nothing in it is empty and points Back at the listing', function (): void {
    $review = CustomReview::factory()->create();

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('custom-reviews/Show')
            ->where('review.item_count', 0)
            ->where('review.item_count_label', '0 items')
            ->where('review.status_label', 'Empty')
            ->where('groups', [])
            ->where('back_url', route('customReviews.index')));
});

it('sends Back to where the learner came from, or to the listing when there is nowhere to return to', function (string $from, ?string $expected): void {
    $review = CustomReview::factory()->create();

    $this->get(route('customReviews.show', [
        'customReview' => $review,
        'from' => $from,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('custom-reviews/Show')
            ->where('back_url', $expected ?? route('customReviews.index')));
})->with([
    'the collections page it was opened from' => ['/collections?tab=vocabulary', '/collections?tab=vocabulary'],
    'the listing keeping its own filters' => ['/custom-reviews?status=finished', '/custom-reviews?status=finished'],
    'another review' => ['/custom-reviews/12', null],
    'an attempt of another review' => ['/custom-reviews/12/attempts/3', null],
    'a session of another review' => ['/sessions/7', '/sessions/7'],
    'somewhere outside the app' => ['https://elsewhere.test/practice', null],
    'the home page' => ['/', null],
]);

it('takes the chosen items out and clears the rest', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);
    buildingRow($question, 'relish');
    buildingRow($question, 'cafe');

    $review = startReview($this);

    addToReview($this, FeedbackType::Vocabulary, ['relish', 'cafe'], $review->id)
        ->assertOk()
        ->assertJson(['total' => 2]);

    $this->deleteJson(route('customReviews.items.destroy', $review), [
        'kind' => 'vocabulary',
        'identities' => ['relish', 'not-there'],
    ])->assertOk()->assertJson([
        'removed_count' => 1,
        'removed_identities' => ['relish'],
        'total' => 1,
    ]);

    // Undoing the removal puts the same item back in the same review.
    addToReview($this, FeedbackType::Vocabulary, ['relish'], $review->id)
        ->assertOk()
        ->assertJson(['added_count' => 1, 'total' => 2]);

    $this->postJson(route('customReviews.clear', $review))
        ->assertOk()
        ->assertJson(['cleared_count' => 2, 'total' => 0]);

    expect(CustomReviewItem::query()->count())->toBe(0)
        ->and(CustomReview::query()->count())->toBe(1)
        ->and($review->fresh()?->status)->toBe(CustomReviewStatus::Empty);
});

it('changes nothing else when a review is added to, removed from or cleared', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);
    buildingRow($question, 'relish');
    buildingRow($question, 'cafe', FeedbackType::Vocabulary, 'to eat slowly');
    $note = buildingNote($question, 'Use the past tense here.');

    app(SaveItemMark::class)->handle(
        FeedbackType::Vocabulary,
        'relish',
        true,
        CollectionItemStatus::StillDifficult,
    );

    $review = startReview($this);
    $other = CustomReview::factory()->draft()->create();
    $otherRow = CustomReviewItem::factory()->create(['custom_review_id' => $other->id]);

    $markBefore = CollectionItemMark::query()->sole()->toArray();
    $sessionBefore = $session->fresh()?->toArray();
    $entriesBefore = PracticeFeedbackEntry::query()->orderBy('id')->get()->toArray();
    $ratingsBefore = Rating::query()->count();
    $reviewsBefore = Review::query()->count();

    addToReview($this, FeedbackType::Vocabulary, ['relish', 'cafe'], $review->id)->assertOk();
    addToReview($this, FeedbackType::GrammarNote, ['note-'.$note->id], $review->id)->assertOk();

    $this->deleteJson(route('customReviews.items.destroy', $review), [
        'kind' => 'vocabulary',
        'identities' => ['cafe'],
    ])->assertOk();

    $this->postJson(route('customReviews.clear', $review))->assertOk();

    expect(CollectionItemMark::query()->count())->toBe(1)
        ->and(CollectionItemMark::query()->sole()->toArray())->toBe($markBefore)
        ->and($session->fresh()?->toArray())->toBe($sessionBefore)
        ->and(PracticeFeedbackEntry::query()->orderBy('id')->get()->toArray())->toBe($entriesBefore)
        ->and(Rating::query()->count())->toBe($ratingsBefore)
        ->and(Review::query()->count())->toBe($reviewsBefore)
        ->and(CustomReviewItem::query()->where('custom_review_id', $other->id)->sole()->id)
        ->toBe($otherRow->id);
});

it('selects every matching collection item for a bulk add', function (): void {
    buildingRow(buildingQuestion(buildingSession('First')), 'zebra');
    buildingRow(buildingQuestion(buildingSession('Second')), 'apple');

    $this->getJson(route('collections.selection'))
        ->assertOk()
        ->assertJsonCount(2, 'keys')
        ->assertJson(['total' => 2]);

    $this->getJson(route('collections.selection', ['q' => 'zeb']))
        ->assertOk()
        ->assertJson(['keys' => ['zebra'], 'total' => 1]);

    addToReview($this, FeedbackType::Vocabulary, $this->getJson(route('collections.selection'))->json('keys'))
        ->assertOk()
        ->assertJson(['added_count' => 2, 'total' => 2]);
});

it('follows an edited word into every review that holds it', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);
    $row = buildingRow($question, 'teh', FeedbackType::Vocabulary, 'slow to act', [
        'client_id' => 'row-1',
    ]);

    $review = startReview($this);
    $other = CustomReview::factory()->create();

    addToReview($this, FeedbackType::Vocabulary, ['teh'], $review->id)->assertOk();
    addToReview($this, FeedbackType::Vocabulary, ['teh'], $other->id)->assertOk();

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

    expect(CustomReviewItem::query()->pluck('item_identity')->unique()->sort()->values()->all())->toBe(['the'])
        ->and(CustomReviewItem::query()->count())->toBe(2);

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review.item_count', 1)
            ->where('groups.0.items.0.identity', 'the')
            ->where('groups.0.items.0.title', 'the'));
});

it('shows the words a session has now, not the words a review copied', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session, 1, ['question_text' => 'What do you order?']);

    $review = startReview($this);
    addToReview($this, FeedbackType::Question, [(string) $question->id], $review->id)->assertOk();

    DB::transaction(fn (): mixed => app(SavePracticeQuestions::class)->handle($session, [
        [
            'question_html' => '<p>What do you usually order?</p>',
            'answer_html' => '<p>A flat white.</p>',
            'feedback' => [],
        ],
    ]));

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('groups.0.key', 'question')
            ->where('groups.0.items.0.title', 'What do you usually order?'));
});

it('drops an item whose last session was deleted when a review is next opened', function (): void {
    $gone = buildingSession();
    $question = buildingQuestion($gone);
    buildingRow($question, 'cafe');
    $note = buildingNote($question, 'Use the past tense here.');

    $kept = buildingSession('A morning coffee');
    buildingRow(buildingQuestion($kept), 'relish');

    $review = startReview($this);
    addToReview($this, FeedbackType::Vocabulary, ['cafe'], $review->id)->assertOk();
    addToReview($this, FeedbackType::GrammarNote, ['note-'.$note->id], $review->id)->assertOk();

    // Another review holds the same word beside a word that stays.
    $other = CustomReview::factory()->create();
    addToReview($this, FeedbackType::Vocabulary, ['cafe', 'relish'], $other->id)->assertOk();

    $gone->delete();

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('review.item_count', 0)
            ->where('review.status', 'empty')
            ->where('groups', []));

    expect(CustomReviewItem::query()->where('kind', 'vocabulary')->pluck('item_identity')->all())
        ->toBe(['relish'])
        ->and(CustomReview::query()->count())->toBe(2)
        ->and($review->fresh()?->status)->toBe(CustomReviewStatus::Empty)
        ->and($other->fresh()?->status)->toBe(CustomReviewStatus::Draft);
});

it('rejects an identity that does not belong to its kind', function (): void {
    addToReview($this, FeedbackType::NaturalVersion, ['anything'])->assertUnprocessable();
    addToReview($this, FeedbackType::GrammarNote, ['12'])->assertUnprocessable();
    addToReview($this, FeedbackType::Question, ['zero'])->assertUnprocessable();
    addToReview($this, FeedbackType::Vocabulary, ['   '])->assertUnprocessable();

    expect(CustomReviewItem::query()->count())->toBe(0)
        ->and(CustomReview::query()->count())->toBe(0);

    // A word longer than the identity column is refused calmly too.
    addToReview($this, FeedbackType::Vocabulary, [SearchText::normalize(str_repeat('a', 200))])
        ->assertUnprocessable();

    expect(CustomReview::query()->count())->toBe(0);
});

it('shows nothing about a review being built on the pages that never mention one', function (): void {
    $session = buildingSession();
    buildingRow(buildingQuestion($session), 'cafe');

    addToReview($this, FeedbackType::Vocabulary, ['cafe'])->assertOk();

    foreach ([route('home'), route('review.index'), route('sessions.index'), route('review.custom')] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->missing('review_target')
                ->missing('reviewTarget')
                ->missing('reviewQuestionIds'));
    }
});

it('reads a review in the same number of queries however many items it holds', function (): void {
    $session = buildingSession();
    $question = buildingQuestion($session);
    $review = startReview($this);

    foreach (range(1, 3) as $index) {
        buildingRow($question, 'word'.$index);
    }

    addToReview($this, FeedbackType::Vocabulary, ['word1', 'word2', 'word3'], $review->id)->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get(route('customReviews.show', $review))->assertOk();
    $few = count(DB::getQueryLog());

    foreach (range(4, 40) as $index) {
        buildingRow($question, 'word'.$index);
    }

    addToReview($this, FeedbackType::Vocabulary, array_map(
        fn (int $index): string => 'word'.$index,
        range(4, 40),
    ), $review->id)->assertOk();

    DB::flushQueryLog();
    $this->get(route('customReviews.show', $review))->assertOk();
    $many = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($many)->toBe($few);
});

it('keeps the quick review chooser apart from the reviews the learner builds', function (): void {
    // "review/custom" starts a sitting from what is due; the saved review the
    // learner gathers lives under its own address and never becomes a sitting.
    $this->get(route('review.custom'))->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('review/Custom'));

    $this->post(route('customReviews.store'))->assertRedirect();

    expect(CustomReview::query()->sole()->name)->toBe('Custom Review 1')
        ->and(Review::query()->count())->toBe(0);
});
