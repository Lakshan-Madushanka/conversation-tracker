<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Exceptions\SessionDeletionBatchStaleException;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Queries\SessionDeletionBatchPreview;
use App\Support\DeletionToken;
use Illuminate\Support\Facades\DB;

/**
 * Removes a whole selection and everything inside those sessions, permanently.
 *
 * The confirmation carries the signed state the warning showed. The rows are
 * locked while that state is checked, so a selection changed in another tab is
 * refused instead of deleting content the learner has not seen. When the check
 * passes, every question, feedback entry and session is removed together in
 * one transaction, so nothing is left half-deleted.
 *
 * A selection that is already entirely gone is treated as success, which makes
 * a double click and a retried request safe.
 */
final readonly class DeletePracticeSessions
{
    public function __construct(private SessionDeletionBatchPreview $preview) {}

    /**
     * @param  list<int>  $sessionIds
     */
    public function handle(
        array $sessionIds,
        string $token,
        string $timezone,
    ): BulkDeleteResult {
        $sessionIds = array_values(array_unique($sessionIds));

        if ($sessionIds === []) {
            return new BulkDeleteResult([], 0, true);
        }

        return DB::transaction(function () use ($sessionIds, $token, $timezone): BulkDeleteResult {
            /** @var list<int> $foundIds */
            $foundIds = PracticeSession::query()
                ->whereIn('id', $sessionIds)
                ->lockForUpdate()
                ->pluck('id')
                ->map(intval(...))
                ->all();

            if ($foundIds === []) {
                return new BulkDeleteResult([], count($sessionIds), true);
            }

            $effect = $this->preview->forIds($sessionIds, $timezone);

            if (! DeletionToken::matches($token, $effect->fingerprint())) {
                throw new SessionDeletionBatchStaleException($effect);
            }

            $questionIds = PracticeQuestion::query()
                ->whereIn('practice_session_id', $foundIds)
                ->pluck('id')
                ->all();

            if ($questionIds !== []) {
                PracticeFeedbackEntry::query()
                    ->whereIn('practice_question_id', $questionIds)
                    ->delete();

                PracticeQuestion::query()
                    ->whereIn('id', $questionIds)
                    ->delete();
            }

            PracticeSession::query()
                ->whereIn('id', $foundIds)
                ->delete();

            return new BulkDeleteResult(
                deletedIds: $foundIds,
                skippedMissing: count($sessionIds) - count($foundIds),
                alreadyDeleted: false,
            );
        });
    }
}
