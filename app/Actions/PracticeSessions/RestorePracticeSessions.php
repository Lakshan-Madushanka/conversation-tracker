<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Models\PracticeSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Brings a whole selection back from the Archived view. Each session keeps its
 * content, status, dates and last edited day exactly as it was; only the
 * archived marker is cleared.
 *
 * Sessions that are no longer archived, or have since been deleted, are
 * skipped and counted so the rest still come back.
 */
final readonly class RestorePracticeSessions
{
    /**
     * @param  list<int>  $sessionIds
     */
    public function handle(array $sessionIds): BulkRestoreResult
    {
        $sessionIds = array_values(array_unique($sessionIds));

        if ($sessionIds === []) {
            return new BulkRestoreResult([], 0, 0);
        }

        return DB::transaction(function () use ($sessionIds): BulkRestoreResult {
            /** @var Collection<int, PracticeSession> $sessions */
            $sessions = PracticeSession::query()
                ->whereIn('id', $sessionIds)
                ->lockForUpdate()
                ->get(['id', 'archived_at']);

            $restorableIds = [];
            $skippedNotArchived = 0;

            foreach ($sessions as $session) {
                if (! $session->isArchived()) {
                    $skippedNotArchived++;

                    continue;
                }

                $restorableIds[] = (int) $session->getKey();
            }

            if ($restorableIds !== []) {
                // Restoring is not an edit either, so the row's own timestamps
                // stay exactly as they were.
                PracticeSession::query()
                    ->toBase()
                    ->whereIn('id', $restorableIds)
                    ->update(['archived_at' => null]);
            }

            return new BulkRestoreResult(
                restoredIds: $restorableIds,
                skippedNotArchived: $skippedNotArchived,
                skippedMissing: count($sessionIds) - $sessions->count(),
            );
        });
    }
}
