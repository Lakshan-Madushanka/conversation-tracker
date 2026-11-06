<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CollectionSort;
use App\Enums\CollectionStatusFilter;
use App\Enums\CollectionTab;
use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeSession;
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
 *     seen_count: int,
 *     attention: string|null,
 *     appearances: list<GroupAppearance>
 * }
 * @phpstan-type NoteItem array{
 *     key: string,
 *     text: string,
 *     preview: string,
 *     html: string,
 *     latest_at: CarbonImmutable,
 *     session_id: int,
 *     session_date: CarbonImmutable|null,
 *     topic: string,
 *     question_position: int,
 *     entry_id: string|null,
 *     entry_position: int
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
    ): array {
        $filters ??= CollectionFilters::for($type);
        $perPage = max(1, $perPage);

        return $type === FeedbackType::GrammarNote
            ? $this->notesPage($type, $filters, $cursor, $perPage)
            : $this->groupedPage($type, $filters, $cursor, $perPage);
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
    ): array {
        $grouped = $this->groupedQuery($type, $filters);
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
    ): array {
        $base = $this->notesQuery($filters);
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
    private function groupedQuery(FeedbackType $type, CollectionFilters $filters): QueryBuilder
    {
        $query = DB::table('practice_feedback_entries as e')
            ->join('practice_questions as q', 'q.id', '=', 'e.practice_question_id')
            ->join('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->where('e.type', $type->value)
            ->where('s.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('s.archived_at');

        $this->applyNonBlank($query, $type);
        $this->applyMarkJoin($query);
        $this->applyMarkFilters($query, $filters);
        $this->applyTopicFilter($query, $type, $filters);

        if ($filters->onlyNeedsAttention()) {
            $query->whereRaw($this->attentionSql($type));
        }

        $this->applyGroupSearch($query, $type, $filters);

        $seconds = $this->secondsExpression('s.created_at');

        return $query
            ->groupBy('e.collection_key')
            ->select([
                'e.collection_key as group_key',
                DB::raw('MAX(s.created_at) as latest_at'),
                DB::raw($seconds.' as latest_seconds'),
                DB::raw('COUNT(DISTINCT e.practice_question_id) as seen_count'),
                DB::raw('MIN(e.sort_text) as sort_text'),
            ]);
    }

    /**
     * The grammar notes, narrowed by every filter and search term.
     */
    private function notesQuery(CollectionFilters $filters): QueryBuilder
    {
        $query = DB::table('practice_feedback_entries as e')
            ->join('practice_questions as q', 'q.id', '=', 'e.practice_question_id')
            ->join('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->where('e.type', FeedbackType::GrammarNote->value)
            ->where('s.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('s.archived_at')
            ->whereRaw("TRIM(e.content_text) <> ''");

        $this->applyMarkJoin($query);
        $this->applyMarkFilters($query, $filters);

        $topics = $filters->topics();

        if ($topics !== []) {
            $query->whereIn('s.topic_id', $topics);
        }

        $this->applyNoteSearch($query, $filters);

        $seconds = $this->secondsExpression('s.created_at');

        return $query->select([
            'e.id',
            'e.collection_key as group_key',
            'e.sort_text',
            's.created_at as latest_at',
            DB::raw($seconds.' as latest_seconds'),
            DB::raw('(q.position * '.self::NOTE_ORDER_STEP.' + e.position) as note_order'),
        ]);
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
                ->join('practice_questions as tq', 'tq.id', '=', 'te.practice_question_id')
                ->join('practice_sessions as ts', 'ts.id', '=', 'tq.practice_session_id')
                ->whereColumn('te.collection_key', 'e.collection_key')
                ->where('te.type', $type->value)
                ->where('ts.status', PracticeSession::STATUS_FINISHED)
                ->whereNull('ts.archived_at')
                ->whereIn('ts.topic_id', $topics);
        });
    }

    /**
     * Why one grouped item asks for attention, by the same rules the item
     * shape uses: a word with no meaning anywhere, a phrase whose line could
     * not be split, or a mistake whose newest row still needs a correction.
     *
     * @return literal-string
     */
    private function attentionSql(FeedbackType $type): string
    {
        return match ($type) {
            FeedbackType::Vocabulary => 'NOT EXISTS (
                SELECT 1 FROM practice_feedback_entries a
                INNER JOIN practice_questions as aq ON aq.id = a.practice_question_id
                INNER JOIN practice_sessions as ss ON ss.id = aq.practice_session_id
                WHERE a.collection_key = e.collection_key
                    AND a.type = \'vocabulary\'
                    AND ss.status = \'finished\'
                    AND ss.archived_at IS NULL
                    AND TRIM(a.correction_text) <> \'\'
            )',
            FeedbackType::Phrase => 'EXISTS (
                SELECT 1 FROM practice_feedback_entries a
                INNER JOIN practice_questions as aq ON aq.id = a.practice_question_id
                INNER JOIN practice_sessions as ss ON ss.id = aq.practice_session_id
                WHERE a.collection_key = e.collection_key
                    AND a.type = \'phrase\'
                    AND ss.status = \'finished\'
                    AND ss.archived_at IS NULL
                    AND a.split_broke = 1
            )',
            FeedbackType::Mistake => '(SELECT a.attention_reason
                FROM practice_feedback_entries a
                INNER JOIN practice_questions as aq ON aq.id = a.practice_question_id
                INNER JOIN practice_sessions as ss ON ss.id = aq.practice_session_id
                WHERE a.collection_key = e.collection_key
                    AND a.type = \'mistake\'
                    AND ss.status = \'finished\'
                    AND ss.archived_at IS NULL
                ORDER BY ss.created_at DESC, a.created_at DESC, a.id DESC
                LIMIT 1) IS NOT NULL',
            default => '1 = 1',
        };
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
                        ->join('practice_questions as sq', 'sq.id', '=', 'se.practice_question_id')
                        ->join('practice_sessions as ss', 'ss.id', '=', 'sq.practice_session_id')
                        ->whereColumn('se.collection_key', 'e.collection_key')
                        ->where('se.type', $type->value)
                        ->where('ss.status', PracticeSession::STATUS_FINISHED)
                        ->whereNull('ss.archived_at')
                        ->whereRaw("se.search_text LIKE ? ESCAPE '!'", [$pattern]);
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
     * @param  literal-string  $column
     * @return literal-string
     */
    private function secondsExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%s', {$column}) AS INTEGER)"
            : "CAST(UNIX_TIMESTAMP({$column}) AS SIGNED)";
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

        $entries = $matching
            ->join('practice_questions as sort_question', 'sort_question.id', '=', 'practice_feedback_entries.practice_question_id')
            ->join('practice_sessions as sort_session', 'sort_session.id', '=', 'sort_question.practice_session_id')
            ->select('practice_feedback_entries.*')
            ->selectSub($this->entryPositionQuery($type), 'entry_position')
            ->with(['practiceQuestion.practiceSession.topic'])
            ->orderByDesc('sort_session.created_at')
            ->orderByDesc('practice_feedback_entries.created_at')
            ->orderByDesc('practice_feedback_entries.id')
            ->get();

        $items = match ($type) {
            FeedbackType::GrammarNote => $this->notesFromEntries($entries, $keys),
            FeedbackType::Mistake => $this->mistakesFromEntries($entries, $keys, $withAllAppearances),
            default => $this->groupsFromEntries($type, $entries, $keys, $withAllAppearances),
        };

        $ordered = [];

        foreach ($keys as $key) {
            if (isset($items[$key])) {
                $ordered[] = $items[$key];
            }
        }

        return $ordered;
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
     * How many items one collection holds right now, worked out from the
     * same data the list would show: grouped items by their normalised
     * subject, so a word seen many times is counted once; grammar notes one
     * by one, because they are never merged.
     */
    public function count(FeedbackType $type): int
    {
        $query = DB::table('practice_feedback_entries as e')
            ->join('practice_questions as q', 'q.id', '=', 'e.practice_question_id')
            ->join('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->where('e.type', $type->value)
            ->where('s.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('s.archived_at');

        $this->applyNonBlank($query, $type);

        if ($type === FeedbackType::GrammarNote) {
            return (int) $query->count();
        }

        return (int) $query
            ->whereNotNull('e.collection_key')
            ->distinct()
            ->count('e.collection_key');
    }

    /**
     * How many items of one collection currently ask for the learner's
     * attention, by the same rule the list uses. Grammar notes never do, so
     * their count is always zero.
     */
    public function attentionCount(FeedbackType $type): int
    {
        if ($type === FeedbackType::GrammarNote) {
            return 0;
        }

        return match ($type) {
            FeedbackType::Vocabulary => $this->countGroupsNeedingAttention(
                $type,
                "MAX(CASE WHEN TRIM(e.correction_text) <> '' THEN 1 ELSE 0 END) = 0",
            ),
            FeedbackType::Phrase => $this->countGroupsNeedingAttention(
                $type,
                'MAX(CASE WHEN e.split_broke = 1 THEN 1 ELSE 0 END) = 1',
            ),
            FeedbackType::Mistake => $this->countMistakesNeedingAttention(),
            default => 0,
        };
    }

    /**
     * Count the words or phrases that need attention, working out the same
     * group-level rule the list uses but in one pass: a word with no meaning
     * anywhere, or a phrase with a line that could not be split. Doing this
     * after grouping keeps the count off the rows themselves.
     *
     * @param  literal-string  $having
     */
    private function countGroupsNeedingAttention(FeedbackType $type, string $having): int
    {
        $query = DB::table('practice_feedback_entries as e')
            ->join('practice_questions as q', 'q.id', '=', 'e.practice_question_id')
            ->join('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->where('e.type', $type->value)
            ->where('s.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('s.archived_at')
            ->groupBy('e.collection_key')
            ->havingRaw($having);

        $this->applyNonBlank($query, $type);

        return (int) DB::query()->fromSub($query, 'g')->count();
    }

    /**
     * Count the mistakes whose newest appearance still needs attention. Each
     * group is asked once, rather than every row asking about its whole
     * history.
     */
    private function countMistakesNeedingAttention(): int
    {
        $query = DB::table('practice_feedback_entries as e')
            ->join('practice_questions as q', 'q.id', '=', 'e.practice_question_id')
            ->join('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->where('e.type', FeedbackType::Mistake->value)
            ->where('s.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('s.archived_at')
            ->groupBy('e.collection_key')
            ->select('e.collection_key')
            ->selectSub($this->newestMistakeAttentionQuery(), 'newest_attention');

        $this->applyNonBlank($query, FeedbackType::Mistake);

        return (int) DB::query()
            ->fromSub($query, 'g')
            ->whereNotNull('newest_attention')
            ->count();
    }

    /**
     * The attention reason of a mistake's newest appearance, used by the
     * attention count. The list and the item shape still read the same rows.
     */
    private function newestMistakeAttentionQuery(): QueryBuilder
    {
        return DB::table('practice_feedback_entries as attention_entry')
            ->select('attention_entry.attention_reason')
            ->join('practice_questions as attention_question', 'attention_question.id', '=', 'attention_entry.practice_question_id')
            ->join('practice_sessions as attention_session', 'attention_session.id', '=', 'attention_question.practice_session_id')
            ->whereColumn('attention_entry.collection_key', 'e.collection_key')
            ->where('attention_entry.type', FeedbackType::Mistake->value)
            ->where('attention_session.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('attention_session.archived_at')
            ->orderByDesc('attention_session.created_at')
            ->orderByDesc('attention_entry.created_at')
            ->orderByDesc('attention_entry.id')
            ->limit(1);
    }

    /**
     * The topics that currently feed one collection, alphabetically, each with
     * how many items of that collection appeared in it. A topic that no item
     * comes from is left out, so the picker never offers a dead end.
     *
     * @return list<array{id: int, name: string, count: int}>
     */
    public function topicOptions(FeedbackType $type): array
    {
        $query = DB::table('practice_feedback_entries as e')
            ->join('practice_questions as q', 'q.id', '=', 'e.practice_question_id')
            ->join('practice_sessions as s', 's.id', '=', 'q.practice_session_id')
            ->join('topics as t', 't.id', '=', 's.topic_id')
            ->where('e.type', $type->value)
            ->where('s.status', PracticeSession::STATUS_FINISHED)
            ->whereNull('s.archived_at')
            ->whereNotNull('e.collection_key');

        $this->applyNonBlank($query, $type);

        return array_values($query
            ->select([
                't.id',
                't.name',
                DB::raw('COUNT(DISTINCT e.collection_key) as count'),
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
     * The count of every collection that has been built, for the tabs.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            CollectionTab::Vocabulary->value => $this->count(FeedbackType::Vocabulary),
            CollectionTab::Phrases->value => $this->count(FeedbackType::Phrase),
            CollectionTab::Grammar->value => $this->count(FeedbackType::GrammarNote),
            CollectionTab::Mistakes->value => $this->count(FeedbackType::Mistake),
        ];
    }

    /**
     * Every item key the current view, search and filters match, without
     * paging. "Select all" can then add the whole result to the basket, not
     * only the items already loaded on screen.
     *
     * @return list<string>
     */
    public function keys(FeedbackType $type, CollectionFilters $filters): array
    {
        if ($type === FeedbackType::GrammarNote) {
            /** @var list<string> $keys */
            $keys = $this->notesQuery($filters)
                ->select('e.id')
                ->get()
                ->map(fn (object $row): string => 'note-'.(int) $row->id)
                ->all();

            return $keys;
        }

        /** @var list<string> $keys */
        $keys = $this->groupedQuery($type, $filters)
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
         *     seen_questions: array<int, true>,
         *     split_broke: bool,
         *     appearances: list<GroupAppearance>
         * }> $groups
         */
        $groups = [];

        foreach ($entries as $entry) {
            $question = $entry->practiceQuestion;
            $session = $question->practiceSession;
            $subject = trim((string) $entry->content_text);
            $key = SearchText::normalize($subject);

            if ($key === '' || ! isset($wanted[$key])) {
                continue;
            }

            $meaning = trim((string) $entry->correction_text);
            $example = trim(EntrySearch::exampleText($entry));
            $exampleHtml = null;

            $groups[$key] ??= [
                'key' => $key,
                'title' => $subject,
                'meaning' => '',
                'example' => '',
                'example_html' => '',
                'latest_at' => $session->created_at ?? CarbonImmutable::now(),
                'seen_questions' => [],
                'split_broke' => false,
                'appearances' => [],
            ];

            $group = $groups[$key];

            $group['seen_questions'][$question->id] = true;

            // The shared rule says why one row asks for attention; a phrase
            // whose line would not split is the case it names for phrases.
            if (RowAttention::reason($type, $subject, $meaning, $example)
                === RowAttention::MORE_THAN_ONE) {
                $group['split_broke'] = true;
            }

            // Rows arrive newest first, so the first meaning and the first
            // example are the most recent ones that hold anything.
            if ($group['meaning'] === '' && $meaning !== '') {
                $group['meaning'] = $meaning;
            }

            if ($group['example'] === '' && $example !== '') {
                $group['example'] = $example;
                $group['example_html'] = $exampleHtml = RichText::inlineExampleHtml($entry->example_html);
            }

            if ($withAllAppearances || $group['appearances'] === []) {
                $group['appearances'][] = [
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
                'seen_count' => count($group['seen_questions']),
                'attention' => $this->groupAttention($type, $group),
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
            $question = $entry->practiceQuestion;
            $session = $question->practiceSession;
            $key = $this->noteKey($entry);

            if (! isset($wanted[$key])) {
                continue;
            }

            $items[$key] = [
                'key' => $key,
                'text' => (string) $entry->content_text,
                'preview' => NotePreview::of((string) $entry->content_text),
                'html' => RichText::clean((string) $entry->content_html),
                'latest_at' => $session->created_at ?? CarbonImmutable::now(),
                'session_id' => (int) $session->id,
                'session_date' => $session->created_at,
                'topic' => (string) $session->topic->name,
                'question_position' => (int) $question->position,
                'entry_id' => is_string($entry->client_id) && $entry->client_id !== ''
                    ? $entry->client_id
                    : null,
                'entry_position' => (int) $entry->getAttribute('entry_position'),
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
            ->whereHas(
                'practiceQuestion.practiceSession',
                fn (Builder $query): Builder => $query->study(),
            )
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
