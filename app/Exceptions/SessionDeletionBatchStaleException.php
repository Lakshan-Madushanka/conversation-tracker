<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\SessionDeletionBatchEffect;
use RuntimeException;

/**
 * The bulk delete warning showed numbers that no longer match the selection.
 * The confirmation is refused and the fresh effect travels back, so the
 * learner can see the current numbers and confirm again. Nothing is deleted.
 */
final class SessionDeletionBatchStaleException extends RuntimeException
{
    public function __construct(public readonly SessionDeletionBatchEffect $effect)
    {
        parent::__construct(
            'These sessions changed since you opened the delete warning.',
        );
    }
}
