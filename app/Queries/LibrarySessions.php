<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\PracticeSession;
use App\Support\LibraryFilters;
use App\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;

/**
 * The sessions that belong in the Library, narrowed by the learner's filters
 * when they have chosen any.
 *
 * A normal Library visit lists visible sessions; the Archived view lists only
 * archived ones. Every list reads this one rule rather than deciding for
 * itself what archiving hides.
 */
final readonly class LibrarySessions
{
    /** How many sessions the Library loads at a time. */
    public const int PAGE_SIZE = 20;

    /** How many sessions one selection may act on at once. */
    public const int SELECTION_LIMIT = 1000;

    /**
     * One page of the Library, in the chosen order. Sessions created on the
     * same day fall back to the newest row first, and the id keeps the order
     * stable when two rows share a moment.
     *
     * The cursor continues from the last session shown, not from a page
     * number, so a session created while the list is open can never make a
     * later page repeat or skip one.
     *
     * @return CursorPaginator<int, PracticeSession>
     */
    public function paginate(
        ?LibraryFilters $filters = null,
        ?string $cursor = null,
        int $perPage = self::PAGE_SIZE,
    ): CursorPaginator {
        $query = $this->query($filters)
            ->with(['topic', 'questions.feedbackEntries']);

        $this->applySort($query, $filters?->sort() ?? LibraryFilters::SORT_NEWEST);

        return $query->cursorPaginate(perPage: $perPage, cursor: $cursor);
    }

    /**
     * How many sessions the Library lists, both after filtering and in total.
     * A filtered list counts the sessions that match every chosen filter.
     */
    public function total(?LibraryFilters $filters = null): int
    {
        return $this->query($filters)->count();
    }

    /**
     * Every session matching the current view, filters and search, in the
     * chosen order, so "Select all" means the whole result and not only the
     * cards already loaded.
     *
     * A list too large for one selection is refused as a whole: the total is
     * still reported, but no ids are returned, so nothing is ever selected
     * silently behind the learner's back.
     *
     * @return array{
     *     ids: list<int>,
     *     finished_ids: list<int>,
     *     total: int,
     *     limit: int,
     *     truncated: bool
     * }
     */
    public function selection(?LibraryFilters $filters = null, int $limit = self::SELECTION_LIMIT): array
    {
        $total = $this->total($filters);

        if ($total > $limit) {
            return [
                'ids' => [],
                'finished_ids' => [],
                'total' => $total,
                'limit' => $limit,
                'truncated' => true,
            ];
        }

        $query = $this->query($filters);

        $this->applySort($query, $filters?->sort() ?? LibraryFilters::SORT_NEWEST);

        $ids = [];
        $finishedIds = [];

        foreach ($query->get(['id', 'status']) as $session) {
            $id = (int) $session->getKey();

            $ids[] = $id;

            if ($session->isFinished()) {
                $finishedIds[] = $id;
            }
        }

        return [
            'ids' => $ids,
            'finished_ids' => $finishedIds,
            'total' => $total,
            'limit' => $limit,
            'truncated' => false,
        ];
    }

    /**
     * The topics used by at least one session in the view being shown,
     * ordered by name. A topic no session uses any more simply stops being
     * offered. The Archived view offers only topics used by archived sessions.
     *
     * @return list<array{id: int, name: string}>
     */
    public function topics(bool $archived = false): array
    {
        $topics = PracticeSession::query()
            ->when($archived, fn (Builder $query): Builder => $query->archived(), fn (Builder $query): Builder => $query->visible())
            ->join('topics', 'topics.id', '=', 'practice_sessions.topic_id')
            ->distinct()
            ->orderBy('topics.name')
            ->pluck('topics.name', 'topics.id');

        $options = [];

        foreach ($topics as $id => $name) {
            $options[] = ['id' => (int) $id, 'name' => (string) $name];
        }

        return $options;
    }

    /**
     * @return Builder<PracticeSession>
     */
    private function query(?LibraryFilters $filters): Builder
    {
        $query = PracticeSession::query();

        $query = $filters?->archivedView() === true
            ? $query->archived()
            : $query->visible();

        return $filters instanceof LibraryFilters
            ? $this->applyFilters($query, $filters)
            : $query;
    }

    /**
     * Apply every chosen kind of filter. Topic and content type values match
     * any of the chosen options; the kinds themselves must all match.
     *
     * @param  Builder<PracticeSession>  $query
     * @return Builder<PracticeSession>
     */
    private function applyFilters(Builder $query, LibraryFilters $filters): Builder
    {
        if ($filters->topics() !== []) {
            $query->whereIn('topic_id', $filters->topics());
        }

        if ($filters->status() === PracticeSession::STATUS_FINISHED) {
            $query->finished();
        } elseif ($filters->status() === PracticeSession::STATUS_UNFINISHED) {
            $query->unfinished();
        }

        $range = $filters->dateRange();

        if ($range !== null) {
            [$start, $end] = $range;

            if ($start !== null && $end !== null) {
                $query->whereBetween('created_at', [$start, $end]);
            } elseif ($start !== null) {
                $query->where('created_at', '>=', $start);
            } elseif ($end !== null) {
                $query->where('created_at', '<=', $end);
            }
        }

        if ($filters->types() !== []) {
            $types = $filters->types();

            $query->whereHas('questions.feedbackEntries', function (Builder $entries) use ($types): void {
                $entries
                    ->whereIn('type', $types)
                    ->where(function (Builder $nonEmpty): void {
                        $nonEmpty
                            ->where(function (Builder $rich): void {
                                $rich
                                    ->whereIn('type', FeedbackType::richValues())
                                    ->whereRaw("TRIM(content_text) <> ''");
                            })
                            ->orWhere(function (Builder $table): void {
                                $table
                                    ->whereIn('type', FeedbackType::tableValues())
                                    ->where(function (Builder $columns): void {
                                        $columns
                                            ->whereRaw("TRIM(content_text) <> ''")
                                            ->orWhereRaw("TRIM(correction_text) <> ''")
                                            ->orWhereRaw("TRIM(reason) <> ''")
                                            ->orWhereRaw("TRIM(example) <> ''")
                                            ->orWhereRaw("TRIM(content_html) <> ''")
                                            ->orWhereRaw("TRIM(correction_html) <> ''");
                                    });
                            });
                    });
            });
        }

        $this->applySearch($query, $filters);

        return $query;
    }

    /**
     * Narrow the list to sessions holding at least one item with every search
     * word. With content types chosen, only those boxes are searched, so the
     * search and the filter never disagree about what is on screen.
     *
     * @param  Builder<PracticeSession>  $query
     */
    private function applySearch(Builder $query, LibraryFilters $filters): void
    {
        $terms = $filters->searchTerms();

        if ($terms === []) {
            return;
        }

        $types = $filters->types();

        $query->where(function (Builder $search) use ($terms, $types): void {
            $search->whereHas(
                'questions.feedbackEntries',
                function (Builder $entries) use ($terms, $types): void {
                    if ($types !== []) {
                        $entries->whereIn('type', $types);
                    }

                    SearchText::whereAll(
                        $entries,
                        'practice_feedback_entries.search_text',
                        $terms,
                    );
                },
            );

            // A content type filter says exactly which boxes are in play, so
            // topics, questions and answers are not searched beside them.
            if ($types !== []) {
                return;
            }

            $search->orWhereHas('questions', function (Builder $questions) use ($terms): void {
                $questions
                    ->where(function (Builder $questionField) use ($terms): void {
                        SearchText::whereAll(
                            $questionField,
                            'practice_questions.question_search_text',
                            $terms,
                        );
                    })
                    ->orWhere(function (Builder $answerField) use ($terms): void {
                        SearchText::whereAll(
                            $answerField,
                            'practice_questions.answer_search_text',
                            $terms,
                        );
                    });
            });

            $search->orWhereHas('topic', function (Builder $topic) use ($terms): void {
                SearchText::whereAll($topic, 'topics.search_text', $terms);
            });
        });
    }

    /**
     * @param  Builder<PracticeSession>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            LibraryFilters::SORT_OLDEST => $query
                ->orderBy('created_at')
                ->orderBy('id'),
            LibraryFilters::SORT_RECENTLY_EDITED => $query
                ->orderByDesc('last_changed_at')
                ->orderByDesc('id'),
            default => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
        };
    }
}
