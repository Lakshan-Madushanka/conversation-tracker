<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One fill-in-the-blank card as the review screen shows it.
 *
 * The blank is built from the entry's current text on the server, so an edit
 * shows straight away and the choice is never saved. The HTML is already safe
 * from `BlankExercise`; the learner's typed answer never reaches this class.
 *
 * @mixin PracticeFeedbackEntry
 */
final class FillInTheBlankCardResource extends JsonResource
{
    /**
     * @param  array{
     *     prompt_html: string,
     *     answer_html: string,
     *     answer_text: string,
     *     hint: string|null,
     *     hint_label: string|null
     * }  $blank
     */
    public function __construct(PracticeFeedbackEntry $entry, public readonly array $blank)
    {
        parent::__construct($entry);
    }

    /**
     * @return array{
     *     kind: string,
     *     exercise: 'fill_in_the_blank',
     *     item_identity: string,
     *     label: string,
     *     prompt_html: string,
     *     answer_html: string,
     *     answer_text: string,
     *     hint: string|null,
     *     hint_label: string|null,
     *     source_kind: 'session'|'notebook',
     *     source_label: string|null,
     *     session_url: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var PracticeFeedbackEntry $entry */
        $entry = $this->resource;

        $session = $entry->practiceQuestion?->practiceSession;
        $notebook = $entry->notebook_id === null ? null : $entry->notebook;

        return [
            'kind' => $entry->type->value,
            'exercise' => 'fill_in_the_blank',
            'item_identity' => (string) $entry->collection_key,
            'label' => 'Fill in the blank',
            'prompt_html' => $this->blank['prompt_html'],
            'answer_html' => $this->blank['answer_html'],
            'answer_text' => $this->blank['answer_text'],
            'hint' => $this->blank['hint'],
            'hint_label' => $this->blank['hint_label'],
            'source_kind' => $notebook instanceof Notebook ? 'notebook' : 'session',
            'source_label' => $notebook instanceof Notebook
                ? $this->notebookLabel($notebook)
                : null,
            'session_url' => $notebook instanceof Notebook
                ? $this->notebookUrl($entry, $notebook)
                : ($session instanceof PracticeSession ? $this->sessionUrl($entry, $session) : null),
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
            'type' => $entry->type->value,
            'position' => (string) $entry->position,
        ];

        if ($entry->client_id !== null && $entry->client_id !== '') {
            $query['entry'] = $entry->client_id;
        }

        return route('sessions.show', ['session' => $session->id, ...$query]);
    }
}
