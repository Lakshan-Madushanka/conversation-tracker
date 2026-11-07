<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomReviewStatus;
use App\Models\CustomReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomReview>
 */
class CustomReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Custom Review '.fake()->unique()->numberBetween(1, 50_000),
            'description' => null,
            'status' => CustomReviewStatus::Empty,
        ];
    }

    /** A review the learner has added items to. */
    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => CustomReviewStatus::Draft]);
    }

    /** A review the learner marked as ready. */
    public function finished(): static
    {
        return $this->state(fn (): array => ['status' => CustomReviewStatus::Finished]);
    }

    /** A review holding exactly one item, counted and ready to answer. */
    public function withItem(): static
    {
        return $this->state(fn (): array => ['status' => CustomReviewStatus::Draft])
            ->afterCreating(function (CustomReview $review): void {
                CustomReviewItemFactory::new()->create(['custom_review_id' => $review->id]);
            });
    }
}
