<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Support\CustomReviewEligibility;
use App\Support\NotebookItemContent;
use App\Support\NotePreview;

/**
 * The items one notebook can offer a custom review, by the scoped identities
 * the review will store.
 *
 * A word or phrase is one candidate however many saved rows carry it; a
 * grammar note is always its own candidate. The content shown is resolved by
 * `NotebookItemContent` in the board's saved order, so the same notebook
 * always offers the same words, and a meaning or example is never borrowed
 * from a matching session or another notebook. Every row in this notebook is
 * read at once, because the selection screen shows them all.
 *
 * The learner's kinds choice narrows the list; it is never inferred from the
 * boxes that happen to have content.
 *
 * @phpstan-type NotebookReviewCandidate array{
 *     kind: string,
 *     identity: string,
 *     title: string,
 *     meaning: string,
 *     example: string,
 *     eligible: bool,
 *     reason: string|null
 * }
 */
final readonly class NotebookReviewCandidates
{
    /**
     * The kinds a notebook can hold, in the order reviews list them.
     *
     * @return list<FeedbackType>
     */
    public static function kinds(): array
    {
        return [
            FeedbackType::Vocabulary,
            FeedbackType::Phrase,
            FeedbackType::GrammarNote,
        ];
    }

    /**
     * @param  list<FeedbackType>|null  $kinds
     * @return list<NotebookReviewCandidate>
     */
    public function for(Notebook $notebook, ?array $kinds = null): array
    {
        $wanted = $kinds === null
            ? self::kinds()
            : array_values(array_filter(
                self::kinds(),
                fn (FeedbackType $kind): bool => in_array($kind, $kinds, true),
            ));

        if ($wanted === []) {
            return [];
        }

        $rows = PracticeFeedbackEntry::query()
            ->where('notebook_id', $notebook->id)
            ->whereIn('type', array_map(
                fn (FeedbackType $kind): string => $kind->value,
                $wanted,
            ))
            ->orderBy('type')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (PracticeFeedbackEntry $entry): string => $entry->type->value);

        $candidates = [];

        foreach ($wanted as $kind) {
            /** @var list<PracticeFeedbackEntry> $kindRows */
            $kindRows = $rows->get($kind->value, collect())->values()->all();

            if ($kind === FeedbackType::GrammarNote) {
                foreach ($kindRows as $row) {
                    $candidate = $this->noteCandidate($row);

                    if ($candidate !== null) {
                        $candidates[$candidate['identity']] = $candidate;
                    }
                }

                continue;
            }

            /** @var array<string, list<PracticeFeedbackEntry>> $grouped */
            $grouped = [];

            foreach ($kindRows as $row) {
                $identity = (string) ($row->collection_key ?? '');

                if ($identity === '') {
                    continue;
                }

                $grouped[$identity][] = $row;
            }

            foreach ($grouped as $identity => $entryRows) {
                $content = NotebookItemContent::of($kind, $entryRows);
                $reason = CustomReviewEligibility::reason(
                    $kind,
                    true,
                    $content['usable'],
                    $content['split_broke'],
                );

                $candidates[$identity] = [
                    'kind' => $kind->value,
                    'identity' => $identity,
                    'title' => $content['title'],
                    'meaning' => $content['meaning'],
                    'example' => $content['example'],
                    'eligible' => $reason === null,
                    'reason' => $reason,
                ];
            }
        }

        return array_values($candidates);
    }

    /**
     * The candidates keyed by the identity a selection names, so a request can
     * be checked against what the notebook holds right now.
     *
     * @param  list<FeedbackType>|null  $kinds
     * @return array<string, NotebookReviewCandidate>
     */
    public function map(Notebook $notebook, ?array $kinds = null): array
    {
        $map = [];

        foreach ($this->for($notebook, $kinds) as $candidate) {
            $map[$candidate['identity']] = $candidate;
        }

        return $map;
    }

    /**
     * @return NotebookReviewCandidate|null
     */
    private function noteCandidate(PracticeFeedbackEntry $row): ?array
    {
        $identity = (string) ($row->collection_key ?? '');
        $text = trim((string) $row->content_text);

        if ($identity === '' || $text === '') {
            return null;
        }

        $content = NotebookItemContent::of(FeedbackType::GrammarNote, [$row]);
        $reason = CustomReviewEligibility::reason(
            FeedbackType::GrammarNote,
            true,
            $content['usable'],
        );

        return [
            'kind' => FeedbackType::GrammarNote->value,
            'identity' => $identity,
            'title' => NotePreview::of($text),
            'meaning' => '',
            'example' => '',
            'eligible' => $reason === null,
            'reason' => $reason,
        ];
    }
}
