<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\PracticeSession;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Thrown when a save targets a session that has been archived in the meantime.
 * The editor keeps the learner's text on screen and offers to restore the
 * session instead of silently losing the change.
 */
final class PracticeSessionArchivedException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly PracticeSession $session)
    {
        parent::__construct('This session was archived. Restore it to keep editing.');
    }
}
