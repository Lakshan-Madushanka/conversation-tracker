<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

/**
 * The answer after archiving a selection. Only the sessions that actually
 * changed are returned, so Undo can restore exactly those and nothing else.
 */
final readonly class BulkArchiveResult
{
    /**
     * @param  list<int>  $archivedIds
     */
    public function __construct(
        public array $archivedIds,
        public int $skippedUnfinished,
        public int $skippedAlreadyArchived,
        public int $skippedMissing,
    ) {}

    public function archivedCount(): int
    {
        return count($this->archivedIds);
    }
}
