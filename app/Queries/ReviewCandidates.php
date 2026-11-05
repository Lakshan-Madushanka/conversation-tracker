<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Support\BlankExercise;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The items a review can be built from: the words and phrases that carry a
 * meaning (or use), the mistakes that carry a correction, the questions that
 * carry a more natural version, and the grammar notes that can make a blank,
 * from finished sessions that are not archived.
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
    /**
     * The saved kinds that can carry a fill-in-the-blank.
     *
     * @return list<FeedbackType>
     */
    public static function blankTypes(): array
    {
        return [
            FeedbackType::Vocabulary,
            FeedbackType::Phrase,
            FeedbackType::Mistake,
            FeedbackType::GrammarNote,
        ];
    }

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

        $entries = $this->entriesBase(self::blankTypes())
            ->with('practiceQuestion.practiceSession.topic')
            ->where(function (EloquentBuilder $query) use ($items): void {
                foreach ($items as $item) {
                    $query->orWhere(fn (EloquentBuilder $pair): EloquentBuilder => $pair
                        ->where('practice_feedback_entries.type', $item['kind']->value)
                        ->where('practice_feedback_entries.collection_key', $item['identity']));
                }
            })
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

    /**
     * The candidate keys that can build a blank right now, keyed by kind and
     * identity. Each item is tested through its current appearance — the same
     * newest-entry rule cardsFor() uses — in one bulk read for the whole
     * suggestion.
     *
     * @param  array<int, array{kind: FeedbackType, identity: string}>  $items
     * @return array<string, true>
     */
    public function blankableKeys(array $items): array
    {
        $wanted = [];

        foreach ($items as $item) {
            if (! in_array($item['kind'], self::blankTypes(), true)) {
                continue;
            }

            $wanted[$this->key($item['kind'], $item['identity'])] = true;
        }

        if ($wanted === []) {
            return [];
        }

        $blankable = [];
        $seen = [];

        foreach ($this->entriesBase(self::blankTypes())->get() as $entry) {
            $key = $this->key($entry->type, (string) $entry->collection_key);

            // Only the newest appearance decides, so an older row that still
            // holds a usable example cannot bring a removed one back.
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            if (! isset($wanted[$key])) {
                continue;
            }

            if (BlankExercise::build($entry) !== null) {
                $blankable[$key] = true;
            }
        }

        return $blankable;
    }

    /**
     * How many visible grammar notes cannot make a blank yet, for the friendly
     * tip on Review Home. Two notes with identical text count separately, and
     * a note with neither a bold part nor a quoted example counts.
     */
    public function unusableGrammarNotesCount(): int
    {
        $notes = PracticeFeedbackEntry::query()
            ->select([
                'practice_feedback_entries.id',
                'practice_feedback_entries.type',
                'practice_feedback_entries.collection_key',
                'practice_feedback_entries.content_html',
                'practice_feedback_entries.content_text',
            ])
            ->join('practice_questions as questions', 'questions.id', '=', 'practice_feedback_entries.practice_question_id')
            ->join('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->where('practice_feedback_entries.type', FeedbackType::GrammarNote->value)
            ->where('sessions.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('sessions.archived_at')
            ->whereRaw("TRIM(practice_feedback_entries.content_text) <> ''")
            ->get();

        $unusable = 0;

        foreach ($notes as $note) {
            if (BlankExercise::build($note) === null) {
                $unusable += 1;
            }
        }

        return $unusable;
    }

    /** The key a review item and its current entry share. */
    public function key(FeedbackType $kind, string $identity): string
    {
        return $kind->value."\u{0}".$identity;
    }

    /**
     * The saved appearances of these kinds, newest first. Callers add their
     * own filters; keeping the order here keeps the "newest appearance wins"
     * rule in one place.
     *
     * @param  list<FeedbackType>  $types
     * @return EloquentBuilder<PracticeFeedbackEntry>
     */
    private function entriesBase(array $types): EloquentBuilder
    {
        return PracticeFeedbackEntry::query()
            ->select('practice_feedback_entries.*')
            ->join('practice_questions as questions', 'questions.id', '=', 'practice_feedback_entries.practice_question_id')
            ->join('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->whereIn('practice_feedback_entries.type', array_map(
                fn (FeedbackType $type): string => $type->value,
                $types,
            ))
            ->where('sessions.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('sessions.archived_at')
            // The newest appearance wins, so the learner sees their latest
            // wording. Ties go to the row saved last.
            ->orderByDesc('sessions.created_at')
            ->orderByDesc('practice_feedback_entries.created_at')
            ->orderByDesc('practice_feedback_entries.id');
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

    /**
     * The starting point for fill-in-the-blank's grammar notes: a note with
     * words, from a finished session that is not archived. A note's identity is
     * its own row id, so two notes with identical text stay two items and an
     * edit never moves a rating.
     */
    public function grammarNotesBase(): QueryBuilder
    {
        return DB::table('practice_feedback_entries as entries')
            ->join('practice_questions as questions', 'questions.id', '=', 'entries.practice_question_id')
            ->join('practice_sessions as sessions', 'sessions.id', '=', 'questions.practice_session_id')
            ->whereNull('sessions.archived_at')
            ->where('sessions.status', PracticeSession::STATUS_FINISHED)
            ->where('entries.type', FeedbackType::GrammarNote->value)
            ->whereNotNull('entries.collection_key')
            ->where('entries.collection_key', '!=', '')
            ->whereRaw("TRIM(entries.content_text) <> ''");
    }
}
