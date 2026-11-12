<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FeedbackType;
use App\Models\Notebook;
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
     *     source_kind: 'session'|'notebook',
     *     source_label?: string|null,
     *     session_url: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var PracticeFeedbackEntry $entry */
        $entry = $this->resource;

        $session = $entry->practiceQuestion?->practiceSession;
        $notebook = $entry->notebook_id === null ? null : $entry->notebook;

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
            'source_kind' => $notebook instanceof Notebook ? 'notebook' : 'session',
            'source_label' => $notebook instanceof Notebook
                ? $this->notebookLabel($notebook)
                : null,
            'session_url' => $notebook instanceof Notebook
                ? $this->notebookUrl($entry, $notebook)
                : ($session === null ? null : route('sessions.show', $session)),
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
     *     source_kind: 'session'|'notebook',
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
            'source_kind' => 'session',
            'session_url' => $sessionUrl,
        ];
    }

    /** The notebook whose entry this card came from, with its shared topic. */
    private function notebookLabel(Notebook $notebook): string
    {
        $topic = (string) $notebook->topic->name;

        return $topic === '' ? $notebook->name : $notebook->name.' · '.$topic;
    }

    /** The notebook board opened at this saved row. */
    private function notebookUrl(PracticeFeedbackEntry $entry, Notebook $notebook): string
    {
        $query = [
            'from' => 'review',
            'field' => 'entry',
            'type' => $entry->type->value,
            'position' => (string) $entry->position,
        ];

        if ($entry->client_id !== null && $entry->client_id !== '') {
            $query['entry'] = $entry->client_id;
        }

        return route('notebooks.show', ['notebook' => $notebook->id, ...$query]);
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
