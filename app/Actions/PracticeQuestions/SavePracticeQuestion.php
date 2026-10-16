<?php

declare(strict_types=1);

namespace App\Actions\PracticeQuestions;

use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Support\RichText;

/**
 * Saves a single question for a session (identified by its position) and
 * persists its feedback boxes.
 */
final readonly class SavePracticeQuestion
{
    public function __construct(
        private SavePracticeFeedback $savePracticeFeedback,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PracticeSession $session, int $position, array $data): PracticeQuestion
    {
        $question = $session->questions()->firstOrNew(['position' => $position]);

        $questionContent = RichText::cleanWithText((string) ($data['question_html'] ?? ''));
        $answerContent = RichText::cleanWithText((string) ($data['answer_html'] ?? ''));

        $question->question_html = $questionContent['html'];
        $question->question_text = $questionContent['text'];
        $question->answer_html = $answerContent['html'];
        $question->answer_text = $answerContent['text'];
        $question->save();

        $feedback = $data['feedback'] ?? [];
        $this->savePracticeFeedback->handle($question, is_array($feedback) ? $feedback : []);

        return $question;
    }
}
