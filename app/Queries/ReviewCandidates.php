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
 * mention the same word make one item. The text is deliberately not copied:
 * `entriesFor()` reads the current words straight from the session, so an
 * edit is shown and a removed item is skipped.
 *
 * Choosing today's items lives in `App\Queries\TodaysReview`; this class
 * provides the shared candidate query and the bulk readers it and the
 * exercise screen need.
 */
final class ReviewCandidates
{
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
     * The shared starting point: finished, non-archived sessions and entries
     * that actually hold a meaning (or use).
     */
    public function base(): QueryBuilder
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
}
