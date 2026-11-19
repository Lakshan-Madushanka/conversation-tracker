<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Support\SearchText;

/**
 * The first saved row in each notebook that holds every search term, read for
 * the whole page in one query. Rows keep the order the board shows its boxes,
 * so the entry the list names is the one the learner meets first after opening
 * the notebook.
 *
 * The database ranks the matching rows per notebook and only the first of each
 * is returned, so a word that matches hundreds of rows still costs one row per
 * card rather than every match.
 */
final class NotebookSearchMatches
{
    /**
     * @param  list<int>  $notebookIds
     * @param  list<string>  $terms
     * @return array<int, PracticeFeedbackEntry>
     */
    public function firstFor(array $notebookIds, array $terms): array
    {
        if ($notebookIds === [] || $terms === []) {
            return [];
        }

        $ranked = PracticeFeedbackEntry::query()
            ->whereIn('notebook_id', $notebookIds)
            ->whereIn('type', [
                FeedbackType::Vocabulary->value,
                FeedbackType::Phrase->value,
                FeedbackType::GrammarNote->value,
            ])
            ->select(['practice_feedback_entries.*'])
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY notebook_id ORDER BY CASE practice_feedback_entries.type WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END, position, id) AS match_rank',
                [FeedbackType::Vocabulary->value, FeedbackType::Phrase->value],
            );

        SearchText::whereAll($ranked, 'practice_feedback_entries.search_text', $terms);

        $entries = PracticeFeedbackEntry::query()
            ->fromSub($ranked, 'ranked')
            ->where('match_rank', 1)
            ->get();

        $first = [];

        foreach ($entries as $entry) {
            $first[(int) $entry->notebook_id] = $entry;
        }

        return $first;
    }
}
