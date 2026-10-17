<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\PracticeSession;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Thrown when a save targets a version of a session that another browser tab
 * has already changed. It carries the latest saved session so the caller can
 * offer it to the learner instead of overwriting newer work.
 */
final class PracticeSessionConflictException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly PracticeSession $session)
    {
        parent::__construct('This session was changed somewhere else.');
    }
}
