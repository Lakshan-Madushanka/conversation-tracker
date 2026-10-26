<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Exceptions\PracticeSessionNotFinishedException;
use App\Models\PracticeSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Hides a finished session from the Library, Home and search by recording the
 * moment it was archived. Nothing in the session is moved, copied or changed,
 * so it can be restored exactly as it was.
 *
 * Only finished sessions can be archived, and the rule is enforced here as
 * well as on screen. Archiving an already-archived session changes nothing, so
 * a repeated request is always safe.
 */
final readonly class ArchivePracticeSession
{
    public function handle(PracticeSession $session): PracticeSession
    {
        return DB::transaction(function () use ($session): PracticeSession {
            /** @var PracticeSession $locked */
            $locked = PracticeSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isFinished()) {
                throw new PracticeSessionNotFinishedException;
            }

            if ($locked->isArchived()) {
                return $locked;
            }

            // Archiving is not an edit: the last edited day, the version and
            // the row's own timestamps all stay exactly as they were.
            $locked->timestamps = false;
            $locked->archived_at = CarbonImmutable::now();
            $locked->save();
            $locked->timestamps = true;

            return $locked;
        });
    }
}
