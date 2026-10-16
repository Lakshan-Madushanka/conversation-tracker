<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PracticeQuestion>
 */
class PracticeQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'practice_session_id' => PracticeSession::factory(),
            'position' => 1,
            'question_html' => '<p>Sample question</p>',
            'question_text' => 'Sample question',
            'answer_html' => '<p>Sample answer</p>',
            'answer_text' => 'Sample answer',
        ];
    }
}
