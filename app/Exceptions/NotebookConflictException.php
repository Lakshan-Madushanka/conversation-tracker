<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Notebook;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Thrown when a save targets a version of a notebook that another browser tab
 * has already changed. It carries the latest saved notebook so the caller can
 * offer it to the learner instead of overwriting newer work.
 */
final class NotebookConflictException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly Notebook $notebook)
    {
        parent::__construct('This notebook was changed somewhere else.');
    }
}
