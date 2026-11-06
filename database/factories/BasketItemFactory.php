<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FeedbackType;
use App\Models\BasketItem;
use App\Support\SearchText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BasketItem>
 */
class BasketItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => FeedbackType::Vocabulary,
            'item_identity' => SearchText::normalize(fake()->unique()->word()),
            'added_at' => now(),
        ];
    }

    /** One question from a session, named by its own id. */
    public function question(int $questionId): static
    {
        return $this->state(fn (): array => [
            'kind' => FeedbackType::Question,
            'item_identity' => (string) $questionId,
        ]);
    }
}
