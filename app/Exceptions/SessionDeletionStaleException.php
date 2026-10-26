<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\SessionDeletionEffect;
use RuntimeException;

/**
 * The delete warning showed numbers that no longer match the session. The
 * confirmation is refused and the fresh effect travels back, so the learner
 * can see the current numbers and confirm again.
 */
final class SessionDeletionStaleException extends RuntimeException
{
    public function __construct(public readonly SessionDeletionEffect $effect)
    {
        parent::__construct(
            'This session changed since you opened the delete warning.',
        );
    }
}
