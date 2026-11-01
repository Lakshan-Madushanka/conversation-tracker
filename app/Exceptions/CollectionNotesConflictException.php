<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\CollectionItemMark;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Thrown when a personal note targets a version that another browser tab has
 * already changed. It carries the latest saved note so the caller can offer it
 * to the learner, choosing between the two versions, instead of overwriting
 * newer work.
 */
final class CollectionNotesConflictException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly CollectionItemMark $mark)
    {
        parent::__construct('These notes were changed somewhere else.');
    }
}
