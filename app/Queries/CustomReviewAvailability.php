<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Enums\ItemAvailability;
use App\Models\PracticeSession;
use App\Support\CustomReviewIdentity;
use App\Support\MistakeIdentity;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The one answer to "can this item be used right now", read from the sessions
 * that hold it.
 *
 * A custom review stores identities, not rows, so every place that shows or
 * uses one has to ask the same question: does the item still exist, and does a
 * session that is finished and not archived still hold it? The review's page,
 * its card, the attempt it starts and the attempt already running all read
 * this class, so none of them can disagree about what is available.
 *
 * One call answers for every identity of one kind in a single query, so the
 * number of queries never grows with the items a review holds.
 */
final readonly class CustomReviewAvailability
{
    /**
     * The state of each identity, with every identity asked for given an answer.
     *
     * @param  list<string>  $identities
     * @return array<string, ItemAvailability>
     */
    public function states(FeedbackType $kind, array $identities): array
    {
        if ($identities === []) {
            return [];
        }

        $found = match ($kind) {
            FeedbackType::Vocabulary, FeedbackType::Phrase => $this->entryStates($kind, $identities),
            FeedbackType::GrammarNote => $this->noteStates($identities),
            FeedbackType::Mistake => $this->mistakeStates($identities),
            FeedbackType::Question => $this->questionStates($identities),
            // Not a collection an attempt can be built from.
            FeedbackType::NaturalVersion => [],
        };

        $states = [];

        foreach ($identities as $identity) {
            $states[$identity] = $found[$identity] ?? ItemAvailability::Missing;
        }

        return $states;
    }

    /**
     * The identities that can be used in an attempt right now.
     *
     * @param  list<string>  $identities
     * @return array<string, true>
     */
    public function available(FeedbackType $kind, array $identities): array
    {
        $usable = [];

        foreach ($this->states($kind, $identities) as $identity => $state) {
            if ($state->isAvailable()) {
                $usable[$identity] = true;
            }
        }

        return $usable;
    }

    /**
     * Words and phrases, grouped by the identity their rows carry.
     *
     * @param  list<string>  $identities
     * @return array<string, ItemAvailability>
     */
    private function entryStates(FeedbackType $kind, array $identities): array
    {
        /** @var list<string> $keys */
        $keys = array_values(array_filter(
            $identities,
            fn (string $identity): bool => $identity !== '',
        ));

        if ($keys === []) {
            return [];
        }

        // Notebook rows have no question and no session state to wait on, so
        // they are joined loosely and count as study rows the moment they
        // exist. A matching session row never rescues a deleted notebook one:
        // the key itself is what is looked up.
        $rows = DB::table('practice_feedback_entries as e')
            ->leftJoin('practice_questions as q', 'q.id', '=', 'e.practice_question_id')
            ->leftJoin('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->where('e.type', $kind->value)
            ->whereIn('e.collection_key', $keys)
            ->groupBy('e.collection_key')
            ->select('e.collection_key')
            ->selectRaw(
                'MAX(CASE WHEN e.notebook_id IS NOT NULL OR (s.status = ? AND s.archived_at IS NULL) THEN 1 ELSE 0 END) AS study_rows',
                [PracticeSession::STATUS_FINISHED],
            )
            ->selectRaw('COUNT(*) AS all_rows')
            ->get();

        $states = [];

        foreach ($rows as $row) {
            $states[(string) $row->collection_key] = $this->state(
                (int) $row->study_rows,
                (int) $row->all_rows,
            );
        }

        return $states;
    }

    /**
     * Grammar notes, named by the row they belong to.
     *
     * @param  list<string>  $identities
     * @return array<string, ItemAvailability>
     */
    private function noteStates(array $identities): array
    {
        $noteIds = array_values(array_filter(array_map(
            CustomReviewIdentity::noteId(...),
            $identities,
        )));

        if ($noteIds === []) {
            return [];
        }

        /** @var array<string, ItemAvailability> $states */
        $states = [];

        $rows = DB::table('practice_feedback_entries as e')
            ->leftJoin('practice_questions as q', 'q.id', '=', 'e.practice_question_id')
            ->leftJoin('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->where('e.type', FeedbackType::GrammarNote->value)
            ->whereIn('e.id', $noteIds)
            ->select(['e.id', 'e.notebook_id', 's.status', 's.archived_at'])
            ->get();

        foreach ($rows as $row) {
            $identity = CustomReviewIdentity::noteKey((int) $row->id);
            $states[$identity] = $this->state(
                $row->notebook_id !== null || $this->isStudySession($row) ? 1 : 0,
                1,
            );
        }

        return $states;
    }

    /**
     * Mistakes, matched by the same pair key the collections group them with.
     *
     * The key comes out of two columns hashed in PHP, so it cannot be matched
     * in the database. Every mistake row is read once and sorted by its key,
     * which is the rule `PruneCustomReviewItems` already follows.
     *
     * @param  list<string>  $identities
     * @return array<string, ItemAvailability>
     */
    private function mistakeStates(array $identities): array
    {
        $wanted = array_fill_keys($identities, true);

        $rows = DB::table('practice_feedback_entries as e')
            ->join('practice_questions as q', 'q.id', '=', 'e.practice_question_id')
            ->join('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->where('e.type', FeedbackType::Mistake->value)
            ->select(['e.content_text', 'e.correction_text', 's.status', 's.archived_at'])
            ->get();

        /** @var array<string, array{study: int, all: int}> $counts */
        $counts = [];

        foreach ($rows as $row) {
            $said = trim((string) $row->content_text);
            $correction = trim((string) $row->correction_text);

            if (MistakeIdentity::isBlank($said, $correction)) {
                continue;
            }

            $identity = MistakeIdentity::pairKey($said, $correction);

            if (! isset($wanted[$identity])) {
                continue;
            }

            $counts[$identity] ??= ['study' => 0, 'all' => 0];
            $counts[$identity]['all'] += 1;

            if ($this->isStudySession($row)) {
                $counts[$identity]['study'] += 1;
            }
        }

        $states = [];

        foreach ($counts as $identity => $count) {
            $states[$identity] = $this->state($count['study'], $count['all']);
        }

        return $states;
    }

    /**
     * Questions, named by the row they belong to.
     *
     * @param  list<string>  $identities
     * @return array<string, ItemAvailability>
     */
    private function questionStates(array $identities): array
    {
        $ids = array_values(array_filter(array_map(intval(...), $identities)));

        if ($ids === []) {
            return [];
        }

        /** @var array<string, ItemAvailability> $states */
        $states = [];

        $rows = DB::table('practice_questions as q')
            ->join('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->whereIn('q.id', $ids)
            ->select(['q.id', 's.status', 's.archived_at'])
            ->get();

        foreach ($rows as $row) {
            $states[(string) $row->id] = $this->state(
                $this->isStudySession($row) ? 1 : 0,
                1,
            );
        }

        return $states;
    }

    /** Whether the session this row belongs to can be studied right now. */
    private function isStudySession(stdClass $row): bool
    {
        return (string) $row->status === PracticeSession::STATUS_FINISHED
            && $row->archived_at === null;
    }

    /**
     * The state an item with this many study rows and this many rows in all is
     * in: usable, kept but not available right now, or gone for good.
     */
    private function state(int $studyRows, int $allRows): ItemAvailability
    {
        return match (true) {
            $studyRows > 0 => ItemAvailability::Available,
            $allRows > 0 => ItemAvailability::Archived,
            default => ItemAvailability::Missing,
        };
    }
}
