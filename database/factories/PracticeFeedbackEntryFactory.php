<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PracticeFeedbackEntry>
 */
class PracticeFeedbackEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'practice_question_id' => PracticeQuestion::factory(),
            'type' => FeedbackType::Mistake,
            'content_html' => '',
            'content_text' => 'said → told',
        ];
    }

    /**
     * A formatted entry, as used by grammar notes and the natural version.
     */
    public function rich(): static
    {
        return $this->state(fn (): array => [
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p>Use the <strong>past tense</strong> here.</p>',
            'content_text' => 'Use the past tense here.',
        ]);
    }
}
