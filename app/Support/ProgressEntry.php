<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * One finished session as progress sees it: when it happened and how much it
 * held. Kept apart from the model so progress can be worked out and tested
 * without touching the database.
 */
final readonly class ProgressEntry
{
    public function __construct(
        public int $sessionId,
        public ?CarbonImmutable $finishedAt,
        public int $words,
        public int $phrases,
        public int $mistakes,
        public int $grammarNotes,
    ) {}
}
