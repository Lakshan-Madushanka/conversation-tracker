<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The items a review can be built from: the words and phrases that carry a
 * meaning (or use), the mistakes that carry a correction, and the questions
 * that carry a more natural version, from finished sessions that are not
 * archived.
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
        $entries = DB::query()
            ->fromSub(
                $this->base()
                    ->select(['entries.type', 'entries.collection_key'])
                    ->distinct(),
                'reviewable',
            )
            ->count();

        $questions = DB::query()
            ->fromSub(
                $this->questionsBase()->select('questions.id as collection_key'),
                'reviewable_questions',
            )
            ->count();

        return $entries + $questions;
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

    /**
     * The current question and its natural version behind each say-it-again
     * identity, in one query for the whole review. A question whose session
     * has gone, was archived, or whose natural version was removed is skipped
     * rather than shown stale.
     *
     * @param  array<int, array{kind: FeedbackType, identity: string}>  $items
     * @return array<string, array{question: PracticeQuestion, natural: PracticeFeedbackEntry}>
     *                                                                                          keyed by kind and identity
     */
    public function questionCardsFor(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            if ($item['kind'] === FeedbackType::Question && is_numeric($item['identity'])) {
                $ids[] = (int) $item['identity'];
            }
        }

        if ($ids === []) {
            return [];
        }

        $naturals = PracticeFeedbackEntry::query()
            ->select('practice_feedback_entries.*')
            ->join('practice_questions as questions', 'questions.id', '=', 'practice_feedback_entries.practice_question_id')
            ->join('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->with('practiceQuestion.practiceSession')
            ->where('practice_feedback_entries.type', FeedbackType::NaturalVersion->value)
            ->whereIn('practice_feedback_entries.practice_question_id', $ids)
            ->whereRaw("TRIM(practice_feedback_entries.content_text) <> ''")
            ->whereRaw("TRIM(questions.question_text) <> ''")
            ->where('sessions.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('sessions.archived_at')
            ->orderByDesc('practice_feedback_entries.id')
            ->get();

        /** @var array<string, array{question: PracticeQuestion, natural: PracticeFeedbackEntry}> $cards */
        $cards = [];

        foreach ($naturals as $natural) {
            $question = $natural->practiceQuestion;

            if (! $question instanceof PracticeQuestion) {
                continue;
            }

            $cards[$this->key(FeedbackType::Question, (string) $question->id)] ??= [
                'question' => $question,
                'natural' => $natural,
            ];
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

    /**
     * The starting point for say-it-again: a tutor's question with words and a
     * non-empty natural version, from a finished session that is not archived.
     * A question's identity is its own row id, so the same words in two
     * sessions stay two items and an edit never moves a rating.
     */
    public function questionsBase(): QueryBuilder
    {
        return DB::table('practice_questions as questions')
            ->join('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->whereNull('sessions.archived_at')
            ->where('sessions.status', PracticeSession::STATUS_FINISHED)
            ->whereRaw("TRIM(questions.question_text) <> ''")
            ->whereExists(function (QueryBuilder $natural): void {
                $natural
                    ->select(DB::raw('1'))
                    ->from('practice_feedback_entries as natural')
                    ->whereColumn('natural.practice_question_id', 'questions.id')
                    ->where('natural.type', FeedbackType::NaturalVersion->value)
                    ->whereRaw("TRIM(natural.content_text) <> ''");
            });
    }
}
