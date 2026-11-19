<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\Notebook;
use App\Models\Topic;
use App\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;

/**
 * The notebook listing: newest first, counted in the database, narrowed by
 * topic, and searched across the notebook's own metadata, its topic, and its
 * own entries. A search never reaches another notebook's rows or a session.
 */
final class NotebooksListing
{
    public const int PAGE_SIZE = 20;

    /**
     * @return CursorPaginator<int, Notebook>
     */
    public function paginate(string $search, ?int $topic, ?string $cursor, int $perPage): CursorPaginator
    {
        return $this->query($search, $topic)
            ->with('topic')
            ->withCount([
                'entries as words_count' => fn (Builder $entries): Builder => $entries->where('type', FeedbackType::Vocabulary->value),
                'entries as phrases_count' => fn (Builder $entries): Builder => $entries->where('type', FeedbackType::Phrase->value),
                'entries as grammar_notes_count' => fn (Builder $entries): Builder => $entries->where('type', FeedbackType::GrammarNote->value),
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }

    public function total(string $search, ?int $topic): int
    {
        return $this->query($search, $topic)->count();
    }

    /**
     * The topics at least one notebook uses, ordered by name. A topic no
     * notebook uses is not offered, so the filter never lists an empty choice.
     *
     * @return list<array{id: int, name: string}>
     */
    public function topics(): array
    {
        /** @var list<array{id: int, name: string}> $topics */
        $topics = Topic::query()
            ->join('notebooks', 'notebooks.topic_id', '=', 'topics.id')
            ->distinct()
            ->orderBy('topics.name')
            ->get(['topics.id', 'topics.name'])
            ->map(fn (Topic $topic): array => [
                'id' => $topic->id,
                'name' => $topic->name,
            ])
            ->values()
            ->all();

        return $topics;
    }

    /**
     * @return Builder<Notebook>
     */
    private function query(string $search, ?int $topic): Builder
    {
        $query = Notebook::query();

        if ($topic !== null) {
            $query->where('topic_id', $topic);
        }

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
