<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

/**
 * The answer after restoring a selection. Only the sessions that actually came
 * back are returned, so a repeated request changes nothing twice.
 */
final readonly class BulkRestoreResult
{
    /**
     * @param  list<int>  $restoredIds
     */
    public function __construct(
        public array $restoredIds,
        public int $skippedNotArchived,
        public int $skippedMissing,
    ) {}

    public function restoredCount(): int
    {
        return count($this->restoredIds);
    }
}
