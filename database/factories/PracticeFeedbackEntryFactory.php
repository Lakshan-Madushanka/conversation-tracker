<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'type' => PracticeFeedbackEntry::TYPE_MISTAKE,
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
            'type' => PracticeFeedbackEntry::TYPE_GRAMMAR_NOTE,
            'content_html' => '<p>Use the <strong>past tense</strong> here.</p>',
            'content_text' => 'Use the past tense here.',
        ]);
    }
}
