<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The session shaped for the session editor and the reading page.
 *
 * Every list box and the grammar notes arrive as a list of entries, each with
 * the id the editor saved it under, so a later save can update the same entry
 * instead of adding another. The natural version stays one piece of
 * formatted text.
 *
 * @mixin PracticeSession
 */
final class SessionEditorResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     status: string,
     *     session_date: string|null,
     *     finished_at: string|null,
     *     last_changed_at: string|null,
     *     archived_at: string|null,
     *     version: int,
     *     topic: array{id: int, name: string},
     *     questions: array<int, array{
     *         id: int,
     *         position: int,
     *         question_html: string,
     *         answer_html: string,
     *         feedback: array<string, list<array{id: string, text: string, correction_html: string, reason: string}|array{id: string, text: string, meaning: string, example_html: string|list<array{id: string, text: string, html: string}>}|array{id: string, html: string}>|string>
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var PracticeSession $session */
        $session = $this->resource;

        $session->loadMissing(['topic', 'questions.feedbackEntries']);

        return [
            'id' => $session->id,
            'status' => $session->status,
            'session_date' => $session->sessionDate()?->toIso8601String(),
            'finished_at' => $session->finished_at?->toIso8601String(),
            'last_changed_at' => $session->last_changed_at?->toIso8601String(),
            'archived_at' => $session->archived_at?->toIso8601String(),
            'version' => $session->version,
            'topic' => [
                'id' => $session->topic->id,
                'name' => $session->topic->name,
            ],
            'questions' => $session->questions
                ->map(fn (PracticeQuestion $question): array => [
                    'id' => $question->id,
                    'position' => $question->position,
                    'question_html' => $question->question_html,
                    'answer_html' => $question->answer_html,
                    'feedback' => $question->feedbackEntries
                        ->groupBy(fn (PracticeFeedbackEntry $entry): string => $entry->type->value)
                        ->map(fn (Collection $entries, string $type): mixed => $this->feedbackPayload(FeedbackType::from($type), $entries))
                        ->all(),
                ])
                ->all(),
        ];
    }

    /**
     * One box shaped for the editor: table rows with their columns, one
     * formatted entry per grammar note, and the raw text for the natural
     * version.
     *
     * @param  Collection<int, PracticeFeedbackEntry>  $entries
     * @return list<array{id: string, text: string, correction_html: string, reason: string}|array{id: string, text: string, meaning: string, example_html: string|list<array{id: string, text: string, html: string}>}|array{id: string, html: string}>|string
     */
    private function feedbackPayload(FeedbackType $type, Collection $entries): array|string
    {
        if ($type === FeedbackType::NaturalVersion) {
            return $entries->first()?->editableContent() ?? '';
        }

        $payload = [];

        foreach ($entries as $entry) {
            $id = $entry->client_id ?? '';

            if ($type === FeedbackType::Mistake) {
                $payload[] = [
                    'id' => $id,
                    'text' => $entry->content_text,
                    'correction_html' => $entry->correction_html,
                    'reason' => $entry->reason,
                ];

                continue;
            }

            if ($type->isTable()) {
                $payload[] = [
                    'id' => $id,
                    'text' => $entry->content_text,
                    'meaning' => $entry->correction_text,
                    'example_html' => $entry->example_html,
                ];

                continue;
            }

            $payload[] = ['id' => $id, 'html' => $entry->content_html];
        }

        return $payload;
    }
}
