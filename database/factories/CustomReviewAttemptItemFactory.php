<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomReviewAttemptItem>
 */
class CustomReviewAttemptItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();
        $identity = $word;

        return [
            'custom_review_attempt_id' => CustomReviewAttempt::factory(),
            'position' => 1,
            'kind' => FeedbackType::Vocabulary,
            'exercise' => 'flashcard',
            'item_identity' => $identity,
            'snapshot_text' => $word,
            'snapshot' => [
                'kind' => FeedbackType::Vocabulary->value,
                'exercise' => 'flashcard',
                'item_identity' => $identity,
                'label' => 'Word',
                'front' => $word,
                'meaning' => 'the meaning of '.$word,
                'example' => null,
                'session_url' => null,
            ],
            'topic' => 'Ordering coffee',
            'session_date' => now(),
            'source_session_id' => null,
            'source_url' => null,
            'snapshot_taken_at' => now(),
        ];
    }

    public function rated(ReviewRating $rating = ReviewRating::Okay): static
    {
        return $this->state(fn (): array => [
            'rating' => $rating,
            'rated_at' => now(),
            'rating_key' => 'attempt-rating-'.hash('sha1', fake()->unique()->uuid()),
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn (): array => ['skipped_at' => now()]);
    }
}
