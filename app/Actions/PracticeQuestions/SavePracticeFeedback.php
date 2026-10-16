<?php

declare(strict_types=1);

namespace App\Actions\PracticeQuestions;

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Support\RichText;

/**
 * Replaces a question's feedback boxes with the submitted content. Each box
 * produces at most one entry in this step; an empty box produces none.
 */
final class SavePracticeFeedback
{
    /**
     * @param  array<string, mixed>  $feedback
     */
    public function handle(PracticeQuestion $question, array $feedback): void
    {
        foreach (PracticeFeedbackEntry::TYPES as $type) {
            $content = $feedback[$type] ?? '';
            $content = is_string($content) ? $content : '';

            $entry = $question->feedbackEntries()->firstOrNew([
                'type' => $type,
            ]);

            if (PracticeFeedbackEntry::isRich($type)) {
                $clean = RichText::cleanWithText($content);
                $entry->content_html = $clean['html'];
                $entry->content_text = $clean['text'];

                if ($clean['text'] === '') {
                    if ($entry->exists) {
                        $entry->delete();
                    }

                    continue;
                }

                $entry->save();

                continue;
            }

            $text = RichText::normalizePlainText($content);
            $entry->content_html = '';
            $entry->content_text = $text;

            if ($text === '') {
                if ($entry->exists) {
                    $entry->delete();
                }

                continue;
            }

            $entry->save();
        }
    }
}
