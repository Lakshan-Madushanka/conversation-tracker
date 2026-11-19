<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\NotebookDeletionEffect;
use RuntimeException;

/**
 * The delete warning showed numbers that no longer match the notebook. The
 * confirmation is refused and the fresh effect travels back, so the learner
 * can see the current numbers and confirm again.
 */
final class NotebookDeletionStaleException extends RuntimeException
{
    public function __construct(public readonly NotebookDeletionEffect $effect)
    {
        parent::__construct(
            'This notebook changed since you opened the delete warning.',
        );
    }
}
