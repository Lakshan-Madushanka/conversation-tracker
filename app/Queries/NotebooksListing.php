<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\Notebook;
use App\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;

/**
 * The notebook listing: newest first, counted in the database, and searched
 * across the notebook's own metadata, its topic, and its own entries. A search
 * never reaches another notebook's rows or a session.
 */
final class NotebooksListing
{
    public const int PAGE_SIZE = 20;

    /**
     * @return CursorPaginator<int, Notebook>
     */
    public function paginate(string $search, ?string $cursor, int $perPage): CursorPaginator
    {
        return $this->query($search)
            ->withCount([
                'entries as words_count' => fn (Builder $entries): Builder => $entries->where('type', FeedbackType::Vocabulary->value),
                'entries as phrases_count' => fn (Builder $entries): Builder => $entries->where('type', FeedbackType::Phrase->value),
                'entries as grammar_notes_count' => fn (Builder $entries): Builder => $entries->where('type', FeedbackType::GrammarNote->value),
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }

    public function total(string $search): int
    {
        return $this->query($search)->count();
    }

    /**
     * @return Builder<Notebook>
     */
    private function query(string $search): Builder
    {
        $query = Notebook::query();

        $terms = SearchText::terms($search);

        if ($terms === []) {
            return $query;
        }

        return $query->where(function (Builder $search) use ($terms): void {
            $search
                ->where(function (Builder $metadata) use ($terms): void {
                    SearchText::whereAll($metadata, 'notebooks.search_text', $terms);
                })
                ->orWhereHas('topic', function (Builder $topic) use ($terms): void {
                    SearchText::whereAll($topic, 'topics.search_text', $terms);
                })
                ->orWhereHas('entries', function (Builder $entries) use ($terms): void {
                    SearchText::whereAll($entries, 'practice_feedback_entries.search_text', $terms);
                });
        });
    }
}
