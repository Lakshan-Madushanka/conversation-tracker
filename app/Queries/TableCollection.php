<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CollectionSort;
use App\Enums\CollectionSource;
use App\Enums\CollectionStatusFilter;
use App\Enums\CollectionTab;
use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeSession;
use App\Support\BlankExercise;
use App\Support\CollectionCursor;
use App\Support\CollectionFilters;
use App\Support\EntrySearch;
use App\Support\MistakeIdentity;
use App\Support\NotePreview;
use App\Support\RichText;
use App\Support\RowAttention;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One collection, gathered live from the study sessions: the vocabulary and
 * phrase rows, grouped by the words themselves; the grammar notes, which
 * are never merged — every note stays its own item; and the mistakes,
 * grouped by the pair of what was said and the correction.
 *
 * A word or phrase is the same word when it differs only in capitals,
 * accents, extra spaces or formatting. A mistake is the same mistake when
 * both parts of its pair match that way; the pair's key comes from
 * `MistakeIdentity`. Every row it was saved in is kept as an appearance, but
 * two copies in the same box of the same question count once, because the
 * learner wrote the word once and the editor repeated the row.
 *
 * Nothing is stored or cached: a finished session, an edit, an archive, a
 * restore or a delete changes the next read straight away.
 *
 * @phpstan-type CollectionNotebook array{id: int, name: string, topic: string}
 * @phpstan-type GroupRow array{
 *     entry_id: string|null,
 *     entry_position: int,
 *     meaning: string,
 *     example: string,
 *     example_html: string
 * }
 * @phpstan-type GroupAppearance array{
 *     session_id: int,
 *     session_date: CarbonImmutable|null,
 *     topic: string,
 *     question_position: int,
 *     entry_id: string|null,
 *     entry_position: int,
 *     meaning: string,
 *     example: string,
 *     example_html: string
 * }
 * @phpstan-type GroupItem array{
 *     key: string,
 *     title: string,
 *     meaning: string,
 *     example: string,
 *     example_html: string,
 *     latest_at: CarbonImmutable,
 *     seen_count: int|null,
 *     attention: string|null,
 *     session_id: int|null,
 *     session_date: CarbonImmutable|null,
 *     topic: string,
 *     question_position: int|null,
 *     source: 'session'|'notebook',
 *     notebook: CollectionNotebook|null,
 *     rows: list<GroupRow>,
 *     appearances: list<GroupAppearance>
 * }
 * @phpstan-type NoteItem array{
 *     key: string,
 *     text: string,
 *     preview: string,
 *     html: string,
 *     latest_at: CarbonImmutable,
 *     session_id: int|null,
 *     session_date: CarbonImmutable|null,
 *     topic: string,
 *     question_position: int|null,
 *     entry_id: string|null,
 *     entry_position: int,
 *     source: 'session'|'notebook',
 *     notebook: CollectionNotebook|null
 * }
 * @phpstan-type MistakeAppearance array{
 *     session_id: int,
 *     session_date: CarbonImmutable|null,
 *     topic: string,
 *     question_position: int,
 *     entry_id: string|null,
 *     entry_position: int,
 *     said: string,
 *     correction: string,
 *     correction_html: string,
 *     reason: string
 * }
 * @phpstan-type MistakeItem array{
 *     key: string,
 *     title: string,
 *     correction: string,
 *     correction_html: string,
 *     reason: string,
 *     latest_at: CarbonImmutable,
 *     seen_count: int,
 *     worth_practicing: bool,
 *     attention: string|null,
 *     source: 'session',
 *     notebook: null,
 *     rows: list<GroupRow>,
 *     appearances: list<MistakeAppearance>
 * }
 */
final class TableCollection
{
    /** How many items one page of a collection holds. */
    public const int PAGE_SIZE = 30;

    /**
     * How often a mistake has to be seen before it is worth practicing.
     * Keeping the threshold here in one named place makes it easy to change:
     * a mistake seen this many times, or more, carries the label.
     */
    public const int WORTH_PRACTICING_SEEN_COUNT = 3;

    /** How much of a note's plain text the list shows before it is opened. */
    public const int NOTE_PREVIEW_LENGTH = NotePreview::LENGTH;

    /**
     * How many rows one box can hold, which keeps the "question then row"
     * order of a note inside one number the cursor can carry.
     */
    private const int NOTE_ORDER_STEP = 1000;

    /**
     * One page, newest session first and then — for grouped items —
     * alphabetically, for notes by their place in the session. The cursor
     * names the last item shown, so a row saved while the list is open can
     * never make a later page repeat or skip one.
     *
     * Grouping, filtering, searching, ordering and the total are worked out by
     * the database. Only the items of one page are read in full, so the page
     * stays fast however many rows the collection gathers.
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     next_cursor: string|null,
     *     has_more: bool,
     *     total: int
     * }
     */
    public function page(
        FeedbackType $type,
        ?string $cursor = null,
        int $perPage = self::PAGE_SIZE,
        ?CollectionFilters $filters = null,
        bool $eligibleOnly = false,
    ): array {
        $filters ??= CollectionFilters::for($type);
        $perPage = max(1, $perPage);

        return $type === FeedbackType::GrammarNote
            ? $this->notesPage($type, $filters, $cursor, $perPage, $eligibleOnly)
            : $this->groupedPage($type, $filters, $cursor, $perPage, $eligibleOnly);
    }

    /**
     * A page of grouped words, phrases or mistakes, read from the database as
     * one group per item and then filled in from the rows of that page alone.
     *
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null, has_more: bool, total: int}
     */
    private function groupedPage(
        FeedbackType $type,
        CollectionFilters $filters,
        ?string $cursor,
        int $perPage,
        bool $eligibleOnly = false,
    ): array {
        $grouped = $this->groupedQuery($type, $filters, $eligibleOnly);
        $page = DB::query()->fromSub($grouped, 'g');

        $total = (clone $page)->count();
        $this->applyGroupedCursor($page, $filters->sort(), $this->cursorForSort($cursor, $filters->sort()));

        $this->orderGrouped($page, $filters->sort());

        $rows = $page->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $shown = $rows->take($perPage)->values();

        $nextCursor = null;

        if ($hasMore && $shown->isNotEmpty()) {
            $last = $shown->last();

            $nextCursor = $this->groupedCursor(
                (string) $last->group_key,
                (string) $last->latest_at,
                (int) $last->seen_count,
                $last->sort_text === null ? null : (string) $last->sort_text,
                $filters->sort(),
            )->encode();
        }

        /** @var list<string> $keys */
        $keys = $shown
            ->pluck('group_key')
            ->map(fn (mixed $key): string => (string) $key)
            ->all();

        return [
            'items' => $this->buildForKeys($type, $keys),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'total' => $total,
        ];
    }

    /**
     * A page of grammar notes. Every note is its own item, so the rows are the
     * items and only their ordering, filtering and search need the database.
     *
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null, has_more: bool, total: int}
     */
    private function notesPage(
        FeedbackType $type,
        CollectionFilters $filters,
        ?string $cursor,
        int $perPage,
        bool $eligibleOnly = false,
    ): array {
        $base = $this->notesQuery($filters);

        if ($eligibleOnly) {
            $base->whereIn('e.id', $this->eligibleNoteIds($base));
        }

        $total = (clone $base)->count();

        // The cursor and the order both read the moment and the note's place
        // after they are worked out, so the rows are paged as a subquery.
        $notes = DB::query()->fromSub($base, 'n');

        $from = $this->cursorForSort($cursor, $filters->sort());
        $this->applyNotesCursor($notes, $filters->sort(), $from);
        $this->orderNotes($notes, $filters->sort());

        $rows = $notes->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $shown = $rows->take($perPage)->values();

        $nextCursor = null;

        if ($hasMore && $shown->isNotEmpty()) {
            $last = $shown->last();

            $nextCursor = $this->noteCursor(
                (int) $last->id,
                (string) $last->latest_at,
                (int) $last->note_order,
                $last->sort_text === null ? null : (string) $last->sort_text,
                $filters->sort(),
            )->encode();
        }

        /** @var list<string> $keys */
        $keys = $shown
            ->pluck('id')
            ->map(fn (mixed $id): string => 'note-'.(int) $id)
            ->all();

        return [
            'items' => $this->buildForKeys($type, $keys),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'total' => $total,
        ];
    }

    /**
     * The grouped rows of one collection, narrowed by every filter and search
     * term before anything is counted or ordered.
     */
    private function groupedQuery(
        FeedbackType $type,
        CollectionFilters $filters,
        bool $eligibleOnly = false,
    ): QueryBuilder {
        $query = $this->scopedEntries($type);

        $this->applyNonBlank($query, $type);
        $this->applySourceFilter($query, $filters);

        if ($eligibleOnly) {
            $this->applyEligibleFilter($query, $type);
        }

        $this->applyMarkJoin($query);
        $this->applyMarkFilters($query, $filters);
        $this->applyTopicFilter($query, $type, $filters);

        if ($filters->onlyNeedsAttention()) {
            $this->applyAttentionFilter($query, $type);
        }

        $this->applyGroupSearch($query, $type, $filters);

        // A grouped item is always from one source, because a notebook key
        // and a session key can never be equal. Its date is the latest session
        // it appeared in, or the moment its notebook row was first saved.
        $moment = 'COALESCE(s.created_at, e.created_at)';

        return $query
            ->groupBy('e.collection_key')
            ->select([
                'e.collection_key as group_key',
                DB::raw('MAX('.$moment.') as latest_at'),
                DB::raw('MAX('.$this->secondsOf($moment).') as latest_seconds'),
                // Notebook rows have no question, so they count zero. The item
                // shape reports null for them rather than a "seen 0 times".
                DB::raw('COUNT(DISTINCT e.practice_question_id) as seen_count'),
                DB::raw('MIN(e.sort_text) as sort_text'),
            ]);
    }

    /**
     * The grammar notes, narrowed by every filter and search term.
     */
    private function notesQuery(CollectionFilters $filters): QueryBuilder
    {
        $query = $this->scopedEntries(FeedbackType::GrammarNote)
            ->whereRaw("TRIM(e.content_text) <> ''");

        $this->applySourceFilter($query, $filters);
        $this->applyMarkJoin($query);
        $this->applyMarkFilters($query, $filters);

        $topics = $filters->topics();

        if ($topics !== []) {
            $query->whereIn(
                DB::raw('COALESCE(s.topic_id, nb.topic_id)'),
                $topics,
            );
        }

        $this->applyNoteSearch($query, $filters);

        return $query->select([
            'e.id',
            'e.collection_key as group_key',
            'e.sort_text',
            DB::raw('COALESCE(s.created_at, e.created_at) as latest_at'),
            DB::raw($this->secondsOf('COALESCE(s.created_at, e.created_at)').' as latest_seconds'),
            // One number that keeps a note's place inside its question, or the
            // notebook's saved order, so the cursor can carry it.
            DB::raw('(CASE WHEN e.notebook_id IS NOT NULL THEN e.position ELSE q.position * '.self::NOTE_ORDER_STEP.' + e.position END) as note_order'),
        ]);
    }

    /**
     * The rows of one collection from both sources. A session row counts only
     * while its session is finished and visible; a notebook row always counts,
     * because a notebook has no state to finish or archive.
     */
    private function scopedEntries(FeedbackType $type): QueryBuilder
    {
        $query = DB::table('practice_feedback_entries as e')
            ->leftJoin('practice_questions as q', 'q.id', '=', 'e.practice_question_id')
            ->leftJoin('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->leftJoin('notebooks as nb', 'nb.id', '=', 'e.notebook_id')
            ->where('e.type', $type->value);

        $this->applyRowScope($query, 'e', 's');

        return $query;
    }

    /**
     * A row counts when it lives in a notebook, or in a session the learner
     * finished and has not archived.
     */
    private function applyRowScope(QueryBuilder $query, string $entry, string $session): void
    {
        $query->where(function (QueryBuilder $scope) use ($entry, $session): void {
            $scope->whereNotNull($entry.'.notebook_id')
                ->orWhere(function (QueryBuilder $live) use ($session): void {
                    $live->where($session.'.status', PracticeSession::STATUS_FINISHED)
                        ->whereNull($session.'.archived_at');
                });
        });
    }

    private function applySourceFilter(QueryBuilder $query, CollectionFilters $filters): void
    {
        match ($filters->source()) {
            CollectionSource::Sessions => $query->whereNull('e.notebook_id'),
            CollectionSource::Notebooks => $query->whereNotNull('e.notebook_id'),
            CollectionSource::All => null,
        };

        if ($filters->notebook() !== null) {
            $query->where('e.notebook_id', $filters->notebook());
        }
    }

    private function applyNonBlank(QueryBuilder $query, FeedbackType $type): void
    {
        if ($type === FeedbackType::Mistake) {
            $query->where(function (QueryBuilder $instead): void {
                $instead
                    ->whereRaw("TRIM(e.content_text) <> ''")
                    ->orWhereRaw("TRIM(e.correction_text) <> ''");
            });

            return;
        }

        $query->whereRaw("TRIM(e.content_text) <> ''");
    }

    /**
     * Keep only the groups a custom review can actually use: a word or phrase
     * needs a meaning anywhere in the group, and a mistake needs both what was
     * said and a correction.
     *
     * The check is correlated on `collection_key` instead of narrowing the
     * rows, so a group that is kept still carries every appearance it has and
     * its seen count stays whole.
     */
    private function applyEligibleFilter(QueryBuilder $query, FeedbackType $type): void
    {
        $usable = match ($type) {
            FeedbackType::Vocabulary, FeedbackType::Phrase => "TRIM(a.correction_text) <> ''",
            FeedbackType::Mistake => "TRIM(a.content_text) <> '' AND TRIM(a.correction_text) <> ''",
            default => null,
        };

        if ($usable === null) {
            return;
        }

        $query->whereExists(
            $this->sameItemRows($type)
                ->whereRaw($usable),
        );
    }

    /**
     * The rows of the same scoped item, from either source, with the session
     * state already checked. A notebook item can only meet notebook rows and a
     * session item can only meet live session rows, because the keys never
     * cross sources.
     */
    private function sameItemRows(FeedbackType $type): QueryBuilder
    {
        $query = DB::table('practice_feedback_entries as a')
            ->leftJoin('practice_questions as aq', 'aq.id', '=', 'a.practice_question_id')
            ->leftJoin('practice_sessions as ss', 'ss.id', '=', 'aq.practice_session_id')
            ->whereColumn('a.collection_key', 'e.collection_key')
            ->where('a.type', $type->value);

        $this->applyRowScope($query, 'a', 'ss');

        return $query;
    }

    /**
     * The grammar notes of the current view that can still make the
     * fill-in-the-blank exercise. The rule is the one `BlankExercise` owns, so
     * this reads the note rows, asks it about each, and hands back the ids.
     *
     * The base query is cloned, so the caller's filters, search and ordering
     * stay untouched.
     *
     * @return list<int>
     */
    private function eligibleNoteIds(QueryBuilder $base): array
    {
        $rows = (clone $base)
            ->select(['e.id', 'e.content_text', 'e.content_html'])
            ->distinct()
            ->get();

        $ids = [];

        foreach ($rows as $row) {
            $note = new PracticeFeedbackEntry;
            $note->forceFill([
                'type' => FeedbackType::GrammarNote,
                'content_html' => (string) ($row->content_html ?? ''),
                'content_text' => (string) $row->content_text,
            ]);

            if (BlankExercise::build($note) !== null) {
                $ids[] = (int) $row->id;
            }
        }

        return $ids;
    }

    private function applyMarkJoin(QueryBuilder $query): void
    {
        $query->leftJoin('collection_item_marks as m', function (JoinClause $join): void {
            $join->on('m.group_key', '=', 'e.collection_key')
                ->on('m.kind', '=', 'e.type');
        });
    }

    private function applyMarkFilters(QueryBuilder $query, CollectionFilters $filters): void
    {
        if ($filters->starred()) {
            $query->where('m.starred', true);
        }

        $status = $filters->status();

        if ($status === CollectionStatusFilter::NotMarked) {
            $query->whereNull('m.status');

            return;
        }

        if ($status->statusValue() !== null) {
            $query->where('m.status', $status->statusValue());
        }
    }

    /**
     * Items that appeared in any of the chosen topics. The group keeps every
     * appearance it has, so its meaning, example and seen count stay whole.
     */
    private function applyTopicFilter(
        QueryBuilder $query,
        FeedbackType $type,
        CollectionFilters $filters,
    ): void {
        $topics = $filters->topics();

        if ($topics === []) {
            return;
        }

        $query->whereExists(function (QueryBuilder $exists) use ($type, $topics): void {
            $exists->selectRaw('1')
                ->from('practice_feedback_entries as te')
                ->leftJoin('practice_questions as tq', 'tq.id', '=', 'te.practice_question_id')
                ->leftJoin('practice_sessions as ts', 'ts.id', '=', 'tq.practice_session_id')
                ->leftJoin('notebooks as tn', 'tn.id', '=', 'te.notebook_id')
                ->whereColumn('te.collection_key', 'e.collection_key')
                ->where('te.type', $type->value)
                ->whereIn(DB::raw('COALESCE(ts.topic_id, tn.topic_id)'), $topics);
            $this->applyRowScope($exists, 'te', 'ts');
        });
    }

    /**
     * Why one grouped item asks for attention, by the same rules the item
     * shape uses: a word with no meaning anywhere, a phrase whose line could
     * not be split, or a mistake whose newest row still needs a correction.
     */
    private function applyAttentionFilter(QueryBuilder $query, FeedbackType $type): void
    {
        match ($type) {
            FeedbackType::Vocabulary => $query->whereNotExists(
                $this->sameItemRows($type)->whereRaw("TRIM(a.correction_text) <> ''"),
            ),
            FeedbackType::Phrase => $query->whereExists(
                $this->sameItemRows($type)->where('a.split_broke', true),
            ),
            FeedbackType::Mistake => $query->whereRaw($this->newestMistakeAttentionSql()),
            default => null,
        };
    }

    /**
     * A mistake's newest row still needs attention. Mistakes live only in
     * sessions, so this keeps its one-source rule.
     *
     * @return literal-string
     */
    private function newestMistakeAttentionSql(): string
    {
        return '(SELECT a.attention_reason
            FROM practice_feedback_entries a
            INNER JOIN practice_questions as aq ON aq.id = a.practice_question_id
            INNER JOIN practice_sessions as ss ON ss.id = aq.practice_session_id
            WHERE a.collection_key = e.collection_key
                AND a.type = \'mistake\'
                AND ss.status = \'finished\'
                AND ss.archived_at IS NULL
            ORDER BY ss.created_at DESC, a.created_at DESC, a.id DESC
            LIMIT 1) IS NOT NULL';
    }

    private function applyGroupSearch(
        QueryBuilder $query,
        FeedbackType $type,
        CollectionFilters $filters,
    ): void {
        foreach ($filters->terms() as $term) {
            $pattern = SearchText::likePattern($term);

            $query->where(function (QueryBuilder $match) use ($pattern, $type): void {
                $match->whereExists(function (QueryBuilder $exists) use ($pattern, $type): void {
                    $exists->selectRaw('1')
                        ->from('practice_feedback_entries as se')
                        ->leftJoin('practice_questions as sq', 'sq.id', '=', 'se.practice_question_id')
                        ->leftJoin('practice_sessions as ss', 'ss.id', '=', 'sq.practice_session_id')
                        ->whereColumn('se.collection_key', 'e.collection_key')
                        ->where('se.type', $type->value)
                        ->whereRaw("se.search_text LIKE ? ESCAPE '!'", [$pattern]);
                    $this->applyRowScope($exists, 'se', 'ss');
                })->orWhereRaw("m.notes_search_text LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
    }

    private function applyNoteSearch(QueryBuilder $query, CollectionFilters $filters): void
    {
        foreach ($filters->terms() as $term) {
            $pattern = SearchText::likePattern($term);

            $query->where(function (QueryBuilder $match) use ($pattern): void {
                $match
                    ->whereRaw("e.search_text LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("m.notes_search_text LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
    }

    /**
     * A cursor only means something for the order it was made for. Anything
     * else is quietly ignored and the list starts at the top.
     */
    private function cursorForSort(?string $value, CollectionSort $sort): ?CollectionCursor
    {
        $cursor = CollectionCursor::decode($value);

        if (! $cursor instanceof CollectionCursor) {
            return null;
        }

        return match ($sort) {
            CollectionSort::Alphabetical => $cursor->sortText === null ? null : $cursor,
            CollectionSort::MostSeen => $cursor->position === null ? null : $cursor,
            CollectionSort::Newest => $cursor,
        };
    }

    private function applyGroupedCursor(
        QueryBuilder $query,
        CollectionSort $sort,
        ?CollectionCursor $cursor,
    ): void {
        if (! $cursor instanceof CollectionCursor) {
            return;
        }

        $time = $cursor->latestAt->getTimestamp();

        if ($sort === CollectionSort::Alphabetical) {
            $query->where(function (QueryBuilder $ordered) use ($cursor): void {
                $ordered
                    ->whereRaw("COALESCE(g.sort_text, '') > ?", [$cursor->sortText])
                    ->orWhere(function (QueryBuilder $tie) use ($cursor): void {
                        $tie->whereRaw("COALESCE(g.sort_text, '') = ?", [$cursor->sortText])
                            ->where('g.group_key', '>', $cursor->key);
                    });
            });

            return;
        }

        if ($sort === CollectionSort::MostSeen) {
            $seen = (int) $cursor->position;

            $query->where(function (QueryBuilder $ordered) use ($seen, $time, $cursor): void {
                $ordered
                    ->where('g.seen_count', '<', $seen)
                    ->orWhere(function (QueryBuilder $tie) use ($seen, $time, $cursor): void {
                        $tie->where('g.seen_count', '=', $seen)
                            ->where(function (QueryBuilder $later) use ($time, $cursor): void {
                                $later
                                    ->where('g.latest_seconds', '<', $time)
                                    ->orWhere(function (QueryBuilder $same) use ($time, $cursor): void {
                                        $same->where('g.latest_seconds', '=', $time)
                                            ->where('g.group_key', '>', $cursor->key);
                                    });
                            });
                    });
            });

            return;
        }

        $query->where(function (QueryBuilder $ordered) use ($time, $cursor): void {
            $ordered
                ->where('g.latest_seconds', '<', $time)
                ->orWhere(function (QueryBuilder $tie) use ($time, $cursor): void {
                    $tie->where('g.latest_seconds', '=', $time)
                        ->where('g.group_key', '>', $cursor->key);
                });
        });
    }

    private function orderGrouped(QueryBuilder $query, CollectionSort $sort): void
    {
        match ($sort) {
            CollectionSort::Alphabetical => $query
                ->orderByRaw("COALESCE(g.sort_text, '') asc")
                ->orderBy('g.group_key'),
            CollectionSort::MostSeen => $query
                ->orderByDesc('g.seen_count')
                ->orderByDesc('g.latest_seconds')
                ->orderBy('g.group_key'),
            CollectionSort::Newest => $query
                ->orderByDesc('g.latest_seconds')
                ->orderBy('g.group_key'),
        };
    }

    private function groupedCursor(
        string $key,
        string $latestAt,
        int $seenCount,
        ?string $sortText,
        CollectionSort $sort,
    ): CollectionCursor {
        return new CollectionCursor(
            $key,
            CarbonImmutable::parse($latestAt),
            $sort === CollectionSort::MostSeen ? $seenCount : null,
            $sort === CollectionSort::Alphabetical
                ? ($sortText ?? $key)
                : null,
        );
    }

    private function applyNotesCursor(
        QueryBuilder $query,
        CollectionSort $sort,
        ?CollectionCursor $cursor,
    ): void {
        if (! $cursor instanceof CollectionCursor) {
            return;
        }

        $id = $this->noteId($cursor->key);

        if ($id === null) {
            return;
        }

        if ($sort === CollectionSort::Alphabetical) {
            $query->where(function (QueryBuilder $ordered) use ($cursor, $id): void {
                $ordered
                    ->whereRaw("COALESCE(n.sort_text, '') > ?", [$cursor->sortText])
                    ->orWhere(function (QueryBuilder $tie) use ($cursor, $id): void {
                        $tie->whereRaw("COALESCE(n.sort_text, '') = ?", [$cursor->sortText])
                            ->where('n.id', '>', $id);
                    });
            });

            return;
        }

        if ($cursor->position === null) {
            return;
        }

        $time = $cursor->latestAt->getTimestamp();
        $place = $cursor->position;

        $query->where(function (QueryBuilder $ordered) use ($time, $place, $id): void {
            $ordered
                ->where('n.latest_seconds', '<', $time)
                ->orWhere(function (QueryBuilder $tie) use ($time, $place, $id): void {
                    $tie->where('n.latest_seconds', '=', $time)
                        ->where(function (QueryBuilder $later) use ($place, $id): void {
                            $later
                                ->where('n.note_order', '>', $place)
                                ->orWhere(function (QueryBuilder $same) use ($place, $id): void {
                                    $same->where('n.note_order', '=', $place)
                                        ->where('n.id', '>', $id);
                                });
                        });
                });
        });
    }

    private function orderNotes(QueryBuilder $query, CollectionSort $sort): void
    {
        if ($sort === CollectionSort::Alphabetical) {
            $query
                ->orderByRaw("COALESCE(n.sort_text, '') asc")
                ->orderBy('n.id');

            return;
        }

        $query
            ->orderByDesc('n.latest_seconds')
            ->orderBy('n.note_order')
            ->orderBy('n.id');
    }

    private function noteCursor(
        int $id,
        string $latestAt,
        int $noteOrder,
        ?string $sortText,
        CollectionSort $sort,
    ): CollectionCursor {
        return new CollectionCursor(
            'note-'.$id,
            CarbonImmutable::parse($latestAt),
            $sort === CollectionSort::Alphabetical ? null : $noteOrder,
            $sort === CollectionSort::Alphabetical ? ($sortText ?? '') : null,
        );
    }

    private function noteId(string $key): ?int
    {
        return preg_match('/^note-(\d+)$/', $key, $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    /**
     * A timestamp cut to whole seconds, the resolution the cursor carries. One
     * expression keeps SQLite and MySQL agreeing about what "the same moment"
     * means.
     *
     * @param  literal-string  $expression
     * @return literal-string
     */
    private function secondsOf(string $expression): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%s', {$expression}) AS INTEGER)"
            : "CAST(UNIX_TIMESTAMP({$expression}) AS SIGNED)";
    }

    /**
     * The items named by one page's keys, in the order the page chose. Only
     * the questions those items appear in are read, so the work stays bounded
     * by the page rather than by the whole collection.
     *
     * The list needs only the newest appearance of each item, while an opened
     * item needs every place it was seen. `$withAllAppearances` says which is
     * being built, so a page of very common items never carries their whole
     * history.
     *
     * @param  list<string>  $keys
     * @return list<GroupItem|NoteItem|MistakeItem>
     */
    private function buildForKeys(
        FeedbackType $type,
        array $keys,
        bool $withAllAppearances = false,
    ): array {
        $keys = array_values(array_filter(
            $keys,
            fn (string $key): bool => $key !== '',
        ));

        if ($keys === []) {
            return [];
        }

        $matching = $this->entryQuery($type);

        if ($type === FeedbackType::GrammarNote) {
            $ids = [];

            foreach ($keys as $key) {
                $id = $this->noteId($key);

                if ($id !== null) {
                    $ids[] = $id;
                }
            }

            if ($ids === []) {
                return [];
            }

            $matching->whereIn('practice_feedback_entries.id', $ids);
        } else {
            $matching->whereIn('practice_feedback_entries.collection_key', $keys);
        }

        // A notebook row has no question, so the joins stay left joins and the
        // notebook's own topic is loaded beside the session's.
        $entries = $matching
            ->leftJoin('practice_questions as sort_question', 'sort_question.id', '=', 'practice_feedback_entries.practice_question_id')
            ->leftJoin('practice_sessions as sort_session', 'sort_session.id', '=', 'sort_question.practice_session_id')
            ->select('practice_feedback_entries.*')
            ->selectSub($this->entryPositionQuery($type), 'entry_position')
            ->with(['practiceQuestion.practiceSession.topic', 'notebook.topic'])
            ->get();

        /** @var array<string, list<PracticeFeedbackEntry>> $byKey */
        $byKey = [];

        foreach ($entries as $entry) {
            $key = $this->entryKey($type, $entry);

            if ($key !== null) {
                $byKey[$key][] = $entry;
            }
        }

        $ordered = [];

        foreach ($keys as $key) {
            if (! isset($byKey[$key])) {
                continue;
            }

            $rows = collect($this->sortGroup($byKey[$key]));

            $items = match ($type) {
                FeedbackType::GrammarNote => $this->notesFromEntries($rows, [$key]),
                FeedbackType::Mistake => $this->mistakesFromEntries($rows, [$key], $withAllAppearances),
                default => $this->groupsFromEntries($type, $rows, [$key], $withAllAppearances),
            };

            if (isset($items[$key])) {
                $ordered[] = $items[$key];
            }
        }

        return $ordered;
    }

    /** The scoped identity one loaded row carries, for gathering it again. */
    private function entryKey(FeedbackType $type, PracticeFeedbackEntry $entry): ?string
    {
        if ($type === FeedbackType::GrammarNote) {
            return $this->noteKey($entry);
        }

        if ($type === FeedbackType::Mistake) {
            $said = (string) $entry->content_text;
            $correction = (string) $entry->correction_text;

            return MistakeIdentity::isBlank($said, $correction)
                ? null
                : MistakeIdentity::pairKey($said, $correction);
        }

        $key = (string) ($entry->collection_key ?? '');

        return $key === '' ? null : $key;
    }

    /**
     * One item's rows in the order it shows them: a session item newest
     * appearance first, a notebook item in the order the learner saved it.
     *
     * @param  list<PracticeFeedbackEntry>  $entries
     * @return list<PracticeFeedbackEntry>
     */
    private function sortGroup(array $entries): array
    {
        $notebook = isset($entries[0]) && $entries[0]->notebook_id !== null;

        usort($entries, function (PracticeFeedbackEntry $a, PracticeFeedbackEntry $b) use ($notebook): int {
            if ($notebook) {
                return [$a->position, $a->id] <=> [$b->position, $b->id];
            }

            $aMoment = $a->practiceQuestion?->practiceSession?->created_at?->getTimestamp() ?? 0;
            $bMoment = $b->practiceQuestion?->practiceSession?->created_at?->getTimestamp() ?? 0;

            return [$bMoment, $b->created_at?->getTimestamp() ?? 0, $b->id]
                <=> [$aMoment, $a->created_at?->getTimestamp() ?? 0, $a->id];
        });

        return $entries;
    }

    /**
     * One item by the key the address names, used to open it in its panel.
     * For grammar notes the key is the note's own identity, so two identical
     * notes still open separately; for mistakes it is the one derived from
     * the normalised pair.
     *
     * @return GroupItem|NoteItem|MistakeItem|null
     */
    public function find(FeedbackType $type, string $key): ?array
    {
        return $this->buildForKeys($type, [$key], true)[0] ?? null;
    }

    /**
     * Whether there is anything to gather from at all. A learner with no
     * finished session gets the friendly "start a session" state instead of
     * an empty list that looks broken.
     */
    public function hasFinishedSessions(): bool
    {
        return PracticeSession::query()->study()->exists();
    }

    /**
     * Whether there is anything to gather from at all: a finished session, or
     * a notebook holding at least one row with words in it.
     */
    public function hasSources(): bool
    {
        return $this->hasFinishedSessions()
            || PracticeFeedbackEntry::query()
                ->whereNotNull('notebook_id')
                ->whereRaw("TRIM(content_text) <> ''")
                ->exists();
    }

    /**
     * How many items one collection holds right now, worked out from the
     * same data the list would show: grouped items by their normalised
     * subject, so a word seen many times is counted once; grammar notes one
     * by one, because they are never merged.
     */
    public function count(
        FeedbackType $type,
        ?CollectionFilters $filters = null,
        bool $eligibleOnly = false,
    ): int {
        $filters ??= CollectionFilters::for($type)->scopeOnly();

        if ($type === FeedbackType::GrammarNote) {
            $notes = $this->notesQuery($filters);

            if ($eligibleOnly) {
                $notes->whereIn('e.id', $this->eligibleNoteIds($notes));
            }

            return (int) $notes->count();
        }

        return (int) DB::query()
            ->fromSub($this->groupedQuery($type, $filters, $eligibleOnly), 'g')
            ->count();
    }

    /**
     * How many items of one collection currently ask for the learner's
     * attention, by the same rule the list uses. Grammar notes never do, so
     * their count is always zero.
     */
    public function attentionCount(
        FeedbackType $type,
        ?CollectionFilters $filters = null,
    ): int {
        if ($type === FeedbackType::GrammarNote) {
            return 0;
        }

        // One rule for the list and the count: gather the same groups with the
        // "needs attention" choice on, inside the scope being looked at.
        $filters = ($filters ?? CollectionFilters::for($type))->withOnlyNeedsAttention();

        return (int) DB::query()
            ->fromSub($this->groupedQuery($type, $filters), 'g')
            ->count();
    }

    /**
     * The topics that currently feed one collection, alphabetically, each with
     * how many items of that collection appeared in it. A topic that no item
     * comes from is left out, so the picker never offers a dead end.
     *
     * @return list<array{id: int, name: string, count: int}>
     */
    public function topicOptions(
        FeedbackType $type,
        ?CollectionFilters $filters = null,
        bool $eligibleOnly = false,
    ): array {
        $filters = ($filters ?? CollectionFilters::for($type))->scopeOnly();

        $eligible = $eligibleOnly && $type === FeedbackType::GrammarNote
            ? $this->eligibleNoteIds($this->notesQuery($filters))
            : null;

        // A session row takes its topic from its session; a notebook row from
        // its notebook. The two scopes never share an item, so the choice is
        // never ambiguous.
        $feed = $this->scopedEntries($type)
            ->whereNotNull('e.collection_key')
            ->select('e.id', 'e.collection_key')
            ->selectRaw('COALESCE(s.topic_id, nb.topic_id) as topic_id');

        $this->applyNonBlank($feed, $type);
        $this->applySourceFilter($feed, $filters);

        if ($eligible !== null) {
            $feed->whereIn('e.id', $eligible);
        } elseif ($eligibleOnly) {
            $this->applyEligibleFilter($feed, $type);
        }

        return array_values(DB::query()
            ->fromSub($feed, 'f')
            ->join('topics as t', 't.id', '=', 'f.topic_id')
            ->select([
                't.id',
                't.name',
                DB::raw('COUNT(DISTINCT f.collection_key) as count'),
            ])
            ->groupBy('t.id', 't.name')
            ->orderBy('t.name')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'count' => (int) $row->count,
            ])
            ->all());
    }

    /**
     * The notebooks that feed one collection, alphabetically, each with how
     * many scoped items of that collection it holds. Duplicate rows are one
     * item, the same way the list counts them.
     *
     * @return list<array{id: int, name: string, count: int}>
     */
    public function notebookOptions(FeedbackType $type, bool $eligibleOnly = false): array
    {
        $eligible = $eligibleOnly && $type === FeedbackType::GrammarNote
            ? $this->eligibleNoteIds($this->notesQuery(CollectionFilters::for($type)))
            : null;

        $feed = DB::table('practice_feedback_entries as e')
            ->join('notebooks as nb', 'nb.id', '=', 'e.notebook_id')
            ->where('e.type', $type->value)
            ->whereNotNull('e.collection_key')
            ->select('e.id', 'e.collection_key', 'nb.id as notebook_id', 'nb.name');

        $this->applyNonBlank($feed, $type);

        if ($eligible !== null) {
            $feed->whereIn('e.id', $eligible);
        } elseif ($eligibleOnly) {
            $this->applyEligibleFilter($feed, $type);
        }

        return array_values(DB::query()
            ->fromSub($feed, 'f')
            ->select([
                'f.notebook_id as id',
                'f.name',
                DB::raw('COUNT(DISTINCT f.collection_key) as count'),
            ])
            ->groupBy('f.notebook_id', 'f.name')
            ->orderBy('f.name')
            ->orderBy('f.notebook_id')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'count' => (int) $row->count,
            ])
            ->all());
    }

    /**
     * The count of every collection that has been built, for the tabs.
     *
     * @return array<string, int>
     */
    public function counts(
        ?CollectionFilters $filters = null,
        bool $eligibleOnly = false,
    ): array {
        return [
            CollectionTab::Vocabulary->value => $this->count(FeedbackType::Vocabulary, $filters, $eligibleOnly),
            CollectionTab::Phrases->value => $this->count(FeedbackType::Phrase, $filters, $eligibleOnly),
            CollectionTab::Grammar->value => $this->count(FeedbackType::GrammarNote, $filters, $eligibleOnly),
            CollectionTab::Mistakes->value => $this->count(FeedbackType::Mistake, $filters, $eligibleOnly),
        ];
    }

    /**
     * Every item key the current view, search and filters match, without
     * paging. "Select all" can then add the whole result to a review, not
     * only the items already loaded on screen.
     *
     * @return list<string>
     */
    public function keys(
        FeedbackType $type,
        CollectionFilters $filters,
        bool $eligibleOnly = false,
    ): array {
        if ($type === FeedbackType::GrammarNote) {
            $notes = $this->notesQuery($filters);

            if ($eligibleOnly) {
                $notes->whereIn('e.id', $this->eligibleNoteIds($notes));
            }

            /** @var list<string> $keys */
            $keys = $notes
                ->select('e.id')
                ->get()
                ->map(fn (object $row): string => 'note-'.(int) $row->id)
                ->all();

            return $keys;
        }

        /** @var list<string> $keys */
        $keys = $this->groupedQuery($type, $filters, $eligibleOnly)
            ->select('e.collection_key')
            ->pluck('e.collection_key')
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();

        return $keys;
    }

    /**
     * The rows of one box, newest session first, grouped by their normalised
     * subject. The first row of a group is its newest appearance.
     *
     * @param  Collection<int, PracticeFeedbackEntry>  $entries
     * @param  list<string>  $keys
     * @return array<string, GroupItem>
     */
    private function groupsFromEntries(
        FeedbackType $type,
        Collection $entries,
        array $keys,
        bool $withAllAppearances = false,
    ): array {
        $wanted = array_fill_keys($keys, true);

        /**
         * One group while it is still being built, before "needs attention" is
         * boiled down to the one reason the item shows.
         *
         * @var array<string, array{
         *     key: string,
         *     title: string,
         *     meaning: string,
         *     example: string,
         *     example_html: string,
         *     latest_at: CarbonImmutable,
         *     session_id: int|null,
         *     session_date: CarbonImmutable|null,
         *     topic: string,
         *     question_position: int|null,
         *     source: 'session'|'notebook',
         *     notebook: CollectionNotebook|null,
         *     seen_questions: array<int, true>,
         *     split_broke: bool,
         *     rows: list<GroupRow>,
         *     appearances: list<GroupAppearance>
         * }> $groups
         */
        $groups = [];

        foreach ($entries as $entry) {
            $isNotebook = $entry->notebook_id !== null;
            $question = $entry->practiceQuestion;
            $session = $question?->practiceSession;
            $notebook = $entry->notebook;
            $subject = trim((string) $entry->content_text);
            $key = $isNotebook
                ? (string) $entry->collection_key
                : SearchText::normalize($subject);

            if ($key === '' || ! isset($wanted[$key])) {
                continue;
            }

            $meaning = trim((string) $entry->correction_text);
            $example = trim(EntrySearch::exampleText($entry));
            $exampleHtml = null;
            $moment = $isNotebook
                ? ($entry->created_at !== null
                    ? CarbonImmutable::instance($entry->created_at)
                    : CarbonImmutable::now())
                : ($session->created_at ?? CarbonImmutable::now());

            $groups[$key] ??= [
                'key' => $key,
                'title' => $subject,
                'meaning' => '',
                'example' => '',
                'example_html' => '',
                'latest_at' => $moment,
                'session_id' => null,
                'session_date' => null,
                'topic' => $isNotebook ? (string) $notebook->topic->name : '',
                'question_position' => null,
                'source' => $isNotebook ? 'notebook' : 'session',
                'notebook' => $isNotebook
                    ? [
                        'id' => (int) $notebook->id,
                        'name' => (string) $notebook->name,
                        'topic' => (string) $notebook->topic->name,
                    ]
                    : null,
                'seen_questions' => [],
                'split_broke' => false,
                'rows' => [],
                'appearances' => [],
            ];

            $group = $groups[$key];

            if ($moment->greaterThan($group['latest_at'])) {
                $group['latest_at'] = $moment;
            }

            // A notebook item is not "seen" in a question; its date is when it
            // was added, so it never claims an appearance count.
            if (! $isNotebook) {
                $group['seen_questions'][$question->id] = true;
            }

            // The shared rule says why one row asks for attention; a phrase
            // whose line would not split is the case it names for phrases.
            if (RowAttention::reason($type, $subject, $meaning, $example)
                === RowAttention::MORE_THAN_ONE) {
                $group['split_broke'] = true;
            }

            // Rows arrive in saved order for a notebook and newest first for a
            // session, so the first filling value is the one the item shows.
            if ($group['meaning'] === '' && $meaning !== '') {
                $group['meaning'] = $meaning;
            }

            if ($group['example'] === '' && $example !== '') {
                $group['example'] = $example;
                $group['example_html'] = $exampleHtml = RichText::inlineExampleHtml($entry->example_html);
            }

            if ($isNotebook) {
                if ($withAllAppearances) {
                    $group['rows'][] = [
                        'entry_id' => is_string($entry->client_id) && $entry->client_id !== ''
                            ? $entry->client_id
                            : null,
                        'entry_position' => (int) $entry->position,
                        'meaning' => $meaning,
                        'example' => $example,
                        'example_html' => RichText::inlineExampleHtml($entry->example_html),
                    ];
                }
            } elseif ($withAllAppearances || $group['appearances'] === []) {
                $appearance = [
                    'session_id' => (int) $session->id,
                    'session_date' => $session->created_at,
                    'topic' => (string) $session->topic->name,
                    'question_position' => (int) $question->position,
                    'entry_id' => is_string($entry->client_id) && $entry->client_id !== ''
                        ? $entry->client_id
                        : null,
                    'entry_position' => (int) $entry->getAttribute('entry_position'),
                    'meaning' => $meaning,
                    'example' => $example,
                    'example_html' => $exampleHtml ?? RichText::inlineExampleHtml($entry->example_html),
                ];

                // Entries arrive newest first, so the first appearance is the
                // one the panel links back to.
                if ($group['session_id'] === null) {
                    $group['session_id'] = $appearance['session_id'];
                    $group['session_date'] = $appearance['session_date'];
                    $group['topic'] = $appearance['topic'];
                    $group['question_position'] = $appearance['question_position'];
                }

                $group['appearances'][] = $appearance;
            }

            $groups[$key] = $group;
        }

        $items = [];

        foreach ($groups as $key => $group) {
            $items[$key] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'meaning' => $group['meaning'],
                'example' => $group['example'],
                'example_html' => $group['example_html'],
                'latest_at' => $group['latest_at'],
                'seen_count' => $group['source'] === 'notebook'
                    ? null
                    : count($group['seen_questions']),
                'attention' => $this->groupAttention($type, $group),
                'session_id' => $group['session_id'],
                'session_date' => $group['session_date'],
                'topic' => $group['topic'],
                'question_position' => $group['question_position'],
                'source' => $group['source'],
                'notebook' => $group['notebook'],
                'rows' => $group['rows'],
                'appearances' => $group['appearances'],
            ];
        }

        return $items;
    }

    /**
     * The one "needs attention" reason an item shows, handed over by the
     * shared rule in `RowAttention`:
     *
     * - a phrase is marked only when one of its rows could not be split, so
     *   the whole line sits in the phrase column with nothing else. A phrase
     *   without a meaning is normal and stays calm;
     * - a word without any meaning is marked, and a row that could not be
     *   split says so instead of the plainer "Add the meaning."
     *
     * @param  array{meaning: string, split_broke: bool}  $group
     */
    private function groupAttention(FeedbackType $type, array $group): ?string
    {
        if ($type === FeedbackType::Phrase) {
            return $group['split_broke']
                ? RowAttention::MORE_THAN_ONE
                : null;
        }

        return $group['meaning'] === ''
            ? ($group['split_broke']
                ? RowAttention::MORE_THAN_ONE
                : RowAttention::ADD_MEANING)
            : null;
    }

    /**
     * Every grammar note of the study sessions, as its own item. Two notes
     * with identical text never become one: each keeps the identity the
     * editor gave it.
     *
     * @param  Collection<int, PracticeFeedbackEntry>  $entries
     * @param  list<string>  $keys
     * @return array<string, NoteItem>
     */
    private function notesFromEntries(Collection $entries, array $keys): array
    {
        $wanted = array_fill_keys($keys, true);
        $items = [];

        foreach ($entries as $entry) {
            $isNotebook = $entry->notebook_id !== null;
            $question = $entry->practiceQuestion;
            $session = $question?->practiceSession;
            $notebook = $entry->notebook;
            $key = $this->noteKey($entry);

            if (! isset($wanted[$key])) {
                continue;
            }

            $items[$key] = [
                'key' => $key,
                'text' => (string) $entry->content_text,
                'preview' => NotePreview::of((string) $entry->content_text),
                'html' => RichText::clean((string) $entry->content_html),
                'latest_at' => $isNotebook
                    ? ($entry->created_at !== null
                        ? CarbonImmutable::instance($entry->created_at)
                        : CarbonImmutable::now())
                    : ($session->created_at ?? CarbonImmutable::now()),
                'session_id' => $isNotebook ? null : (int) $session->id,
                'session_date' => $isNotebook ? null : $session->created_at,
                'topic' => $isNotebook
                    ? (string) $notebook->topic->name
                    : (string) $session->topic->name,
                'question_position' => $isNotebook ? null : (int) $question->position,
                'entry_id' => is_string($entry->client_id) && $entry->client_id !== ''
                    ? $entry->client_id
                    : null,
                'entry_position' => $isNotebook
                    ? (int) $entry->position
                    : (int) $entry->getAttribute('entry_position'),
                'source' => $isNotebook ? 'notebook' : 'session',
                'notebook' => $isNotebook
                    ? [
                        'id' => (int) $notebook->id,
                        'name' => (string) $notebook->name,
                        'topic' => (string) $notebook->topic->name,
                    ]
                    : null,
            ];
        }

        return $items;
    }

    /**
     * The note's own stable identity: the saved row it is. The row keeps its
     * id when the note is edited, so a link to it keeps working, and two
     * identical notes always have two different identities.
     */
    private function noteKey(PracticeFeedbackEntry $entry): string
    {
        return 'note-'.$entry->id;
    }

    /**
     * The mistake rows of the study sessions, grouped by the pair of what was
     * said and the correction. The same mistake with a different reason stays
     * one group, and the same correction on two different mistakes stays two,
     * because the pair is what identifies the mistake.
     *
     * Rows arrive newest first, so the first row of a group carries the
     * spelling of its most recent appearance. A row without a correction is
     * the pair of what was said and an empty column: its own group, marked
     * "needs attention" by the shared rule, never hidden.
     *
     * @param  Collection<int, PracticeFeedbackEntry>  $entries
     * @param  list<string>  $keys
     * @return array<string, MistakeItem>
     */
    private function mistakesFromEntries(
        Collection $entries,
        array $keys,
        bool $withAllAppearances = false,
    ): array {
        $wanted = array_fill_keys($keys, true);

        /**
         * One mistake while it is still being built, before it is boiled down
         * to the shape the list and panel show.
         *
         * @var array<string, array{
         *     key: string,
         *     title: string,
         *     correction: string,
         *     correction_html: string,
         *     reason: string,
         *     latest_at: CarbonImmutable,
         *     seen_questions: array<int, true>,
         *     sighting_reasons: array<string, true>,
         *     attention: string|null,
         *     appearances: list<MistakeAppearance>
         * }> $groups
         */
        $groups = [];

        foreach ($entries as $entry) {
            $question = $entry->practiceQuestion;
            $session = $question->practiceSession;

            $said = trim((string) $entry->content_text);
            $correction = trim((string) $entry->correction_text);

            if (MistakeIdentity::isBlank($said, $correction)) {
                continue;
            }

            $key = MistakeIdentity::pairKey($said, $correction);

            if (! isset($wanted[$key])) {
                continue;
            }

            $correctionHtml = null;

            $groups[$key] ??= [
                'key' => $key,
                // "Most recent" is the appearance in the latest session, so
                // the first row of the group carries its words as written.
                'title' => $said,
                'correction' => '',
                'correction_html' => '',
                'reason' => '',
                'latest_at' => $session->created_at ?? CarbonImmutable::now(),
                'seen_questions' => [],
                'sighting_reasons' => [],
                'attention' => RowAttention::reason(
                    FeedbackType::Mistake,
                    $said,
                    $correction,
                ),
                'appearances' => [],
            ];

            $group = $groups[$key];

            // Every question the mistake was saved under is one sighting: a
            // repeated row in the same box counts once, another question or
            // another session counts again.
            $group['seen_questions'][$question->id] = true;

            // The panel keeps one appearance per question and reason. A row
            // that repeats a reason already kept for the same box adds nothing
            // to the places seen or the reasons shown, so it is dropped.
            $reason = trim((string) $entry->reason);
            $sightingKey = $question->id.':'.Str::lower($reason);
            $isNewReasonInQuestion = ! isset($group['sighting_reasons'][$sightingKey]);
            $group['sighting_reasons'][$sightingKey] = true;

            if ($group['correction'] === '' && $correction !== '') {
                $group['correction'] = $correction;
            }

            if ($group['correction_html'] === '' && $correction !== '') {
                $correctionHtml = RichText::cleanInlineWithText(
                    (string) $entry->correction_html,
                )['html'];

                if ($correctionHtml !== '') {
                    $group['correction_html'] = $correctionHtml;
                }
            }

            // The reason comes from the most recent appearance that has one;
            // every distinct reason all the same stays with its appearance.
            if ($group['reason'] === '') {
                $group['reason'] = $reason;
            }

            // A list keeps the newest appearance; the panel keeps each new
            // reason of a question, so every place and every reason stays.
            if ($withAllAppearances ? $isNewReasonInQuestion : $group['appearances'] === []) {
                $correctionHtml ??= RichText::cleanInlineWithText(
                    (string) $entry->correction_html,
                )['html'];

                $group['appearances'][] = [
                    'session_id' => (int) $session->id,
                    'session_date' => $session->created_at,
                    'topic' => (string) $session->topic->name,
                    'question_position' => (int) $question->position,
                    'entry_id' => is_string($entry->client_id) && $entry->client_id !== ''
                        ? $entry->client_id
                        : null,
                    'entry_position' => (int) $entry->getAttribute('entry_position'),
                    'said' => $said,
                    'correction' => $correction,
                    'correction_html' => $correctionHtml,
                    'reason' => $reason,
                ];
            }

            $groups[$key] = $group;
        }

        $count = self::WORTH_PRACTICING_SEEN_COUNT;
        $items = [];

        foreach ($groups as $key => $group) {
            $seen = count($group['seen_questions']);

            $items[$key] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'correction' => $group['correction'],
                'correction_html' => $group['correction_html'],
                'reason' => $group['reason'],
                'latest_at' => $group['latest_at'],
                'seen_count' => $seen,
                'worth_practicing' => $seen >= $count,
                'attention' => $group['attention'],
                'source' => 'session',
                'notebook' => null,
                'rows' => [],
                'appearances' => $group['appearances'],
            ];
        }

        return $items;
    }

    /**
     * The entries one collection gathers, before they are read: only rows of
     * its box, inside finished sessions that are not archived, that hold some
     * words. A mistake row can be a pair whose what-I-said column alone is
     * empty as long as a correction is saved, so only rows where both columns
     * are empty are left out.
     *
     * @return Builder<PracticeFeedbackEntry>
     */
    private function entryQuery(FeedbackType $type): Builder
    {
        return PracticeFeedbackEntry::query()
            ->where('type', $type->value)
            ->where(fn (Builder $scope): Builder => $scope
                ->whereNotNull('notebook_id')
                ->orWhereHas(
                    'practiceQuestion.practiceSession',
                    fn (Builder $query): Builder => $query->study(),
                ))
            ->when(
                $type === FeedbackType::Mistake,
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $instead): Builder => $instead
                        ->whereRaw("TRIM(content_text) <> ''")
                        ->orWhereRaw("TRIM(correction_text) <> ''"),
                ),
                fn (Builder $query): Builder => $query->whereRaw("TRIM(content_text) <> ''"),
            );
    }

    /**
     * The place of a row inside its own box, zero-based, matching the order
     * the reading page shows. A target can then name a row by its id or, when
     * an older row has no id, by this place.
     *
     * The place is counted in the database so the collection does not have to
     * read every other row of the same question just to number this one.
     */
    private function entryPositionQuery(FeedbackType $type): QueryBuilder
    {
        $query = DB::table('practice_feedback_entries as earlier')
            ->selectRaw('COUNT(*)')
            ->whereColumn('earlier.practice_question_id', 'practice_feedback_entries.practice_question_id')
            ->whereColumn('earlier.type', 'practice_feedback_entries.type')
            ->where(function (QueryBuilder $before): void {
                $before
                    ->whereColumn('earlier.position', '<', 'practice_feedback_entries.position')
                    ->orWhere(function (QueryBuilder $same): void {
                        $same
                            ->whereColumn('earlier.position', '=', 'practice_feedback_entries.position')
                            ->whereColumn('earlier.id', '<', 'practice_feedback_entries.id');
                    });
            });

        // Count the same rows the collection gathered: a blank row is not a
        // sighting and never took a place in the list.
        if ($type === FeedbackType::Mistake) {
            $query->where(function (QueryBuilder $instead): void {
                $instead
                    ->whereRaw("TRIM(earlier.content_text) <> ''")
                    ->orWhereRaw("TRIM(earlier.correction_text) <> ''");
            });
        } else {
            $query->whereRaw("TRIM(earlier.content_text) <> ''");
        }

        return $query;
    }
}
