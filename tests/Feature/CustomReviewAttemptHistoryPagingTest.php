<?php

declare(strict_types=1);

use App\Enums\CustomReviewStatus;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A review holding the given number of completed attempts, oldest first.
 * Each attempt keeps one item so its row has something to read.
 */
function historyPagingReview(int $attempts): CustomReview
{
    $review = CustomReview::factory()->create([
        'name' => 'Long history',
        'status' => CustomReviewStatus::Finished,
    ]);

    foreach (range(1, $attempts) as $number) {
        $attempt = CustomReviewAttempt::factory()
            ->for($review, 'review')
            ->withNumber($number)
            ->completed()
            ->create(['started_at' => CarbonImmutable::now()->subDays($attempts + 1 - $number)]);

        CustomReviewAttemptItem::factory()
            ->for($attempt, 'attempt')
            ->create(['position' => 1]);
    }

    return $review;
}

/** The attempt holding the given number, as its review keeps it. */
function historyPagingAttempt(CustomReview $review, int $number): CustomReviewAttempt
{
    return CustomReviewAttempt::query()
        ->where('custom_review_id', $review->id)
        ->where('number', $number)
        ->sole();
}

it('shows the ten newest attempts and says more are waiting', function (): void {
    $review = historyPagingReview(12);

    $this->get(route('customReviews.show', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('attempts', 10)
            ->where('attempts.0.number', 12)
            ->where('attempts.9.number', 3)
            ->where('attempt_total', 12)
            ->where('attempts_next_page', 2)
        );

    // A history that fits on one page offers nothing more to load.
    $short = historyPagingReview(4);

    $this->get(route('customReviews.show', $short))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('attempts', 4)
            ->where('attempt_total', 4)
            ->where('attempts_next_page', null)
        );
});

it('loads the next page of attempts, newest first, without a page past the end', function (): void {
    $review = historyPagingReview(12);

    $this->getJson(route('customReviews.attempts.index', [
        'customReview' => $review,
        'page' => 2,
    ]))->assertOk()->assertJson([
        'next_page' => null,
        'total' => 12,
    ])->assertJsonCount(2, 'attempts');

    $second = $this->getJson(route('customReviews.attempts.index', [
        'customReview' => $review,
        'page' => 2,
    ]))->assertOk()->json('attempts');

    expect($second[0]['number'])->toBe(2)
        ->and($second[1]['number'])->toBe(1)
        ->and($second[0]['items'])->toHaveCount(1);

    $this->getJson(route('customReviews.attempts.index', [
        'customReview' => $review,
        'page' => 1,
    ]))->assertOk()->assertJson([
        'next_page' => 2,
        'total' => 12,
    ])->assertJsonCount(10, 'attempts');

    $this->getJson(route('customReviews.attempts.index', [
        'customReview' => $review,
        'page' => 3,
    ]))->assertOk()->assertJson([
        'next_page' => null,
        'total' => 12,
    ])->assertJsonCount(0, 'attempts');
});

it('keeps the expanded attempt open when it sits beyond the first page', function (): void {
    $review = historyPagingReview(12);
    $oldest = historyPagingAttempt($review, 1);

    $this->get(route('customReviews.show', [
        'customReview' => $review,
        'attempt' => $oldest->id,
    ]))->assertInertia(fn (Assert $page): Assert => $page
        ->has('attempts', 12)
        ->where('attempts.11.number', 1)
        ->where('expanded_attempt', $oldest->id)
        ->where('attempts_next_page', null)
    );
});

it('reads one page of history in a flat number of queries', function (): void {
    $review = historyPagingReview(12);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson(route('customReviews.attempts.index', [
        'customReview' => $review,
        'page' => 2,
    ]))->assertOk();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // The total, the page's attempts and their items — never one query per row.
    expect(count($queries))->toBeLessThanOrEqual(4);
});
