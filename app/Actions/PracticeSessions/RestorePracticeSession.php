<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Models\PracticeSession;
use Illuminate\Support\Facades\DB;

/**
 * Brings an archived session back to the Library exactly as it was. Its
 * content, status, dates and last edited day are left untouched; only the
 * archived marker is cleared. Restoring a session that is not archived changes
 * nothing, so a repeated request is always safe.
 */
final readonly class RestorePracticeSession
{
    public function handle(PracticeSession $session): PracticeSession
    {
        return DB::transaction(function () use ($session): PracticeSession {
            /** @var PracticeSession $locked */
            $locked = PracticeSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isArchived()) {
                return $locked;
            }

            $locked->timestamps = false;
            $locked->archived_at = null;
            $locked->save();
            $locked->timestamps = true;

            return $locked;
        });
    }
}
