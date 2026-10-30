<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CollectionTab;
use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeSession;
use App\Support\CollectionCursor;
use App\Support\EntrySearch;
use App\Support\MistakeIdentity;
use App\Support\NotePreview;
use App\Support\RichText;
use App\Support\RowAttention;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
    ): array {
        $items = array_values($this->items($type));

        usort($items, $this->comparatorFor($type));

        $total = count($items);
        $from = CollectionCursor::decode($cursor);

        if ($from instanceof CollectionCursor) {
            $items = array_values(array_filter(
                $items,
                fn (array $item): bool => $this->isAfter($item, $from, $type),
            ));
        }

        $hasMore = count($items) > $perPage;
        $page = array_slice($items, 0, $perPage);
        $nextCursor = null;

        if ($hasMore) {
            $last = $page[count($page) - 1];

            $nextCursor = $this->cursorFor($last, $type)->encode();
        }

        return [
            'items' => $page,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'total' => $total,
        ];
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
        return $this->items($type)[$key] ?? null;
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
        if ($type === FeedbackType::GrammarNote) {
            return $this->entryQuery($type)->count();
        }

        if ($type === FeedbackType::Mistake) {
            return $this->mistakeCount();
        }

        /** @var array<string, true> $keys */
        $keys = [];

        foreach ($this->entryQuery($type)->pluck('content_text') as $subject) {
            $key = SearchText::normalize((string) $subject);

            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return count($keys);
    }

    /**
     * How many mistakes the tab holds right now: one group per normalised
     * pair, so a mistake seen in many sessions counts once, and the same
     * correction for two different mistakes counts twice.
     */
    private function mistakeCount(): int
    {
        /** @var array<string, true> $keys */
        $keys = [];

        foreach ($this->entryQuery(FeedbackType::Mistake)
            ->get(['content_text', 'correction_text']) as $row) {
            $key = MistakeIdentity::pairKey(
                (string) $row->content_text,
                (string) $row->correction_text,
            );

            if (! MistakeIdentity::isBlank((string) $row->content_text, (string) $row->correction_text)) {
                $keys[$key] = true;
            }
        }

        return count($keys);
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
     * The items of one collection, keyed by the key the address uses.
     *
     * @return array<string, GroupItem|NoteItem|MistakeItem>
     */
    private function items(FeedbackType $type): array
    {
        return $type === FeedbackType::GrammarNote
            ? $this->notes()
            : ($type === FeedbackType::Mistake
                ? $this->mistakes()
                : $this->groups($type));
    }

    /**
     * The rows of one box, newest session first, grouped by their normalised
     * subject. The first row of a group is its newest appearance.
     *
     * @return array<string, GroupItem>
     */
    private function groups(FeedbackType $type): array
    {
        $entries = $this->entries($type);
        $entryPositions = $this->entryPositions($entries);

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

            if ($key === '') {
                continue;
            }

            $meaning = trim((string) $entry->correction_text);
            $example = trim(EntrySearch::exampleText($entry));
            $exampleHtml = RichText::inlineExampleHtml($entry->example_html);

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
                $group['example_html'] = $exampleHtml;
            }

            $group['appearances'][] = [
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
                'example_html' => $exampleHtml,
            ];

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
     * @return array<string, NoteItem>
     */
    private function notes(): array
    {
        $entries = $this->entries(FeedbackType::GrammarNote);
        $entryPositions = $this->entryPositions($entries);
        $items = [];

        foreach ($entries as $entry) {
            $question = $entry->practiceQuestion;
            $session = $question->practiceSession;

            $items[$this->noteKey($entry)] = [
                'key' => $this->noteKey($entry),
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
                'entry_position' => $entryPositions[$entry->id] ?? 0,
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
     * @return array<string, MistakeItem>
     */
    private function mistakes(): array
    {
        $entries = $this->entries(FeedbackType::Mistake);
        $entryPositions = $this->entryPositions($entries);

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
            $correctionHtml = RichText::cleanInlineWithText(
                (string) $entry->correction_html,
            )['html'];

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

            if ($group['correction'] === '' && $correction !== '') {
                $group['correction'] = $correction;
            }

            if ($group['correction_html'] === '' && $correctionHtml !== '') {
                $group['correction_html'] = $correctionHtml;
            }

            // The reason comes from the most recent appearance that has one;
            // every distinct reason all the same stays with its appearance.
            if ($group['reason'] === '') {
                $group['reason'] = trim((string) $entry->reason);
            }

            $group['appearances'][] = [
                'session_id' => (int) $session->id,
                'session_date' => $session->created_at,
                'topic' => (string) $session->topic->name,
                'question_position' => (int) $question->position,
                'entry_id' => is_string($entry->client_id) && $entry->client_id !== ''
                    ? $entry->client_id
                    : null,
                'entry_position' => $entryPositions[$entry->id] ?? 0,
                'said' => $said,
                'correction' => $correction,
                'correction_html' => $correctionHtml,
                'reason' => trim((string) $entry->reason),
            ];

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
     * The rows of one box, newest session first, with the sessions they came
     * from. Only finished sessions that are not archived are read, and rows
     * that hold no words at all are left out.
     *
     * @return Collection<int, PracticeFeedbackEntry>
     */
    private function entries(FeedbackType $type): Collection
    {
        return $this->entryQuery($type)
            ->with(['practiceQuestion.practiceSession.topic'])
            ->get()
            ->sort($this->compareEntries(...))
            ->values();
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

    private function comparatorFor(FeedbackType $type): callable
    {
        return match ($type) {
            FeedbackType::GrammarNote => $this->compareNotes(...),
            FeedbackType::Mistake => $this->compareMistakes(...),
            default => $this->compareGroupedItems(...),
        };
    }

    /**
     * Grouped items, newest session first. Two items whose latest session
     * shares a moment fall back to their normalised key, so the order is
     * stable across pages.
     *
     * @param  GroupItem  $left
     * @param  GroupItem  $right
     */
    private function compareGroupedItems(array $left, array $right): int
    {
        $byTime = $right['latest_at']->getTimestamp() <=> $left['latest_at']->getTimestamp();

        return $byTime !== 0 ? $byTime : $left['key'] <=> $right['key'];
    }

    /**
     * Mistakes, most often seen first. Two mistakes seen as often are ordered
     * by their latest session, newest first, and fall back to their normalised
     * key, so the order is stable across pages.
     *
     * @param  MistakeItem  $left
     * @param  MistakeItem  $right
     */
    private function compareMistakes(array $left, array $right): int
    {
        $bySeen = $right['seen_count'] <=> $left['seen_count'];

        if ($bySeen !== 0) {
            return $bySeen;
        }

        $byTime = $right['latest_at']->getTimestamp() <=> $left['latest_at']->getTimestamp();

        return $byTime !== 0 ? $byTime : $left['key'] <=> $right['key'];
    }

    /**
     * Notes, newest session first. Ties fall back to where the note sits in
     * the session — question order, then note order — and finally to the
     * note's identity, so the order never shuffles between pages.
     *
     * @param  NoteItem  $left
     * @param  NoteItem  $right
     */
    private function compareNotes(array $left, array $right): int
    {
        $byTime = $right['latest_at']->getTimestamp() <=> $left['latest_at']->getTimestamp();

        if ($byTime !== 0) {
            return $byTime;
        }

        $byPlace = $this->noteOrder($left) <=> $this->noteOrder($right);

        if ($byPlace !== 0) {
            return $byPlace;
        }

        return $left['key'] <=> $right['key'];
    }

    /**
     * Whether an item comes after the cursor's item in the same order.
     * Mistakes are ordered by how often they were seen, so the cursor names
     * the last item's seen count, its latest session and its key.
     *
     * @param  GroupItem|NoteItem|MistakeItem  $item
     */
    private function isAfter(
        array $item,
        CollectionCursor $cursor,
        FeedbackType $type,
    ): bool {
        if ($type === FeedbackType::Mistake
            && array_key_exists('seen_count', $item)) {
            $itemSeen = $item['seen_count'];
            $cursorSeen = $cursor->position ?? 0;

            if ($itemSeen !== $cursorSeen) {
                return $itemSeen < $cursorSeen;
            }
        }

        $itemTime = $item['latest_at']->getTimestamp();
        $cursorTime = $cursor->latestAt->getTimestamp();

        if ($itemTime !== $cursorTime) {
            return $itemTime < $cursorTime;
        }

        if ($type === FeedbackType::GrammarNote) {
            $itemPlace = $this->noteOrder($item);
            $cursorPlace = $cursor->position ?? -1;

            if ($itemPlace !== $cursorPlace) {
                return $itemPlace > $cursorPlace;
            }
        }

        return $item['key'] > $cursor->key;
    }

    /**
     * The cursor that names one item, so the next page starts after it. The
     * mistakes carry how often they were seen in the cursor's position slot,
     * since their order names the seen count first.
     *
     * @param  GroupItem|NoteItem|MistakeItem  $item
     */
    private function cursorFor(
        array $item,
        FeedbackType $type,
    ): CollectionCursor {
        $position = $type === FeedbackType::GrammarNote
            ? $this->noteOrder($item)
            : ($type === FeedbackType::Mistake
                && array_key_exists('seen_count', $item)
                ? $item['seen_count']
                : null);

        return new CollectionCursor(
            $item['key'],
            $item['latest_at'],
            $position,
        );
    }

    /**
     * The place of a note in its session as one comparable number: the
     * question it was saved under, then the note's row in that box. Just
     * being handed an item of any shape keeps the cursor code simple: rows
     * that are not notes simply have no place numbers and count as zero.
     * The row numbers stay below `NOTE_ORDER_STEP`, so no two places collide.
     *
     * @param  GroupItem|NoteItem|MistakeItem  $item
     */
    private function noteOrder(array $item): int
    {
        $question = array_key_exists('question_position', $item)
            ? $item['question_position']
            : 0;
        $row = array_key_exists('entry_position', $item)
            ? $item['entry_position']
            : 0;

        return ((int) $question) * self::NOTE_ORDER_STEP + (int) $row;
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
