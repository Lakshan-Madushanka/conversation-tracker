<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CustomReviewAttemptStatus;
use App\Enums\RecentReviewKind;
use App\Enums\ReviewRating;
use App\Support\NotebookIdentity;
use App\Support\ReviewSettings;
use Illuminate\Support\Facades\DB;

/**
 * The reviews the learner recently ended, with what they actually rated.
 *
 * A result is either a sitting (`reviews`, daily or custom) or a finished
 * attempt at a saved review. Both are read the same way: each sitting with a
 * finish moment, newest first, with its ratings counted from the saved rows
 * themselves — so an undone rating is gone, a re-rated item counts once with
 * its final rating, and an item that was skipped never appears in the tally.
 * Only a result with something rated is kept, so the list never shows an empty
 * row.
 *
 * The kind is chosen before the list is cut to its five rows, so narrowing to
 * one kind can surface an older result the unfiltered list would have dropped.
 *
 * A result names its notebook only when every item it holds resolves to the
 * same notebook that still exists. A sitting built from a notebook is one
 * result from start to finish; a mix of notebook and session items has no one
 * source, so it shows none. A deleted notebook leaves no link behind.
 *
 * @phpstan-type RecentRow array{
 *     key: string,
 *     id: int,
 *     type: 'daily'|'custom',
 *     custom_review_id: int|null,
 *     review_name: string|null,
 *     attempt_number: int|null,
 *     finished_at: string,
 *     rated: int,
 *     easy: int,
 *     okay: int,
 *     hard: int,
 *     source: array{id: int, name: string}|null
 * }
 */
final class RecentReviews
{
    /**
     * @return list<RecentRow>
     */
    public function list(RecentReviewKind $kind = RecentReviewKind::All): array
    {
        $rows = $this->sittings($kind);

        if ($kind->includesCustom()) {
            $rows = [...$rows, ...$this->attempts()];
        }

        usort(
            $rows,
            fn (array $left, array $right): int => strcmp($right['finished_at'], $left['finished_at'])
                ?: strcmp($right['key'], $left['key']),
        );

        return $this->withSources(array_slice($rows, 0, ReviewSettings::RECENT_RESULTS));
    }

    /**
     * The finished sittings, daily or custom.
     *
     * @return list<RecentRow>
     */
    private function sittings(RecentReviewKind $kind): array
    {
        $query = DB::query()
            ->from('reviews')
            ->join('ratings', 'ratings.review_id', '=', 'reviews.id')
            ->select([
                'reviews.id',
                'reviews.is_custom_review',
                'reviews.finished_at',
            ])
            ->selectRaw('COUNT(ratings.id) as rated')
            ->selectRaw(
                'SUM(CASE WHEN ratings.rating = ? THEN 1 ELSE 0 END) as easy',
                [ReviewRating::Easy->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN ratings.rating = ? THEN 1 ELSE 0 END) as okay',
                [ReviewRating::Okay->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN ratings.rating = ? THEN 1 ELSE 0 END) as hard',
                [ReviewRating::Hard->value],
            )
            ->whereNotNull('reviews.finished_at');

        if ($kind === RecentReviewKind::Daily) {
            $query->where('reviews.is_custom_review', false);
        } elseif ($kind === RecentReviewKind::Custom) {
            $query->where('reviews.is_custom_review', true);
        }

        $rows = $query
            ->groupBy('reviews.id')
            ->orderByDesc('reviews.finished_at')
            ->orderByDesc('reviews.id')
            ->limit(ReviewSettings::RECENT_RESULTS)
            ->get();

        $results = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $custom = (bool) $row->is_custom_review;

            $results[] = [
                'key' => 'review-'.$id,
                'id' => $id,
                'type' => $custom ? 'custom' : 'daily',
                'custom_review_id' => null,
                'review_name' => null,
                'attempt_number' => null,
                'finished_at' => (string) $row->finished_at,
                'rated' => (int) $row->rated,
                'easy' => (int) $row->easy,
                'okay' => (int) $row->okay,
                'hard' => (int) $row->hard,
                'source' => null,
            ];
        }

        return $results;
    }

    /**
     * The attempts that ended with something rated. A skipped item is not a
     * result, so the filter sits before the count.
     *
     * @return list<RecentRow>
     */
    private function attempts(): array
    {
        $rows = DB::query()
            ->from('custom_review_attempts')
            ->join('custom_reviews', 'custom_reviews.id', '=', 'custom_review_attempts.custom_review_id')
            ->join(
                'custom_review_attempt_items',
                'custom_review_attempt_items.custom_review_attempt_id',
                '=',
                'custom_review_attempts.id',
            )
            ->select([
                'custom_review_attempts.id',
                'custom_review_attempts.custom_review_id',
                'custom_review_attempts.number',
                'custom_review_attempts.finished_at',
                'custom_reviews.name as review_name',
            ])
            ->selectRaw('COUNT(custom_review_attempt_items.id) as rated')
            ->selectRaw(
                'SUM(CASE WHEN custom_review_attempt_items.rating = ? THEN 1 ELSE 0 END) as easy',
                [ReviewRating::Easy->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN custom_review_attempt_items.rating = ? THEN 1 ELSE 0 END) as okay',
                [ReviewRating::Okay->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN custom_review_attempt_items.rating = ? THEN 1 ELSE 0 END) as hard',
                [ReviewRating::Hard->value],
            )
            ->whereIn('custom_review_attempts.status', [
                CustomReviewAttemptStatus::Completed->value,
                CustomReviewAttemptStatus::FinishedEarly->value,
            ])
            ->whereNotNull('custom_review_attempts.finished_at')
            ->whereNotNull('custom_review_attempt_items.rating')
            ->groupBy('custom_review_attempts.id')
            ->orderByDesc('custom_review_attempts.finished_at')
            ->orderByDesc('custom_review_attempts.id')
            ->limit(ReviewSettings::RECENT_RESULTS)
            ->get();

        $results = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;

            $results[] = [
                'key' => 'attempt-'.$id,
                'id' => $id,
                'type' => 'custom',
                'custom_review_id' => (int) $row->custom_review_id,
                'review_name' => (string) $row->review_name,
                'attempt_number' => $row->number === null ? null : (int) $row->number,
                'finished_at' => (string) $row->finished_at,
                'rated' => (int) $row->rated,
                'easy' => (int) $row->easy,
                'okay' => (int) $row->okay,
                'hard' => (int) $row->hard,
                'source' => null,
            ];
        }

        return $results;
    }

    /**
     * Give every kept row its one notebook, read from the items the review was
     * built with rather than any snapshot, so a deleted notebook leaves no
     * link behind and a half-notebook review names none.
     *
     * @param  list<RecentRow>  $rows
     * @return list<RecentRow>
     */
    private function withSources(array $rows): array
    {
        $sittingIds = [];
        $attemptReviewIds = [];

        foreach ($rows as $row) {
            if ($row['custom_review_id'] === null) {
                $sittingIds[] = $row['id'];
            } else {
                $attemptReviewIds[] = $row['custom_review_id'];
            }
        }

        $sittingItems = $this->sittingIdentities($sittingIds);
        $attemptItems = $this->attemptReviewIdentities($attemptReviewIds);

        $wanted = [];

        foreach ([...array_values($sittingItems), ...array_values($attemptItems)] as $identities) {
            foreach ($identities as $identity) {
                $wanted[] = $identity;
            }
        }

        // Identities are values, never keys: a numeric session word would
        // otherwise be turned into an integer and lose its string type.
        $notebooks = $this->notebooksFor(array_values(array_unique($wanted)));

        foreach ($rows as $index => $row) {
            $identities = $row['custom_review_id'] === null
                ? ($sittingItems[$row['id']] ?? [])
                : ($attemptItems[$row['custom_review_id']] ?? []);

            $rows[$index]['source'] = $this->commonNotebook($identities, $notebooks);
        }

        return $rows;
    }

    /**
     * @param  list<int>  $reviewIds
     * @return array<int, list<string>>
     */
    private function sittingIdentities(array $reviewIds): array
    {
        return $this->groupedIdentities('review_items', 'review_id', $reviewIds);
    }

    /**
     * @param  list<int>  $reviewIds
     * @return array<int, list<string>>
     */
    private function attemptReviewIdentities(array $reviewIds): array
    {
        return $this->groupedIdentities('custom_review_items', 'custom_review_id', $reviewIds);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<string>>
     */
    private function groupedIdentities(string $table, string $column, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = DB::query()
            ->from($table)
            ->whereIn($column, $ids)
            ->get([$column, 'item_identity']);

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row->{$column}][] = (string) $row->item_identity;
        }

        return $grouped;
    }

    /**
     * The live notebook every identity would open, keyed by the identity the
     * review stores. A session word, a mistake or a question has no notebook,
     * so it is simply absent.
     *
     * @param  list<string>  $identities
     * @return array<string, array{id: int, name: string}>
     */
    private function notebooksFor(array $identities): array
    {
        $wordKeys = [];
        $noteIds = [];

        foreach ($identities as $identity) {
            if (NotebookIdentity::isWordKey($identity)) {
                $wordKeys[] = $identity;

                continue;
            }

            if (preg_match('/^note-(\d+)$/', $identity, $matches) === 1) {
                $noteIds[] = (int) $matches[1];
            }
        }

        $notebooks = [];

        if ($wordKeys !== []) {
            $rows = DB::query()
                ->from('practice_feedback_entries as entries')
                ->join('notebooks', 'notebooks.id', '=', 'entries.notebook_id')
                ->whereIn('entries.collection_key', $wordKeys)
                ->get(['entries.collection_key', 'notebooks.id', 'notebooks.name']);

            foreach ($rows as $row) {
                $notebooks[(string) $row->collection_key] = [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                ];
            }
        }

        if ($noteIds !== []) {
            $rows = DB::query()
                ->from('practice_feedback_entries as entries')
                ->join('notebooks', 'notebooks.id', '=', 'entries.notebook_id')
                ->whereIn('entries.id', $noteIds)
                ->get(['entries.id', 'notebooks.id', 'notebooks.name']);

            foreach ($rows as $row) {
                $notebooks['note-'.$row->id] = [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                ];
            }
        }

        return $notebooks;
    }

    /**
     * @param  list<string>  $identities
     * @param  array<string, array{id: int, name: string}>  $notebooks
     * @return array{id: int, name: string}|null
     */
    private function commonNotebook(array $identities, array $notebooks): ?array
    {
        if ($identities === []) {
            return null;
        }

        $found = [];

        foreach ($identities as $identity) {
            $notebook = $notebooks[$identity] ?? null;

            if ($notebook === null) {
                return null;
            }

            $found[$notebook['id']] = $notebook;
        }

        return count($found) === 1 ? array_values($found)[0] : null;
    }
}
