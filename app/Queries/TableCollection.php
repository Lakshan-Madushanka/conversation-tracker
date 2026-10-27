<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeSession;
use App\Support\CollectionCursor;
use App\Support\EntrySearch;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One collection, gathered live from the study sessions: vocabulary (and,
 * later, phrases and mistakes) grouped by what the word actually is.
 *
 * A word is the same word when it differs only in capitals, accents, extra
 * spaces or formatting. Every row it was saved in is kept as an appearance,
 * but two copies in the same box of the same question count once, because
 * the learner wrote the word once and the editor repeated the row.
 *
 * Nothing is stored or cached: a finished session, an edit, an archive, a
 * restore or a delete changes the next read straight away.
 */
final readonly class TableCollection
{
    /** How many items one page of a collection holds. */
    public const int PAGE_SIZE = 30;

    /**
     * One page, newest session first and then alphabetically. The cursor names
     * the last item shown, so a word saved while the list is open can never
     * make a later page repeat or skip one.
     *
     * @return array{
     *     items: list<array{
     *         key: string,
     *         word: string,
     *         meaning: string,
     *         example: string,
     *         latest_at: CarbonImmutable,
     *         seen_count: int,
     *         appearances: list<array{
     *             session_id: int,
     *             session_date: CarbonImmutable|null,
     *             topic: string,
     *             question_position: int,
     *             entry_id: string|null,
     *             entry_position: int,
     *             meaning: string,
     *             example: string
     *         }>
     *     }>,
     *     next_cursor: string|null,
     *     has_more: bool,
     *     total: int
     * }
     */
    public function page(
        FeedbackType $type,
        ?string $cursor = null,
        int $perPage = self::PAGE_SIZE,
    ): array {
        $items = array_values($this->groups($type));

        usort($items, $this->compareItems(...));

        $total = count($items);
        $from = CollectionCursor::decode($cursor);

        if ($from instanceof CollectionCursor) {
            $items = array_values(array_filter(
                $items,
                fn (array $item): bool => $this->isAfter($item, $from),
            ));
        }

        $hasMore = count($items) > $perPage;
        $page = array_slice($items, 0, $perPage);
        $nextCursor = null;

        if ($hasMore) {
            $last = $page[count($page) - 1];
            $nextCursor = (new CollectionCursor($last['key'], $last['latest_at']))->encode();
        }

        return [
            'items' => $page,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'total' => $total,
        ];
    }

    /**
     * One item by its normalised key, used to open a word named in the address.
     *
     * @return array{
     *     key: string,
     *     word: string,
     *     meaning: string,
     *     example: string,
     *     latest_at: CarbonImmutable,
     *     seen_count: int,
     *     appearances: list<array{
     *         session_id: int,
     *         session_date: CarbonImmutable|null,
     *         topic: string,
     *         question_position: int,
     *         entry_id: string|null,
     *         entry_position: int,
     *         meaning: string,
     *         example: string
     *     }>
     * }|null
     */
    public function find(FeedbackType $type, string $key): ?array
    {
        return $this->groups($type)[$key] ?? null;
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
     * Every item of one box, keyed by its normalised word. Rows are read in the
     * order they should be shown, so the first row of a group is the newest.
     *
     * @return array<string, array{
     *     key: string,
     *     word: string,
     *     meaning: string,
     *     example: string,
     *     latest_at: CarbonImmutable,
     *     seen_count: int,
     *     appearances: list<array{
     *         session_id: int,
     *         session_date: CarbonImmutable|null,
     *         topic: string,
     *         question_position: int,
     *         entry_id: string|null,
     *         entry_position: int,
     *         meaning: string,
     *         example: string
     *     }>
     * }>
     */
    private function groups(FeedbackType $type): array
    {
        /** @var Collection<int, PracticeFeedbackEntry> $entries */
        $entries = PracticeFeedbackEntry::query()
            ->with(['practiceQuestion.practiceSession.topic'])
            ->where('type', $type->value)
            ->whereHas(
                'practiceQuestion.practiceSession',
                fn (Builder $query): Builder => $query->study(),
            )
            ->whereRaw("TRIM(content_text) <> ''")
            ->get()
            ->sort($this->compareEntries(...))
            ->values();

        $entryPositions = $this->entryPositions($entries);

        /** @var array<string, array{
         *     key: string,
         *     word: string,
         *     meaning: string,
         *     example: string,
         *     latest_at: CarbonImmutable,
         *     seen_questions: array<int, true>,
         *     appearances: list<array{
         *         session_id: int,
         *         session_date: CarbonImmutable|null,
         *         topic: string,
         *         question_position: int,
         *         entry_id: string|null,
         *         entry_position: int,
         *         meaning: string,
         *         example: string
         *     }>
         * }> $groups
         */
        $groups = [];

        foreach ($entries as $entry) {
            $question = $entry->practiceQuestion;
            $session = $question->practiceSession;
            $word = trim($entry->content_text);
            $key = SearchText::normalize($word);

            if ($key === '') {
                continue;
            }

            $meaning = trim($entry->correction_text);
            $example = trim(EntrySearch::exampleText($entry));

            $groups[$key] ??= [
                'key' => $key,
                'word' => $word,
                'meaning' => '',
                'example' => '',
                'latest_at' => $session->created_at ?? CarbonImmutable::now(),
                'seen_questions' => [],
                'appearances' => [],
            ];

            $groups[$key]['seen_questions'][$question->id] = true;

            // Rows arrive newest first, so the first meaning and the first
            // example are the most recent ones that hold anything.
            if ($groups[$key]['meaning'] === '' && $meaning !== '') {
                $groups[$key]['meaning'] = $meaning;
            }

            if ($groups[$key]['example'] === '' && $example !== '') {
                $groups[$key]['example'] = $example;
            }

            $groups[$key]['appearances'][] = [
                'session_id' => (int) $session->id,
                'session_date' => $session->created_at,
                'topic' => (string) $session->topic->name,
                'question_position' => (int) $question->position,
                'entry_id' => is_string($entry->client_id) && $entry->client_id !== ''
                    ? $entry->client_id
                    : null,
                'entry_position' => $entryPositions[$entry->id] ?? 0,
                'meaning' => $meaning,
                'example' => $example,
            ];
        }

        $items = [];

        foreach ($groups as $key => $group) {
            unset($group['seen_questions']);

            $items[$key] = [
                ...$group,
                'seen_count' => count($groups[$key]['seen_questions']),
            ];
        }

        return $items;
    }

    /**
     * The place of every row inside its own box, zero-based, matching the
     * order the reading page shows. A target can then name a row by its id or,
     * when an older row has no id, by this place.
     *
     * @param  Collection<int, PracticeFeedbackEntry>  $entries
     * @return array<int, int>
     */
    private function entryPositions(Collection $entries): array
    {
        /** @var array<int, int> $positions */
        $positions = [];

        $byQuestion = $entries->groupBy(
            fn (PracticeFeedbackEntry $entry): int => $entry->practice_question_id,
        );

        foreach ($byQuestion as $rows) {
            $ordered = $rows
                ->sortBy([
                    ['position', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();

            foreach ($ordered as $index => $row) {
                $positions[$row->id] = $index;
            }
        }

        return $positions;
    }

    /**
     * Newest session first. Two items whose latest session shares a moment fall
     * back to their normalised key, so the order is stable across pages.
     *
     * @param  array{key: string, latest_at: CarbonImmutable}  $left
     * @param  array{key: string, latest_at: CarbonImmutable}  $right
     */
    private function compareItems(array $left, array $right): int
    {
        $byTime = $right['latest_at']->getTimestamp() <=> $left['latest_at']->getTimestamp();

        return $byTime !== 0 ? $byTime : $left['key'] <=> $right['key'];
    }

    /**
     * Whether an item comes after the cursor's item in the same order.
     *
     * @param  array{key: string, latest_at: CarbonImmutable}  $item
     */
    private function isAfter(array $item, CollectionCursor $cursor): bool
    {
        $itemTime = $item['latest_at']->getTimestamp();
        $cursorTime = $cursor->latestAt->getTimestamp();

        if ($itemTime !== $cursorTime) {
            return $itemTime < $cursorTime;
        }

        return $item['key'] > $cursor->key;
    }

    /** Rows newest first, so the first row of a group is its latest appearance. */
    private function compareEntries(
        PracticeFeedbackEntry $left,
        PracticeFeedbackEntry $right,
    ): int {
        $bySession = $this->sessionTimestamp($right) <=> $this->sessionTimestamp($left);

        if ($bySession !== 0) {
            return $bySession;
        }

        $byCreated = ($right->created_at?->getTimestamp() ?? 0)
            <=> ($left->created_at?->getTimestamp() ?? 0);

        return $byCreated !== 0 ? $byCreated : $right->id <=> $left->id;
    }

    private function sessionTimestamp(PracticeFeedbackEntry $entry): int
    {
        return $entry->practiceQuestion->practiceSession->created_at?->getTimestamp() ?? 0;
    }
}
