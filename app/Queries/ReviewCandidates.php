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
 * meaning (or use), and the mistakes that carry a correction, from finished
 * sessions that are not archived.
 *
 * An item is the same identity Collections groups by, so two sessions that
 * mention the same word — or the same mistake pair — make one item. The text
 * is deliberately not copied: `cardsFor()` reads the current entries straight
 * from the session, so an edit is shown and a removed item is skipped.
 *
 * A mistake's exercise text comes from its most recent appearance (latest
 * session, ties by latest entry). Its reason comes from the most recent
 * appearance that has one, with every distinct reason kept in order for the
 * "More notes" panel.
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
     * The current entry behind each identity, in one query for the whole
     * review, plus every distinct reason its mistake appearances carry.
     *
     * @param  array<int, array{kind: FeedbackType, identity: string}>  $items
     * @return array<string, array{entry: PracticeFeedbackEntry, reasons: list<string>}>
     *                                                                                   keyed by kind and identity
     */
    public function cardsFor(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $entries = PracticeFeedbackEntry::query()
            ->select('practice_feedback_entries.*')
            ->join('practice_questions as questions', 'questions.id', '=', 'practice_feedback_entries.practice_question_id')
            ->join('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->with('practiceQuestion.practiceSession')
            ->whereIn('practice_feedback_entries.type', [
                FeedbackType::Vocabulary->value,
                FeedbackType::Phrase->value,
                FeedbackType::Mistake->value,
            ])
            ->where(function (EloquentBuilder $query) use ($items): void {
                foreach ($items as $item) {
                    $query->orWhere(fn (EloquentBuilder $pair): EloquentBuilder => $pair
                        ->where('practice_feedback_entries.type', $item['kind']->value)
                        ->where('practice_feedback_entries.collection_key', $item['identity']));
                }
            })
            ->where('sessions.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('sessions.archived_at')
            // The newest appearance wins, so the learner sees their latest
            // wording. Ties go to the row saved last.
            ->orderByDesc('sessions.created_at')
            ->orderByDesc('practice_feedback_entries.created_at')
            ->orderByDesc('practice_feedback_entries.id')
            ->get();

        /** @var array<string, array{entry: PracticeFeedbackEntry, reasons: list<string>}> $cards */
        $cards = [];

        foreach ($entries as $entry) {
            $key = $this->key($entry->type, (string) $entry->collection_key);

            $cards[$key] ??= ['entry' => $entry, 'reasons' => []];

            $reason = trim((string) $entry->reason);

            if ($reason !== '' && ! in_array($reason, $cards[$key]['reasons'], true)) {
                $cards[$key]['reasons'][] = $reason;
            }
        }

        return $cards;
    }

    /** The key a review item and its current entry share. */
    public function key(FeedbackType $kind, string $identity): string
    {
        return $kind->value."\u{0}".$identity;
    }

    /**
     * The shared starting point: finished, non-archived sessions and entries
     * that can actually be practiced. A word or phrase needs its meaning; a
     * mistake needs both the sentence the learner said and its correction.
     */
    public function base(): QueryBuilder
    {
        return DB::table('practice_feedback_entries as entries')
            ->join('practice_questions as questions', 'questions.id', '=', 'entries.practice_question_id')
            ->join('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->whereNull('sessions.archived_at')
            ->where('sessions.status', PracticeSession::STATUS_FINISHED)
            ->where(function (QueryBuilder $reviewable): void {
                $reviewable
                    ->where(function (QueryBuilder $words): void {
                        $words
                            ->whereIn('entries.type', [
                                FeedbackType::Vocabulary->value,
                                FeedbackType::Phrase->value,
                            ])
                            ->whereNotNull('entries.collection_key')
                            ->where('entries.collection_key', '!=', '')
                            ->whereRaw("TRIM(entries.correction_text) <> ''");
                    })
                    ->orWhere(function (QueryBuilder $mistakes): void {
                        $mistakes
                            ->where('entries.type', FeedbackType::Mistake->value)
                            ->whereNotNull('entries.collection_key')
                            ->where('entries.collection_key', '!=', '')
                            ->whereRaw("TRIM(entries.content_text) <> ''")
                            ->whereRaw("TRIM(entries.correction_text) <> ''");
                    });
            });
    }
}
