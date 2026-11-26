<?php

declare(strict_types=1);

namespace App\Actions\PracticeQuestions;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Support\RichText;
use Carbon\CarbonImmutable;

/**
 * Saves a single question for a session (identified by its position), the
 * learner's answer, the tutor's overall remark about it, and its feedback
 * boxes.
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
        $feedbackContent = RichText::cleanWithText((string) ($data['feedback_html'] ?? ''));

        $question->question_html = $questionContent['html'];
        $question->question_text = $questionContent['text'];
        $question->answer_html = $answerContent['html'];
        $question->answer_text = $answerContent['text'];
        // The tutor's overall remark about the answer. It is not one of the
        // feedback entries saved below.
        $question->feedback_html = $feedbackContent['html'];
        $question->feedback_text = $feedbackContent['text'];
        $question->save();

        $feedback = $data['feedback'] ?? [];
        $this->savePracticeFeedback->handle($question, $session, is_array($feedback) ? $feedback : []);

        $this->stampCompletedConversation($question);

        return $question;
    }

    /**
     * Records the moment the question first became a complete conversation: it
     * holds an answer and at least one natural version. The moment is set once
     * and never moved, so a later edit leaves its practice day where it is.
     */
    private function stampCompletedConversation(PracticeQuestion $question): void
    {
        if ($question->completed_at !== null) {
            return;
        }

        $hasNaturalVersion = $question->feedbackEntries()
            ->where('type', FeedbackType::NaturalVersion->value)
            ->get()
            ->contains(fn (PracticeFeedbackEntry $entry): bool => ! $entry->isBlank());

        if (trim($question->answer_text) === '' || ! $hasNaturalVersion) {
            return;
        }

        $question->completed_at = CarbonImmutable::now();
        $question->save();
    }
}
