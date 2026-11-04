<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeSession;
use App\Support\EntrySearch;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One exercise card as the review screen shows it.
 *
 * The words are read from the session at the moment the card is shown, so an
 * edit appears straight away and nothing the learner saved can inject markup
 * into the page. A word or phrase keeps the flashcard shape it always had; a
 * mistake shows what was said, its safe formatted correction and the most
 * recent reason, with the older distinct reasons kept for "More notes".
 *
 * @mixin PracticeFeedbackEntry
 */
final class ReviewCardResource extends JsonResource
{
    /**
     * @param  list<string>  $reasons  newest first, distinct
     */
    public function __construct(PracticeFeedbackEntry $entry, public readonly array $reasons = [])
    {
        parent::__construct($entry);
    }

    /**
     * @return array{
     *     kind: string,
     *     exercise: string,
     *     item_identity: string,
     *     label: string,
     *     front?: string,
     *     meaning?: string,
     *     example?: string,
     *     said?: string,
     *     correction_html?: string,
     *     reason?: string|null,
     *     more_reasons?: list<string>,
     *     session_url: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var PracticeFeedbackEntry $entry */
        $entry = $this->resource;

        $session = $entry->practiceQuestion?->practiceSession;

        if ($entry->type === FeedbackType::Mistake) {
            return $this->mistake($entry, $session === null ? null : $this->sessionUrl($entry, $session));
        }

        return [
            'kind' => $entry->type->value,
            'exercise' => 'flashcard',
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

    /**
     * @return array{
     *     kind: string,
     *     exercise: string,
     *     item_identity: string,
     *     label: string,
     *     said: string,
     *     correction_html: string,
     *     reason: string|null,
     *     more_reasons: list<string>,
     *     session_url: string|null
     * }
     */
    private function mistake(PracticeFeedbackEntry $entry, ?string $sessionUrl): array
    {
        $correction = RichText::cleanInlineWithText((string) $entry->correction_html)['html'];

        if ($correction === '') {
            $correction = htmlspecialchars(
                (string) $entry->correction_text,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            );
        }

        return [
            'kind' => FeedbackType::Mistake->value,
            'exercise' => 'mistake',
            'item_identity' => (string) $entry->collection_key,
            'label' => 'Fix the mistake',
            'said' => (string) $entry->content_text,
            'correction_html' => $correction,
            'reason' => $this->reasons[0] ?? null,
            'more_reasons' => array_slice($this->reasons, 1),
            'session_url' => $sessionUrl,
        ];
    }

    /**
     * The reading page opened at this exact row, reusing its search-target
     * behaviour so the row is highlighted, scrolled to and focused.
     */
    private function sessionUrl(PracticeFeedbackEntry $entry, PracticeSession $session): string
    {
        $query = [
            'from' => 'collections',
            'question' => (string) $entry->practiceQuestion?->position,
            'field' => 'entry',
            'type' => FeedbackType::Mistake->value,
            'position' => (string) $entry->position,
        ];

        if ($entry->client_id !== null && $entry->client_id !== '') {
            $query['entry'] = $entry->client_id;
        }

        return route('sessions.show', ['session' => $session->id, ...$query]);
    }
}
