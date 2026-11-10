<?php

declare(strict_types=1);

use App\Models\CustomReview;
use App\Models\CustomReviewItem;

it('finds the review an item belongs to', function (): void {
    $review = CustomReview::factory()->create();
    $item = CustomReviewItem::factory()->create([
        'custom_review_id' => $review->id,
    ]);

    expect($item->review?->is($review))->toBeTrue();
});
