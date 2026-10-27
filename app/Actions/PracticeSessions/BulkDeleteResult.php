<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

/**
 * The answer after a permanent bulk delete. Sessions that were already gone
 * are counted as skipped instead of failing the whole request, so a repeated
 * confirmation stays safe.
 */
final readonly class BulkDeleteResult
{
    /**
     * @param  list<int>  $deletedIds
     */
    public function __construct(
        public array $deletedIds,
        public int $skippedMissing,
        public bool $alreadyDeleted,
    ) {}

    public function deletedCount(): int
    {
        return count($this->deletedIds);
    }
}
