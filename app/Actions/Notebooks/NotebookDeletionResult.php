<?php

declare(strict_types=1);

namespace App\Actions\Notebooks;

/**
 * What a permanent notebook deletion did, so the page can answer calmly even
 * when the notebook had already gone.
 */
final readonly class NotebookDeletionResult
{
    /**
     * @param  array{words: int, phrases: int, grammar_notes: int}  $counts
     */
    public function __construct(
        public int $notebookId,
        public string $name,
        public array $counts,
        public bool $alreadyDeleted,
    ) {}
}
