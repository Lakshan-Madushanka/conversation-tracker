<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Models\PracticeSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Puts a whole selection away in one go. Archived sessions follow exactly the
 * same rule as a single archive: only finished sessions change, nothing inside
 * them is touched, and their own timestamps stay as they were.
 *
 * Sessions that cannot be archived are skipped and counted rather than failing
 * the whole action, so one unfinished or already-archived session in another
 * tab never blocks the rest.
 */
final readonly class ArchivePracticeSessions
{
    /**
     * @param  list<int>  $sessionIds
     */
    public function handle(array $sessionIds): BulkArchiveResult
    {
        $sessionIds = array_values(array_unique($sessionIds));

        if ($sessionIds === []) {
            return new BulkArchiveResult([], 0, 0, 0);
        }

        return DB::transaction(function () use ($sessionIds): BulkArchiveResult {
            /** @var Collection<int, PracticeSession> $sessions */
            $sessions = PracticeSession::query()
                ->whereIn('id', $sessionIds)
                ->lockForUpdate()
                ->get(['id', 'status', 'archived_at']);

            $archivableIds = [];
            $skippedUnfinished = 0;
            $skippedAlreadyArchived = 0;

            foreach ($sessions as $session) {
                if (! $session->isFinished()) {
                    $skippedUnfinished++;

                    continue;
                }

                if ($session->isArchived()) {
                    $skippedAlreadyArchived++;

                    continue;
                }

                $archivableIds[] = (int) $session->getKey();
            }

            if ($archivableIds !== []) {
                // The base query skips Eloquent's automatic timestamp update:
                // archiving is not an edit.
                PracticeSession::query()
                    ->toBase()
                    ->whereIn('id', $archivableIds)
                    ->update(['archived_at' => CarbonImmutable::now()]);
            }

            return new BulkArchiveResult(
                archivedIds: $archivableIds,
                skippedUnfinished: $skippedUnfinished,
                skippedAlreadyArchived: $skippedAlreadyArchived,
                skippedMissing: count($sessionIds) - $sessions->count(),
            );
        });
    }
}
