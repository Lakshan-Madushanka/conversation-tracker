<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeSession;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The items a review can be built from: the words and phrases that carry a
 * meaning (or use), from finished sessions that are not archived.
 *
 * An item is the same identity Collections groups by, so two sessions that
 * mention the same word make one item. Newest-reviewed information is read in
 * bulk, never once per candidate, so the selection stays quick however much
 * the learner has saved.
 *
 * The text is deliberately not copied: `entriesFor()` reads the current words
 * straight from the session, so an edit is shown and a removed item is skipped.
 */
final class ReviewCandidates
{
    /** How many items one review holds by default. */
    public const int LIMIT = 8;

    /**
     * The next items worth reviewing: never-reviewed items first, then the
     * ones reviewed longest ago. The never-reviewed group is shuffled so two
     * reviews in a row do not look identical.
     *
     * @return list<array{kind: FeedbackType, identity: string}>
     */
    public function next(int $limit = self::LIMIT): array
    {
        $neverReviewed = $this->neverReviewed($limit * 5);
        shuffle($neverReviewed);

        return array_slice(
            [...$neverReviewed, ...$this->reviewedLongestAgo($limit)],
            0,
            $limit,
        );
    }

    /** How many distinct items could be reviewed right now. */
    public function count(): int
    {
        return DB::query()
            ->fromSub(
                $this->base()
                    ->select(['entries.type', 'entries.collection_key'])
                    ->distinct(),
                'reviewable',
            )
            ->count();
    }

    /**
     * The current row behind each identity, in one query for the whole review.
     *
     * @param  array<int, array{kind: FeedbackType, identity: string}>  $items
     * @return array<string, PracticeFeedbackEntry> keyed by kind and identity
     */
    public function entriesFor(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $entries = PracticeFeedbackEntry::query()
            ->with('practiceQuestion.practiceSession')
            ->whereIn('type', [FeedbackType::Vocabulary->value, FeedbackType::Phrase->value])
            ->where(function (EloquentBuilder $query) use ($items): void {
                foreach ($items as $item) {
                    $query->orWhere(fn (EloquentBuilder $pair): EloquentBuilder => $pair
                        ->where('type', $item['kind']->value)
                        ->where('collection_key', $item['identity']));
                }
            })
            ->whereHas(
                'practiceQuestion.practiceSession',
                fn (EloquentBuilder $sessions): EloquentBuilder => $sessions->study(),
            )
            // The newest row wins, so the learner sees their latest wording.
            ->orderByDesc('id')
            ->get();

        $current = [];

        foreach ($entries as $entry) {
            $key = $this->key($entry->type, (string) $entry->collection_key);
            $current[$key] ??= $entry;
        }

        return $current;
    }

    /** The key a review item and its current entry share. */
    public function key(FeedbackType $kind, string $identity): string
    {
        return $kind->value."\u{0}".$identity;
    }

    /**
     * Candidates with no rating at all yet, newest saved first. The bound keeps
     * the read small while still giving the shuffle a varied pool to choose
     * from.
     *
     * @return list<array{kind: FeedbackType, identity: string}>
     */
    private function neverReviewed(int $limit): array
    {
        return array_values($this->base()
            ->select(['entries.type as kind', 'entries.collection_key as identity'])
            ->distinct()
            ->whereNotExists(fn (QueryBuilder $ratings): QueryBuilder => $ratings
                ->from('ratings')
                ->whereColumn('ratings.kind', 'entries.type')
                ->whereColumn('ratings.item_identity', 'entries.collection_key'))
            ->orderByDesc('entries.id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => $this->pair((array) $row))
            ->all());
    }

    /**
     * Candidates already reviewed, the one left alone the longest first, so a
     * forgotten item comes back before a familiar one.
     *
     * @return list<array{kind: FeedbackType, identity: string}>
     */
    private function reviewedLongestAgo(int $limit): array
    {
        return array_values($this->base()
            ->joinSub(
                DB::table('ratings')
                    ->select(['kind', 'item_identity'])
                    ->selectRaw('MAX(created_at) as last_rated_at')
                    ->groupBy('kind', 'item_identity'),
                'given',
                fn ($join) => $join
                    ->on('given.kind', '=', 'entries.type')
                    ->on('given.item_identity', '=', 'entries.collection_key'),
            )
            ->select(['entries.type as kind', 'entries.collection_key as identity'])
            ->selectRaw('MIN(given.last_rated_at) as last_rated_at')
            ->groupBy('entries.type', 'entries.collection_key')
            ->orderBy('last_rated_at')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => $this->pair((array) $row))
            ->all());
    }

    /**
     * The shared starting point: finished, non-archived sessions and entries
     * that actually hold a meaning (or use).
     */
    private function base(): QueryBuilder
    {
        return DB::table('practice_feedback_entries as entries')
            ->join('practice_questions as questions', 'questions.id', '=', 'entries.practice_question_id')
            ->join('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->whereIn('entries.type', [FeedbackType::Vocabulary->value, FeedbackType::Phrase->value])
            ->whereNotNull('entries.collection_key')
            ->where('entries.collection_key', '!=', '')
            ->whereRaw("TRIM(entries.correction_text) <> ''")
            ->where('sessions.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('sessions.archived_at');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{kind: FeedbackType, identity: string}
     */
    private function pair(array $row): array
    {
        return [
            'kind' => FeedbackType::from((string) $row['kind']),
            'identity' => (string) $row['identity'],
        ];
    }
}
