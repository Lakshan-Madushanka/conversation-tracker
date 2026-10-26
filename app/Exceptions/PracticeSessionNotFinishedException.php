<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Thrown when archiving is asked for a session the learner has not finished.
 * Only finished sessions can be archived, and the caller says so calmly.
 */
final class PracticeSessionNotFinishedException extends RuntimeException implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('Finish this session to archive it.');
    }
}
