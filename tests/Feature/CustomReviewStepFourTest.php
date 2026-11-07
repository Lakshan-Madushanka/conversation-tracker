<?php

declare(strict_types=1);

use App\Actions\CustomReviews\AddCustomReviewItems;
use App\Actions\CustomReviews\SetCustomReviewStatus;
use App\Enums\CustomReviewAttemptStatus;
use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CollectionItemMark;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Topic;
use App\Queries\CustomReviewAttemptHistory;
use App\Support\CustomReviewAttemptLabel;
use App\Support\CustomReviewPractice;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A finished session holding the given words, ready to give a review items.
 *
 * @param  list<string>  $words
 * @return array{session: PracticeSession, identities: list<string>}
 */
function stepFourSource(array $words, bool $archived = false): array
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
            'archived_at' => $archived ? $at : null,
        ]);

    $question = PracticeQuestion::factory()->for($session)->create([
        'position' => 1,
    ]);

    $identities = [];

    foreach ($words as $position => $word) {
        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Vocabulary->value,
            'content_text' => $word,
            'correction_text' => 'the meaning of '.$word,
            'correction_html' => '<p>the meaning of '.e($word).'</p>',
            'position' => $position,
            'is_split' => true,
        ]);

        $identities[] = SearchText::normalize($word);
    }

    return ['session' => $session, 'identities' => $identities];
}

/**
 * @param  list<string>  $identities
 */
function stepFourReview(
    string $name,
    CustomReviewStatus $status,
    array $identities,
): CustomReview {
    $review = CustomReview::factory()->create([
        'name' => $name,
        'status' => CustomReviewStatus::Empty,
    ]);

    if ($identities !== []) {
        app(AddCustomReviewItems::class)->handle(
            $review,
            FeedbackType::Vocabulary,
            $identities,
        );
    }

    if ($status === CustomReviewStatus::Finished) {
        app(SetCustomReviewStatus::class)->handle(
            $review,
            CustomReviewStatus::Finished,
        );
    }

    return $review->refresh();
}

/** The body one rating request carries. */
function stepFourRating(string $word, string $rating = 'easy'): array
{
    return [
        'kind' => FeedbackType::Vocabulary->value,
        'item_identity' => SearchText::normalize($word),
        'rating' => $rating,
    ];
}

/** A Finished review with three usable words, started. */
function stepFourStarted(): array
{
    $source = stepFourSource(['apple', 'banana', 'cherry']);
    $review = stepFourReview('Ready set', CustomReviewStatus::Finished, $source['identities']);

    test()->post(route('customReviews.attempts.store', $review))->assertRedirect();

    $attempt = CustomReviewAttempt::query()->sole();

    return [$review, $attempt];
}

it('refuses to start a review that is not ready to practise', function (): void {
    $draftSource = stepFourSource(['apple']);
    $draft = stepFourReview('Draft set', CustomReviewStatus::Draft, $draftSource['identities']);

    $empty = stepFourReview('Empty set', CustomReviewStatus::Empty, []);

    $archivedSource = stepFourSource(['ghost'], archived: true);
    $unavailable = stepFourReview('Unavailable set', CustomReviewStatus::Draft, $archivedSource['identities']);
    app(SetCustomReviewStatus::class)->handle($unavailable, CustomReviewStatus::Finished);

    $this->post(route('customReviews.attempts.store', $draft))
        ->assertRedirect(route('customReviews.show', $draft))
        ->assertSessionHas('attempt_error', CustomReviewPractice::NEEDS_FINISHING);

    $this->post(route('customReviews.attempts.store', $empty))
        ->assertRedirect(route('customReviews.show', $empty))
        ->assertSessionHas('attempt_error', CustomReviewPractice::NEEDS_ITEMS);

    $this->post(route('customReviews.attempts.store', $unavailable))
        ->assertRedirect(route('customReviews.show', $unavailable))
        ->assertSessionHas('attempt_error', CustomReviewPractice::NOT_AVAILABLE);

    expect(CustomReviewAttempt::query()->count())->toBe(0);
});

it('starts an attempt at once with the next number and a snapshot of every usable item', function (): void {
    $source = stepFourSource(['apple', 'banana', 'cherry']);
    $review = stepFourReview('Ready set', CustomReviewStatus::Finished, $source['identities']);

    $this->post(route('customReviews.attempts.store', $review))
        ->assertRedirect();

    $attempt = CustomReviewAttempt::query()->sole();

    $this->assertDatabaseHas('custom_review_attempts', [
        'id' => $attempt->id,
        'custom_review_id' => $review->id,
        'number' => 1,
        'status' => CustomReviewAttemptStatus::InProgress->value,
    ]);

    expect($attempt->started_at)->not->toBeNull()
        ->and($attempt->finished_at)->toBeNull()
        ->and($attempt->items)->toHaveCount(3)
        ->and($attempt->items->pluck('snapshot_text')->all())
        ->toEqualCanonicalizing(['apple', 'banana', 'cherry'])
        ->and($attempt->items->every(
            fn (CustomReviewAttemptItem $item): bool => $item->snapshot['front'] === $item->snapshot_text,
        ))->toBeTrue()
        ->and($attempt->items->every(
            fn (CustomReviewAttemptItem $item): bool => $item->rating === null && $item->isSkipped() === false,
        ))->toBeTrue();
});

it('gives one attempt when Start is pressed twice', function (): void {
    $review = stepFourReview('Ready set', CustomReviewStatus::Finished, stepFourSource(['apple'])['identities']);

    $first = $this->post(route('customReviews.attempts.store', $review))->assertRedirect();
    $second = $this->post(route('customReviews.attempts.store', $review))->assertRedirect();

    $attempt = CustomReviewAttempt::query()->sole();

    expect($second->headers->get('Location'))
        ->toBe($first->headers->get('Location'))
        ->and(CustomReviewAttempt::query()->count())->toBe(1)
        ->and($attempt->items()->count())->toBe(1);
});

it('numbers every attempt ever started, and leaves Not started out of the count', function (): void {
    [$review, $first] = stepFourStarted();

    // A Not started shell has no number, so it neither takes one nor counts.
    CustomReviewAttempt::factory()
        ->for($review, 'review')
        ->notStarted()
        ->create();

    expect($first->number)->toBe(1);

    $this->post(
        route('customReviews.attempts.rate', ['customReview' => $review, 'attempt' => $first]),
        stepFourRating('apple'),
    )->assertRedirect();
    $this->post(
        route('customReviews.attempts.finishEarly', ['customReview' => $review, 'attempt' => $first]),
    )->assertRedirect();

    expect($first->refresh()->status)->toBe(CustomReviewAttemptStatus::FinishedEarly);

    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();

    $second = CustomReviewAttempt::query()->where('number', 2)->sole();

    expect($second->number)->toBe(2)
        ->and($second->id)->not->toBe($first->id);
});

it('resumes the attempt already running instead of starting another', function (): void {
    [$review, $attempt] = stepFourStarted();

    $this->post(route('customReviews.attempts.store', $review))
        ->assertRedirect(route('customReviews.attempts.show', [
            'customReview' => $review->id,
            'attempt' => $attempt->id,
        ]));

    expect(CustomReviewAttempt::query()->count())->toBe(1);
});

it('never cuts a running attempt short when the review status changes', function (): void {
    [$review, $attempt] = stepFourStarted();

    app(SetCustomReviewStatus::class)->handle($review, CustomReviewStatus::Draft);

    $this->get(route('customReviews.attempts.show', [
        'customReview' => $review,
        'attempt' => $attempt,
    ]))->assertOk();

    expect($attempt->refresh()->status)->toBe(CustomReviewAttemptStatus::InProgress);

    // Only a new start is blocked; the running attempt is still what Start
    // resumes, whatever the review has become since.
    $this->post(route('customReviews.attempts.store', $review))
        ->assertRedirect(route('customReviews.attempts.show', [
            'customReview' => $review->id,
            'attempt' => $attempt->id,
        ]));

    $this->post(
        route('customReviews.attempts.rate', ['customReview' => $review, 'attempt' => $attempt]),
        stepFourRating('apple'),
    )->assertRedirect();

    expect(CustomReviewAttempt::query()->count())->toBe(1);
});

it("shows the attempt on Review's exercise screen, labelled with its number", function (): void {
    [$review, $attempt] = stepFourStarted();

    $this->get(route('customReviews.attempts.show', [
        'customReview' => $review,
        'attempt' => $attempt,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('custom-reviews/Attempt')
            ->where('notFound', false)
            ->where('finished', false)
            ->where('title', 'Ready set · 1st attempt')
            ->where('attempt.number', 1)
            ->where('attempt.number_label', 'Attempt 1')
            ->where('attempt.status', 'in_progress')
            ->where('attempt.status_label', 'In progress')
            ->where('attempt.total', 3)
            ->where('attempt.rated', 0)
            ->where('attempt.skipped', 0)
            ->where('attempt.encouragement', CustomReviewAttemptLabel::ENCOURAGEMENT)
            ->where('position.current', 0)
            ->where('position.total', 3)
            ->where('sections.0.key', 'flashcard')
            ->where('sections.0.label', 'Flashcards')
            ->where('sections.0.total', 3)
            ->missing('sections.0.items')
            ->where('current.rating', null)
            ->where('current.step', 'flashcard')
            ->where('current.card.kind', 'vocabulary')
            ->where('current.card.exercise', 'flashcard')
            ->has('current.card.front')
            ->where('urls.rate', route('customReviews.attempts.rate', [
                'customReview' => $review->id,
                'attempt' => $attempt->id,
            ]))
        );
});

it('reads its cards from the snapshot, never from a newer version of the item', function (): void {
    $source = stepFourSource(['apple']);
    $review = stepFourReview('Ready set', CustomReviewStatus::Finished, $source['identities']);

    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();
    $attempt = CustomReviewAttempt::query()->sole();

    $entry = PracticeFeedbackEntry::query()
        ->where('type', FeedbackType::Vocabulary->value)
        ->where('collection_key', SearchText::normalize('apple'))
        ->sole();
    $entry->correction_text = 'a word about pears now';
    $entry->correction_html = '<p>a word about pears now</p>';
    $entry->save();

    $this->get(route('customReviews.attempts.show', [
        'customReview' => $review,
        'attempt' => $attempt,
    ]))->assertInertia(fn (Assert $page): Assert => $page
        ->where('current.card.front', 'apple')
        ->where('current.card.meaning', 'the meaning of apple')
    );
});

it('keeps every attempt rating on the attempt alone', function (): void {
    [$review, $attempt] = stepFourStarted();

    $this->post(
        route('customReviews.attempts.rate', ['customReview' => $review, 'attempt' => $attempt]),
        stepFourRating('apple', 'hard'),
    )->assertRedirect();

    $item = CustomReviewAttemptItem::query()
        ->where('custom_review_attempt_id', $attempt->id)
        ->where('item_identity', SearchText::normalize('apple'))
        ->sole();

    expect($item->rating)->toBe(ReviewRating::Hard)
        ->and($item->rated_at)->not->toBeNull()
        ->and(Rating::query()->count())->toBe(0)
        ->and(CollectionItemMark::query()->count())->toBe(0)
        ->and(PracticeFeedbackEntry::query()
            ->where('type', FeedbackType::Vocabulary->value)
            ->where('collection_key', SearchText::normalize('apple'))
            ->sole()
            ->correction_text)->toBe('the meaning of apple');
});

it('counts a repeated rating once and only the latest choice', function (): void {
    [$review, $attempt] = stepFourStarted();

    $rate = fn (string $rating) => $this->post(
        route('customReviews.attempts.rate', ['customReview' => $review, 'attempt' => $attempt]),
        stepFourRating('apple', $rating),
    );

    $rate('easy')->assertRedirect();
    $rate('easy')->assertRedirect();

    $item = CustomReviewAttemptItem::query()
        ->where('custom_review_attempt_id', $attempt->id)
        ->where('item_identity', SearchText::normalize('apple'))
        ->sole();

    expect($item->rating)->toBe(ReviewRating::Easy)
        ->and($item->skipped_at)->toBeNull();

    $rate('hard')->assertRedirect();

    expect($item->refresh()->rating)->toBe(ReviewRating::Hard);
});

it('takes a rating back, updates the counts and reopens a finished attempt', function (): void {
    [$review, $attempt] = stepFourStarted();

    foreach (['apple', 'banana', 'cherry'] as $word) {
        $this->post(
            route('customReviews.attempts.rate', ['customReview' => $review, 'attempt' => $attempt]),
            stepFourRating($word),
        )->assertRedirect();
    }

    expect($attempt->refresh()->status)->toBe(CustomReviewAttemptStatus::Completed);
    expect($attempt->finished_at)->not->toBeNull();

    $this->delete(
        route('customReviews.attempts.undo', ['customReview' => $review, 'attempt' => $attempt]),
        [
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => SearchText::normalize('apple'),
        ],
    )->assertRedirect();

    $attempt->refresh();

    expect($attempt->status)->toBe(CustomReviewAttemptStatus::InProgress)
        ->and($attempt->finished_at)->toBeNull()
        ->and($attempt->items()->where('rating', 'easy')->count())->toBe(2)
        ->and($attempt->items()->whereNull('rating')->whereNull('skipped_at')->count())->toBe(1);
});

it('completes an attempt when every item is rated or skipped', function (): void {
    $source = stepFourSource(['apple', 'banana']);
    $review = stepFourReview('Ready set', CustomReviewStatus::Finished, $source['identities']);

    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();
    $attempt = CustomReviewAttempt::query()->sole();

    $this->post(
        route('customReviews.attempts.rate', ['customReview' => $review, 'attempt' => $attempt]),
        stepFourRating('apple', 'easy'),
    )->assertRedirect();

    expect($attempt->refresh()->status)->toBe(CustomReviewAttemptStatus::InProgress);

    // A skipped item is settled too, so rating the other one ends the attempt.
    CustomReviewAttemptItem::query()
        ->where('custom_review_attempt_id', $attempt->id)
        ->where('item_identity', SearchText::normalize('banana'))
        ->sole()
        ->update(['skipped_at' => CarbonImmutable::now()]);

    $this->post(
        route('customReviews.attempts.rate', ['customReview' => $review, 'attempt' => $attempt]),
        stepFourRating('apple', 'easy'),
    )->assertRedirect();

    $attempt->refresh();

    expect($attempt->status)->toBe(CustomReviewAttemptStatus::Completed)
        ->and($attempt->finished_at)->not->toBeNull();

    $this->get(route('customReviews.attempts.show', [
        'customReview' => $review,
        'attempt' => $attempt,
    ]))->assertInertia(fn (Assert $page): Assert => $page
        ->where('finished', true)
        ->where('attempt.skipped', 1)
        ->where('counts.easy', 1)
        ->where('attempt.number_label', 'Attempt 1')
    );
});

it('finishes early only once something was rated, keeping what was given', function (): void {
    [$review, $attempt] = stepFourStarted();

    $finish = fn () => $this->post(
        route('customReviews.attempts.finishEarly', ['customReview' => $review, 'attempt' => $attempt]),
    );

    $finish()->assertRedirect();

    expect($attempt->refresh()->status)->toBe(CustomReviewAttemptStatus::InProgress)
        ->and($attempt->finished_at)->toBeNull();

    $this->post(
        route('customReviews.attempts.rate', ['customReview' => $review, 'attempt' => $attempt]),
        stepFourRating('apple', 'okay'),
    )->assertRedirect();

    $finish()->assertRedirect();

    $attempt->refresh();

    expect($attempt->status)->toBe(CustomReviewAttemptStatus::FinishedEarly)
        ->and($attempt->finished_at)->not->toBeNull()
        ->and($attempt->items()->whereNotNull('rating')->count())->toBe(1);

    $this->get(route('customReviews.attempts.show', [
        'customReview' => $review,
        'attempt' => $attempt,
    ]))->assertInertia(fn (Assert $page): Assert => $page
        ->where('finished', true)
        ->where('attempt.status', 'finished_early')
        ->where('attempt.status_label', 'Finished early')
    );
});

it('lists every attempt newest first, from its snapshots and its own counts', function (): void {
    [$review, $first] = stepFourStarted();

    $this->post(
        route('customReviews.attempts.rate', ['customReview' => $review, 'attempt' => $first]),
        stepFourRating('apple', 'hard'),
    )->assertRedirect();

    $this->post(
        route('customReviews.attempts.rate', ['customReview' => $review, 'attempt' => $first]),
        stepFourRating('banana', 'easy'),
    )->assertRedirect();

    $this->post(
        route('customReviews.attempts.finishEarly', ['customReview' => $review, 'attempt' => $first]),
    )->assertRedirect();

    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();

    $second = CustomReviewAttempt::query()->whereKeyNot($first->id)->sole();

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('custom-reviews/Show')
            ->has('attempts', 2)
            ->where('attempts.0.id', $second->id)
            ->where('attempts.0.number', 2)
            ->where('attempts.0.number_label', 'Attempt 2')
            ->where('attempts.0.status', 'in_progress')
            ->where('attempts.0.status_label', 'In progress')
            ->has('attempts.0.started_at')
            ->where('attempts.0.finished_at', null)
            ->where('attempts.0.counts.rated', 0)
            ->has('attempts.0.items', 3)
            ->where('attempts.0.items.0.text', fn (mixed $text): bool => is_string($text) && $text !== '')->where('attempts.1.id', $first->id)
            ->where('attempts.1.number', 1)
            ->where('attempts.1.status', 'finished_early')
            ->where('attempts.1.counts.hard', 1)
            ->where('attempts.1.counts.easy', 1)
            ->where('attempts.1.counts.rated', 2)
            ->where('attempts.1.counts.unrated', 1)
            ->where('attempts.1.items', fn (Collection $items): bool => $items->count() === 3
                && $items->where('state', 'rated')->count() === 2
                && $items->where('state', 'not_rated')->count() === 1)
            ->where('expanded_attempt', null)
        );
});

it('keeps the expanded attempt only when it belongs to this review', function (): void {
    [$review, $attempt] = stepFourStarted();

    $this->get(route('customReviews.show', ['customReview' => $review, 'attempt' => $attempt->id]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('expanded_attempt', $attempt->id)
        );

    $other = stepFourReview('Other set', CustomReviewStatus::Empty, []);

    $this->get(route('customReviews.show', ['customReview' => $review, 'attempt' => 999999]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('expanded_attempt', null)
        );

    $this->get(route('customReviews.show', ['customReview' => $other, 'attempt' => $attempt->id]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('expanded_attempt', null)
        );
});

it("shows each history item's snapshot words and a way to its session", function (): void {
    $source = stepFourSource(['apple']);
    $review = stepFourReview('Ready set', CustomReviewStatus::Finished, $source['identities']);

    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();
    $attempt = CustomReviewAttempt::query()->sole();

    $this->get(route('customReviews.show', $review))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('attempts.0.items.0.text', 'apple')
            ->where('attempts.0.items.0.kind', 'vocabulary')
            ->where('attempts.0.items.0.kind_label', 'Words')
            ->where('attempts.0.items.0.state', 'not_rated')
            ->where('attempts.0.items.0.rating', null)
            ->where('attempts.0.items.0.rating_label', null)
            ->where('attempts.0.items.0.href', fn (mixed $href): bool => is_string($href)
                && str_contains($href, '/sessions/'.$source['session']->id))
        );
});

it('keeps a Not started attempt out of the card figures, and the system clears an empty shell', function (): void {
    $source = stepFourSource(['apple']);
    $review = stepFourReview('Ready set', CustomReviewStatus::Finished, $source['identities']);

    $shell = CustomReviewAttempt::factory()
        ->for($review, 'review')
        ->notStarted()
        ->create();

    $this->get(route('customReviews.show', $review))->assertInertia(fn (Assert $page): Assert => $page
        ->has('attempts', 0)
    );

    expect(CustomReviewAttempt::query()->whereKey($shell->id)->exists())->toBeFalse();

    // A Not started attempt holding its items is kept and listed, still
    // without a number or a place in the card's count.
    $kept = CustomReviewAttempt::factory()
        ->for($review, 'review')
        ->notStarted()
        ->create();
    CustomReviewAttemptItem::factory()
        ->for($kept, 'attempt')
        ->create();

    $this->get(route('customReviews.show', $review))->assertInertia(fn (Assert $page): Assert => $page
        ->has('attempts', 1)
        ->where('attempts.0.number', null)
        ->where('attempts.0.number_label', 'Not started')
        ->where('attempts.0.status', 'not_started')
    );

    $card = collect($this->getJson(route('customReviews.index'))->assertOk()->json('reviews'))
        ->firstWhere('id', $review->id);

    expect($card['attempt_count'])->toBe(0)
        ->and($card['has_attempts'])->toBeFalse()
        ->and($card['view_history_url'])->toBeNull();
});

it('describes the latest attempt on the card and offers the next action', function (): void {
    [$review, $first] = stepFourStarted();

    $this->post(
        route('customReviews.attempts.rate', ['customReview' => $review, 'attempt' => $first]),
        stepFourRating('apple'),
    )->assertRedirect();
    $this->post(
        route('customReviews.attempts.finishEarly', ['customReview' => $review, 'attempt' => $first]),
    )->assertRedirect();

    $cardOf = fn (): array => collect($this->getJson(route('customReviews.index'))->assertOk()->json('reviews'))
        ->firstWhere('id', $review->id);

    $card = $cardOf();

    expect($card['attempt_count'])->toBe(1)
        ->and($card['has_attempts'])->toBeTrue()
        ->and($card['in_progress'])->toBeFalse()
        ->and($card['attempt_number_label'])->toBe('Attempt 1')
        ->and($card['previous_attempt_status'])->toBeNull()
        ->and($card['attempt_summary_label'])->toStartWith('1 attempt · last on ')
        ->and($card['start_label'])->toBe('Start practice · 3 items')
        ->and($card['start_url'])->toBe(route('customReviews.attempts.store', $review))
        ->and($card['resume_url'])->toBeNull()
        ->and($card['practice_note'])->toBeNull()
        ->and($card['view_history_url'])->toEndWith('#attempts');

    $this->post(route('customReviews.attempts.store', $review))->assertRedirect();

    $second = CustomReviewAttempt::query()->whereKeyNot($first->id)->sole();
    $card = $cardOf();

    expect($card['attempt_count'])->toBe(2)
        ->and($card['in_progress'])->toBeTrue()
        ->and($card['attempt_number_label'])->toBe('Attempt 2')
        ->and($card['previous_attempt_status'])->toBe('finished_early')
        ->and($card['previous_attempt_label'])->toBe('Previous: Finished early')
        ->and($card['attempt_summary_label'])->toBe(CustomReviewAttemptLabel::IN_PROGRESS)
        ->and($card['start_label'])->toBeNull()
        ->and($card['start_url'])->toBeNull()
        ->and($card['resume_url'])->toBe(route('customReviews.attempts.show', [
            'customReview' => $review->id,
            'attempt' => $second->id,
        ]));
});

it('never names an In progress attempt as the previous one', function (): void {
    $review = stepFourReview('Ready set', CustomReviewStatus::Finished, stepFourSource(['apple'])['identities']);

    $older = CustomReviewAttempt::factory()
        ->for($review, 'review')
        ->withNumber(1)
        ->completed()
        ->create();
    CustomReviewAttemptItem::factory()->for($older, 'attempt')->rated()->create();

    CustomReviewAttempt::factory()
        ->for($review, 'review')
        ->withNumber(2)
        ->create(['status' => CustomReviewAttemptStatus::InProgress]);

    $card = collect($this->getJson(route('customReviews.index'))->assertOk()->json('reviews'))
        ->firstWhere('id', $review->id);

    expect($card['previous_attempt_status'])->toBe('completed')
        ->and($card['previous_attempt_label'])->toBe('Previous: Completed')
        ->and($card['attempt_summary_label'])->toBe(CustomReviewAttemptLabel::IN_PROGRESS);
});

it('never opens an attempt under a review it does not belong to', function (): void {
    [$review, $attempt] = stepFourStarted();
    $other = stepFourReview('Other set', CustomReviewStatus::Finished, stepFourSource(['delta'])['identities']);

    $this->get(route('customReviews.attempts.show', [
        'customReview' => $other,
        'attempt' => $attempt,
    ]))->assertNotFound();

    $this->post(route('customReviews.attempts.rate', [
        'customReview' => $other,
        'attempt' => $attempt,
    ]), stepFourRating('apple'))->assertNotFound();

    $this->post(route('customReviews.attempts.finishEarly', [
        'customReview' => $other,
        'attempt' => $attempt,
    ]))->assertNotFound();

    expect($attempt->refresh()->status)->toBe(CustomReviewAttemptStatus::InProgress)
        ->and($attempt->items()->whereNotNull('rating')->count())->toBe(0);
});

it('reads a whole attempt history in two queries however long it is', function (): void {
    $review = CustomReview::factory()->create([
        'name' => 'Long history',
        'status' => CustomReviewStatus::Finished,
    ]);

    foreach (range(1, 12) as $number) {
        $attempt = CustomReviewAttempt::factory()
            ->for($review, 'review')
            ->withNumber($number)
            ->completed()
            ->create(['started_at' => CarbonImmutable::now()->subDays(13 - $number)]);

        foreach (range(1, 5) as $position) {
            CustomReviewAttemptItem::factory()
                ->for($attempt, 'attempt')
                ->create(['position' => $position]);
        }
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $history = app(CustomReviewAttemptHistory::class)->for($review);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($history)->toHaveCount(12)
        ->and($queries)->toHaveCount(2)
        ->and($history[0]['attempt']->number)->toBe(12)
        ->and($history[11]['attempt']->number)->toBe(1);
});
