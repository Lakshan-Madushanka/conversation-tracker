<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * One session as progress sees it: whether it is finished and how much it
 * holds, plus the moments it recorded a complete conversation. Kept apart from
 * the model so progress can be worked out and tested without touching the
 * database.
 */
final readonly class ProgressEntry
{
    /**
     * @param  list<CarbonImmutable>  $practiceMoments
     */
    public function __construct(
        public int $sessionId,
        public bool $finished,
        public int $words,
        public int $phrases,
        public int $mistakes,
        public int $grammarNotes,
        public array $practiceMoments,
    ) {}
}
