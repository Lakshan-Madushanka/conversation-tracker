<?php

declare(strict_types=1);

use App\Actions\CustomReviews\AddCustomReviewItems;
use App\Actions\CustomReviews\SetCustomReviewStatus;
use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use App\Models\CustomReviewItem;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session holding the given texts of one kind, so a review can hold
 * real items that an attempt is able to carry.
 *
 * @param  list<string>  $texts
 * @return array{session: PracticeSession, identities: list<string>}
 */
function kindsSource(array $texts, FeedbackType $kind = FeedbackType::Vocabulary): array
{
    $at = CarbonImmutable::now();

    $session = PracticeSession::factory()
        ->finished()
        ->for(Topic::factory())
        ->create([
            'created_at' => $at,
            'updated_at' => $at,
            'finished_at' => $at,
            'last_changed_at' => $at,
        ]);

    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => 1,
    ]);

    $identities = [];

    foreach ($texts as $position => $text) {
        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => $kind->value,
            'content_text' => $text,
            'correction_text' => 'the meaning of '.$text,
            'correction_html' => '<p>the meaning of '.e($text).'</p>',
            'client_id' => (string) Str::uuid(),
            'position' => $position,
            'is_split' => true,
        ]);

        $identities[] = SearchText::normalize($text);
    }

    return ['session' => $session, 'identities' => $identities];
}

/**
 * A review holding the given words and phrases, moved to the status asked for.
 *
 * @param  list<string>  $words
 * @param  list<string>  $phrases
 */
function kindsReview(
    string $name,
    array $words,
    array $phrases,
    CustomReviewStatus $status = CustomReviewStatus::Draft,
): CustomReview {
    $review = CustomReview::factory()->create([
        'name' => $name,
        'status' => CustomReviewStatus::Empty,
    ]);

    if ($words !== []) {
        app(AddCustomReviewItems::class)->handle(
            $review,
            FeedbackType::Vocabulary,
            array_map(SearchText::normalize(...), $words),
        );
    }

    if ($phrases !== []) {
        app(AddCustomReviewItems::class)->handle(
            $review,
            FeedbackType::Phrase,
            array_map(SearchText::normalize(...), $phrases),
        );
    }

    if ($status === CustomReviewStatus::Finished) {
        app(SetCustomReviewStatus::class)->handle($review->refresh(), CustomReviewStatus::Finished);
    }

    return $review->refresh();
}

/**
 * The "Add to review" request, with the review chosen and the size answered.
 *
 * @param  list<string>  $identities
 */
function kindsAdd(
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

/**
 * The learner's own kinds choice, saved from the review's page.
 *
 * @param  list<string>  $kinds
 */
function kindsSet(object $test, CustomReview $review, array $kinds): TestResponse
{
    return $test->putJson(route('customReviews.kinds', $review), ['kinds' => $kinds]);
}

it('lets a new review hold every kind and says "All kinds" on its page and card', function (): void {
    $word = kindsSource(['cafe']);
    $phrase = kindsSource(['good morning'], FeedbackType::Phrase);

    $this->post(route('customReviews.store'))->assertRedirect();
    $review = CustomReview::query()->sole();

    expect($review->allowed_kinds)->toBeNull();

    // Nothing is narrowed, so a word and a phrase both go in.
    kindsAdd($this, FeedbackType::Vocabulary, $word['identities'], $review->id)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'added_count' => 1]);
    kindsAdd($this, FeedbackType::Phrase, $phrase['identities'], $review->id)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'added_count' => 1]);

    $this->get(route('customReviews.show', $review))->assertInertia(fn (Assert $page): Assert => $page
        ->where('review.allowed_kinds', null)
        ->where('review.allowed_kinds_label', 'All kinds')
    );

    $card = collect($this->getJson(route('customReviews.index'))->assertOk()->json('reviews'))
        ->firstWhere('id', $review->id);

    expect($card['allowed_kinds'])->toBeNull()
        ->and($card['allowed_kinds_label'])->toBe('All kinds');
});

it('shows the chosen kinds on the page and the card in the collections\' words', function (): void {
    $mixed = kindsReview('Mixed set', ['cafe'], ['good morning']);

    // Turning off kinds the review holds nothing of is always allowed.
    kindsSet($this, $mixed, ['vocabulary', 'phrase'])
        ->assertOk()
        ->assertJsonPath('review.allowed_kinds', ['vocabulary', 'phrase'])
        ->assertJsonPath('review.allowed_kinds_label', 'Words + Phrases');

    $this->get(route('customReviews.show', $mixed))->assertInertia(fn (Assert $page): Assert => $page
        ->where('review.allowed_kinds', ['vocabulary', 'phrase'])
        ->where('review.allowed_kinds_label', 'Words + Phrases')
    );

    $card = collect($this->getJson(route('customReviews.index'))->assertOk()->json('reviews'))
        ->firstWhere('id', $mixed->id);

    expect($card['allowed_kinds'])->toBe(['vocabulary', 'phrase'])
        ->and($card['allowed_kinds_label'])->toBe('Words + Phrases');

    $wordsOnly = kindsReview('Words only set', ['apple'], []);
    kindsSet($this, $wordsOnly, ['vocabulary'])
        ->assertOk()
        ->assertJsonPath('review.allowed_kinds', ['vocabulary'])
        ->assertJsonPath('review.allowed_kinds_label', 'Words only');

    $this->get(route('customReviews.show', $wordsOnly))->assertInertia(fn (Assert $page): Assert => $page
        ->where('review.allowed_kinds_label', 'Words only')
    );
});

it('refuses an item of a kind the review does not allow and names the kinds it does', function (): void {
    $phrases = kindsSource(['good morning'], FeedbackType::Phrase);

    $review = kindsReview('Words only set', ['apple'], []);
    kindsSet($this, $review, ['vocabulary'])->assertOk();

    kindsAdd($this, FeedbackType::Phrase, $phrases['identities'], $review->id)
        ->assertOk()
        ->assertJson([
            'outcome' => 'refused_kind',
            'message' => 'This review allows Words only.',
            'review' => ['item_count' => 1],
        ]);

    // Nothing was written, and a phrase never lands in the review.
    expect(CustomReviewItem::query()->where('custom_review_id', $review->id)->count())->toBe(1);

    // Answering the size warning never overrules the kinds.
    kindsAdd($this, FeedbackType::Phrase, $phrases['identities'], $review->id, true)
        ->assertOk()
        ->assertJson(['outcome' => 'refused_kind', 'message' => 'This review allows Words only.']);
});

it('refuses through the picker when the chosen review allows only words', function (): void {
    $phrases = kindsSource(['good morning'], FeedbackType::Phrase);

    $wordsOnly = kindsReview('Words only set', ['apple'], []);
    kindsSet($this, $wordsOnly, ['vocabulary'])->assertOk();

    $open = kindsReview('Open set', ['relish'], []);

    kindsAdd($this, FeedbackType::Phrase, $phrases['identities'])
        ->assertOk()
        ->assertJson(['outcome' => 'needs_review_choice']);

    kindsAdd($this, FeedbackType::Phrase, $phrases['identities'], $wordsOnly->id)
        ->assertOk()
        ->assertJson([
            'outcome' => 'refused_kind',
            'message' => 'This review allows Words only.',
        ]);

    // Another review that allows every kind still takes the phrase.
    kindsAdd($this, FeedbackType::Phrase, $phrases['identities'], $open->id)
        ->assertOk()
        ->assertJson(['outcome' => 'added', 'added_count' => 1]);
});

it('shows only the allowed kinds on the Collections + Add items view', function (): void {
    $mixed = kindsReview('Words and phrases', ['apple'], ['good morning']);
    kindsSet($this, $mixed, ['vocabulary', 'phrase'])->assertOk();

    $this->get(route('collections.index', [
        'review' => $mixed->id,
        'eligible' => 1,
    ]))->assertInertia(fn (Assert $page): Assert => $page
        ->has('tabs', 2)
        ->where('tabs.0.key', 'vocabulary')
        ->where('tabs.1.key', 'phrases')
        ->where('review_target.allowed_kinds_label', 'Words + Phrases')
    );

    $wordsOnly = kindsReview('Words only set', ['apple'], []);
    kindsSet($this, $wordsOnly, ['vocabulary'])->assertOk();

    $this->get(route('collections.index', [
        'review' => $wordsOnly->id,
        'eligible' => 1,
    ]))->assertInertia(fn (Assert $page): Assert => $page
        ->has('tabs', 1)
        ->where('tabs.0.key', 'vocabulary')
    );

    // The ordinary Collections view is unchanged and still offers all four.
    $this->get(route('collections.index'))->assertInertia(fn (Assert $page): Assert => $page
        ->has('tabs', 4)
    );
});

it('refuses turning off a kind the review still holds until its items are gone', function (): void {
    $review = kindsReview('Mixed set', ['cafe'], ['good morning', 'see you']);
    kindsSet($this, $review, ['vocabulary', 'phrase'])->assertOk();

    kindsSet($this, $review, ['vocabulary'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['kinds'])
        ->assertJsonPath('errors.kinds.0', 'Remove the 2 phrase items first.');

    expect($review->fresh()?->allowed_kinds)->toBe(['vocabulary', 'phrase']);

    // Turning a kind on is always allowed, held items or not.
    kindsSet($this, $review, ['vocabulary', 'phrase', 'grammar_note'])
        ->assertOk()
        ->assertJsonPath('review.allowed_kinds', ['vocabulary', 'phrase', 'grammar_note']);

    // Once the phrases are gone, the same narrowing goes through.
    $this->deleteJson(route('customReviews.items.destroy', $review), [
        'kind' => 'phrase',
        'identities' => ['good morning', 'see you'],
    ])->assertOk()->assertJson(['removed_count' => 2]);

    kindsSet($this, $review, ['vocabulary'])
        ->assertOk()
        ->assertJsonPath('review.allowed_kinds_label', 'Words only');
});

it('leaves past attempts and a running attempt untouched when the kinds change', function (): void {
    $words = kindsSource(['apple']);
    $phrases = kindsSource(['good morning'], FeedbackType::Phrase);

    $review = kindsReview(
        'Mixed set',
        $words['identities'],
        $phrases['identities'],
        CustomReviewStatus::Finished,
    );

    // Starting an attempt snapshots the word and the phrase it can carry.
    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();
    $attempt = CustomReviewAttempt::query()->where('custom_review_id', $review->id)->sole();

    $snapshot = fn (): array => CustomReviewAttemptItem::query()
        ->where('custom_review_attempt_id', $attempt->id)
        ->orderBy('position')
        ->pluck('kind')
        ->all();

    expect($snapshot())->toHaveCount(2)
        ->and($snapshot())->toContain(FeedbackType::Vocabulary)
        ->and($snapshot())->toContain(FeedbackType::Phrase);

    // Remove the phrase, then narrow the review to words only.
    $this->deleteJson(route('customReviews.items.destroy', $review), [
        'kind' => 'phrase',
        'identities' => $phrases['identities'],
    ])->assertOk()->assertJson(['removed_count' => 1]);

    kindsSet($this, $review, ['vocabulary'])
        ->assertOk()
        ->assertJsonPath('review.allowed_kinds', ['vocabulary']);

    // The running attempt keeps its snapshot exactly as it was, and still opens.
    expect($snapshot())->toHaveCount(2)
        ->and($snapshot())->toContain(FeedbackType::Vocabulary)
        ->and($snapshot())->toContain(FeedbackType::Phrase);

    $this->get(route('customReviews.attempts.show', [
        'customReview' => $review->id,
        'attempt' => $attempt->id,
    ]))->assertOk();
});
