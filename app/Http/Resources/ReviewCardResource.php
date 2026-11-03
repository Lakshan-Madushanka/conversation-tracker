<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Support\EntrySearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One flashcard as the exercise screen shows it.
 *
 * The words are read from the session at the moment the card is shown, as
 * plain text, so an edit appears straight away and nothing the learner saved
 * can inject markup into the page.
 *
 * @mixin PracticeFeedbackEntry
 */
final class ReviewCardResource extends JsonResource
{
    public function __construct(PracticeFeedbackEntry $entry)
    {
        parent::__construct($entry);
    }

    /**
     * @return array{
     *     kind: string,
     *     item_identity: string,
     *     label: string,
     *     front: string,
     *     meaning: string,
     *     example: string,
     *     session_url: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var PracticeFeedbackEntry $entry */
        $entry = $this->resource;

        $session = $entry->practiceQuestion?->practiceSession;

        return [
            'kind' => $entry->type->value,
            'item_identity' => (string) $entry->collection_key,
            'label' => $entry->type === FeedbackType::Vocabulary ? 'Word' : 'Phrase',
            'front' => (string) $entry->content_text,
            'meaning' => (string) $entry->correction_text,
            'example' => EntrySearch::exampleText($entry),
            'session_url' => $session === null
                ? null
                : route('sessions.show', $session),
        ];
    }
}
