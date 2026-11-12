<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Models\Topic;
use App\Support\ReviewFilters;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The topics a custom review can be restricted to: only the shared topics of
 * finished, non-archived sessions and saved notebooks that still hold
 * reviewable items, each with how many it holds, alphabetically. A topic the
 * learner no longer uses simply is not offered, so the list can never go stale.
 *
 * The counts come from the same shared candidate queries the composer reads,
 * narrowed by the other choices already on the page (collections, starred only,
 * Learned), so the numbers move with the choices the learner is making. Four
 * queries in total, whatever the number of items or topics.
 */
final readonly class ReviewTopics
{
    public function __construct(private ReviewCandidates $candidates) {}

    /**
     * @return list<array{id: int, name: string, count: int}>
     */
    public function list(ReviewFilters $filters): array
    {
        $counts = $this->countByTopic($this->entryItems($filters, $this->candidates->base($filters)));

        if ($filters->allows(FeedbackType::Question)) {
            $counts = $this->merge($counts, $this->countByTopic(
                $this->candidates->questionsBase($filters)
                    ->select(['sessions.topic_id as topic_id', 'questions.id as identity'])
                    ->distinct(),
            ));
        }

        if ($filters->allows(FeedbackType::GrammarNote)) {
            $counts = $this->merge($counts, $this->countByTopic(
                $this->entryItems($filters, $this->candidates->grammarNotesBase($filters)),
            ));
        }

        if ($counts === []) {
            return [];
        }

        return array_values(Topic::query()
            ->whereIn('id', array_keys($counts))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Topic $topic): array => [
                'id' => $topic->id,
                'name' => $topic->name,
                'count' => $counts[$topic->id],
            ])
            ->all());
    }

    /**
     * One topic per saved item, so the same word mentioned twice in one topic
     * counts once and the same word in two topics counts in each of them.
     *
     * @return array<int, int>
     */
    private function countByTopic(QueryBuilder $items): array
    {
        $rows = DB::query()
            ->fromSub($items, 'items')
            ->select('items.topic_id as topic_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('items.topic_id')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->topic_id] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * The entries of a topic, one row per item, with the choices the learner's
     * marks decide. Those two stay here rather than in the shared candidate
     * queries: the composer reads its marks in bulk for the whole pool, while
     * this grouped read asks the database in one go.
     */
    private function entryItems(ReviewFilters $filters, QueryBuilder $query): QueryBuilder
    {
        if ($filters->starredOnly) {
            $query->whereExists(fn (QueryBuilder $mark) => $mark
                ->select(DB::raw('1'))
                ->from('collection_item_marks as marks')
                ->whereColumn('marks.group_key', 'entries.collection_key')
                ->whereColumn('marks.kind', 'entries.type')
                ->where('marks.starred', true));
        }

        if ($filters->custom && ! $filters->includeLearned) {
            $query->whereNotExists(fn (QueryBuilder $mark) => $mark
                ->select(DB::raw('1'))
                ->from('collection_item_marks as marks')
                ->whereColumn('marks.group_key', 'entries.collection_key')
                ->whereColumn('marks.kind', 'entries.type')
                ->where('marks.status', CollectionItemStatus::Learned->value));
        }

        return $query->select([
            DB::raw('COALESCE(sessions.topic_id, notebooks.topic_id) as topic_id'),
            'entries.type as type',
            'entries.collection_key as identity',
        ])
            ->distinct();
    }

    /**
     * @param  array<int, int>  $counts
     * @param  array<int, int>  $added
     * @return array<int, int>
     */
    private function merge(array $counts, array $added): array
    {
        foreach ($added as $topicId => $total) {
            $counts[$topicId] = ($counts[$topicId] ?? 0) + $total;
        }

        return $counts;
    }
}
