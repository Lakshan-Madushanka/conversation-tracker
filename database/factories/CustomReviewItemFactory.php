<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Models\CustomReviewItem;
use App\Support\MistakeIdentity;
use App\Support\SearchText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomReviewItem>
 */
class CustomReviewItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custom_review_id' => CustomReview::factory(),
            'kind' => FeedbackType::Vocabulary,
            'item_identity' => SearchText::normalize(fake()->unique()->word()),
            'added_at' => now(),
        ];
    }

    /** A phrase rather than a word; the identity rule is the same. */
    public function phrase(string $text): static
    {
        return $this->state(fn (): array => [
            'kind' => FeedbackType::Phrase,
            'item_identity' => SearchText::normalize($text),
        ]);
    }

    /** A grammar note, named by the saved row's id. */
    public function note(int $noteId): static
    {
        return $this->state(fn (): array => [
            'kind' => FeedbackType::GrammarNote,
            'item_identity' => 'note-'.$noteId,
        ]);
    }

    /** A mistake, named by the opaque key of its pair. */
    public function mistake(string $said, string $correction): static
    {
        return $this->state(fn (): array => [
            'kind' => FeedbackType::Mistake,
            'item_identity' => MistakeIdentity::pairKey($said, $correction),
        ]);
    }

    /** A question from a session, named by its own id. */
    public function question(int $questionId): static
    {
        return $this->state(fn (): array => [
            'kind' => FeedbackType::Question,
            'item_identity' => (string) $questionId,
        ]);
    }
}
