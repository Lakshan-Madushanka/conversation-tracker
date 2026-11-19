<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Notebook;

/**
 * Everything the notebook delete warning needs: which notebook is going, how
 * many saved rows and distinct study items it holds, and the saved custom
 * reviews that would lose items.
 *
 * The fingerprint names the state the learner saw. The signed token made from
 * it travels back with the confirmation, so a notebook or review changed in
 * another tab is refused instead of being deleted under stale numbers.
 */
final readonly class NotebookDeletionEffect
{
    /**
     * @param  array{words: int, phrases: int, grammar_notes: int}  $counts  saved rows by kind
     * @param  array<string, list<string>>  $items  distinct scoped identities by kind value
     * @param  list<array{id: int, name: string, removed: int, remaining: int, becomes_empty: bool}>  $reviews
     */
    public function __construct(
        public Notebook $notebook,
        public array $counts,
        public array $items,
        public array $reviews,
    ) {}

    public function token(): string
    {
        return DeletionToken::sign($this->fingerprint());
    }

    /**
     * The state the warning described. Any content edit, any added or removed
     * review reference or a renamed notebook makes this fingerprint different.
     */
    public function fingerprint(): string
    {
        $items = $this->items;

        ksort($items);

        foreach ($items as $kind => $identities) {
            sort($identities);
            $items[$kind] = $identities;
        }

        $reviews = $this->reviews;

        usort($reviews, fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return json_encode([
            'notebook' => [
                'id' => $this->notebook->id,
                'name' => $this->notebook->name,
                'topic_id' => $this->notebook->topic_id,
                'version' => $this->notebook->version,
                'last_changed_at' => $this->notebook->last_changed_at?->toIso8601String(),
            ],
            'counts' => $this->counts,
            'items' => $items,
            'reviews' => $reviews,
        ], JSON_THROW_ON_ERROR);
    }
}
