<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomReviewAttemptStatus;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomReviewAttempt>
 */
class CustomReviewAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custom_review_id' => CustomReview::factory(),
            'number' => 1,
            'status' => CustomReviewAttemptStatus::InProgress,
            'started_at' => now(),
            'finished_at' => null,
        ];
    }

    public function withNumber(int $number): static
    {
        return $this->state(fn (): array => ['number' => $number]);
    }

    public function notStarted(): static
    {
        return $this->state(fn (): array => [
            'number' => null,
            'status' => CustomReviewAttemptStatus::NotStarted,
            'started_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => CustomReviewAttemptStatus::Completed,
            'finished_at' => now(),
        ]);
    }

    public function finishedEarly(): static
    {
        return $this->state(fn (): array => [
            'status' => CustomReviewAttemptStatus::FinishedEarly,
            'finished_at' => now(),
        ]);
    }
}
