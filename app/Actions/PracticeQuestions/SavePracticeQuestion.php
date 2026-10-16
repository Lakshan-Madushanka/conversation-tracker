<?php

declare(strict_types=1);

namespace App\Actions\PracticeQuestions;

use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Support\RichText;

/**
 * Saves the one question a session starts with. Later steps add more questions,
 * so the first question is looked up by its position instead of being created
 * again on every save.
 */
final class SavePracticeQuestion
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PracticeSession $session, array $data): PracticeQuestion
    {
        $question = $session->questions()->firstOrNew(['position' => 1]);

        $questionContent = RichText::cleanWithText((string) ($data['question_html'] ?? ''));
        $answerContent = RichText::cleanWithText((string) ($data['answer_html'] ?? ''));

        $question->question_html = $questionContent['html'];
        $question->question_text = $questionContent['text'];
        $question->answer_html = $answerContent['html'];
        $question->answer_text = $answerContent['text'];
        $question->save();

        return $question;
    }
}
