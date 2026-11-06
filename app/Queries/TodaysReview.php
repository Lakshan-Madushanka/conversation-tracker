<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CollectionItemMark;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewSchedule;
use App\Support\ExerciseKind;
use App\Support\LearnerDay;
use App\Support\ReviewAllocation;
use App\Support\ReviewExercises;
use App\Support\ReviewFilters;
use App\Support\ReviewSettings;
use App\Support\ReviewSuggestion;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Choosing today's items, in one place on the server.
 *
 * Candidates are reviewable words, phrases, mistakes and questions from
 * finished, non-archived sessions that are either due again (by the item's
 * schedule) or never reviewed. Learned items are left out unless they are due
 * again; a never-reviewed Learned item is never suggested. A question has no
 * star or status, so it takes part through its schedule alone.
 *
 * The sitting is split between the built exercise kinds by the target shares
 * in `ReviewSettings`, renormalised over the kinds that actually have
 * something to offer. Within one kind the Step 2 rules still hold: due Hard
 * items, then due Still difficult items, then due starred items, then the
 * other due items most overdue first, then new items (starred or Still
 * difficult first). Due items crowd out new ones. If one kind cannot fill its
 * share, the other kinds take the free slots, so a full sitting is still on
 * offer on a quiet day. When the sitting has room for every kind, each kind
 * keeps at least one slot, so its step never disappears while it has something
 * to show.
 *
 * The choice is worked out from the current data every time, in bulk: one
 * grouped candidate query for the saved entries and one for the questions, one
 * read of the schedules, one of the marks and one of today's ratings — the
 * number of queries never grows with the number of items. Items are returned
 * grouped one list per kind, in the fixed friendly order the stepper uses;
 * within each kind due and new items are interleaved evenly.
 *
 * A custom review is the same piece with `App\Support\ReviewFilters` naming
 * what the learner asked for. The topics and collections narrow the candidates
 * in the database; starred-only and the Learned choice narrow them here; and
 * because the learner asked for these items, the ones still resting between
 * reviews join the sitting after the due work instead of staying out. Nothing
 * else changes: the schedule, the shares, the estimate, the allocation and the
 * order are the ones above, so a preview and the started review always agree.
 */
final readonly class TodaysReview
{
    /** The registry key of the exercise built from leftovers and notes. */
    private const string FILL_EXERCISE = 'fill_in_the_blank';

    /** The natural kinds whose unpicked items can still become blanks. */
    private const array NATURAL_KINDS = ['flashcard', 'mistake', 'say_it_again'];

    public function __construct(
        private ReviewCandidates $candidates,
        private LearnerDay $day,
    ) {}

    /**
     * The items worth reviewing right now, grouped by exercise kind.
     *
     * The daily suggestion passes no filters. A custom review passes the
     * learner's choices, and the same shares, times and allocation produce its
     * fixed list — only the pool the choices name, and the fact that the
     * learner asked for these items, differ.
     */
    public function suggest(
        int $size = ReviewSettings::SIZE,
        bool $excludingRatedToday = false,
        ?Request $request = null,
        ?ReviewFilters $filters = null,
    ): ReviewSuggestion {
        $filters ??= ReviewFilters::daily();
        $today = $this->day->today($request);
        $rows = $this->candidateRows($filters);

        if ($rows === []) {
            return new ReviewSuggestion([]);
        }

        /** @var list<array{kind: FeedbackType, identity: string}> $pairs */
        $pairs = array_map(fn (array $row): array => [
            'kind' => FeedbackType::from($row['type']),
            'identity' => $row['collection_key'],
        ], $rows);

        // Which current items can build a blank, read in one bulk pass and
        // used both to skip unusable notes and to decide whether the blank
        // step joins the sitting at all.
        $blankable = $this->candidates->blankableKeys($pairs);

        $schedules = $this->schedulesFor();
        $marks = $this->marksFor();
        $ratedToday = $excludingRatedToday ? $this->ratedToday($today) : [];

        /** @var array<string, array{due: list<array{priority: int, due_on: int, kind: FeedbackType, identity: string}>, new: list<array{kind: FeedbackType, identity: string, boosted: bool}>}> $pools */
        $pools = [];

        foreach ($rows as $row) {
            $kind = FeedbackType::from($row['type']);
            $identity = $row['collection_key'];
            $key = $this->candidates->key($kind, $identity);
            $exercise = ReviewExercises::kindOf($kind);

            if (! ReviewExercises::find($exercise) instanceof ExerciseKind) {
                continue;
            }

            if (isset($ratedToday[$key])) {
                continue;
            }

            // A grammar note has no other exercise, so one that cannot make a
            // blank is skipped rather than shown as a broken card.
            if ($kind === FeedbackType::GrammarNote && ! isset($blankable[$key])) {
                continue;
            }

            $mark = $marks[$key] ?? null;
            $starred = $mark !== null && $mark->starred;
            $status = $mark?->status;
            $schedule = $schedules[$key] ?? null;

            // Starred-only is the learner asking for what they starred, so an
            // item without a star has no place in the sitting.
            if ($filters->starredOnly && ! $starred) {
                continue;
            }

            // A Learned item is left out of a custom sitting until the learner
            // asks for it. The daily suggestion keeps Step 2's rule: a due
            // Learned item comes back, a never-reviewed one never does.
            if ($status === CollectionItemStatus::Learned
                && ! $filters->includeLearned
                && ($schedule === null || $filters->custom)) {
                continue;
            }

            $pools[$exercise] ??= ['due' => [], 'new' => []];

            if ($schedule === null) {
                $pools[$exercise]['new'][] = [
                    'kind' => $kind,
                    'identity' => $identity,
                    'boosted' => $starred || $status === CollectionItemStatus::StillDifficult,
                ];

                continue;
            }

            $isDue = $schedule->due_on->toDateString() <= $today->toDateString();

            if (! $isDue && ! $filters->custom) {
                // Rated, and not due yet; resting until its day comes.
                continue;
            }

            // The daily rules decide the order of genuinely due work. Items the
            // learner asked for but that are still resting join after it, so a
            // custom sitting never puts resting work ahead of what is due.
            $priority = match (true) {
                ! $isDue => 5,
                $schedule->last_rating === ReviewRating::Hard => 1,
                $status === CollectionItemStatus::StillDifficult => 2,
                $starred => 3,
                default => 4,
            };

            $pools[$exercise]['due'][] = [
                'priority' => $priority,
                'due_on' => $schedule->due_on->startOfDay()->getTimestamp(),
                'kind' => $kind,
                'identity' => $identity,
            ];
        }

        $grammarNoteCandidates = isset($pools[self::FILL_EXERCISE])
            ? count($pools[self::FILL_EXERCISE]['due']) + count($pools[self::FILL_EXERCISE]['new'])
            : 0;

        $blankableNatural = 0;

        foreach ($pairs as $pair) {
            if ($pair['kind'] === FeedbackType::GrammarNote) {
                continue;
            }

            if (isset($blankable[$this->candidates->key($pair['kind'], $pair['identity'])])) {
                $blankableNatural += 1;
            }
        }

        // Blanks share the sitting when a grammar note can make one, or when
        // the natural exercises will leave blankable items behind. Otherwise a
        // small review keeps the old mix, with no empty fourth step.
        $blankActive = $grammarNoteCandidates > 0 || $blankableNatural > $size;

        if ($blankActive && ! isset($pools[self::FILL_EXERCISE])) {
            $pools[self::FILL_EXERCISE] = ['due' => [], 'new' => []];
        }

        $active = $this->activeKeys($pools);

        if ($blankActive && ! in_array(self::FILL_EXERCISE, $active, true)) {
            // A blank step made only of leftovers has no pool rows of its own.
            $pools[self::FILL_EXERCISE] = ['due' => [], 'new' => []];
            $active[] = self::FILL_EXERCISE;
        }

        if ($active === []) {
            return new ReviewSuggestion([]);
        }

        foreach ($active as $exercise) {
            usort($pools[$exercise]['due'], fn (array $a, array $b): int => $a['priority'] <=> $b['priority']
                ?: $a['due_on'] <=> $b['due_on']
                ?: strcmp($a['kind']->value."\u{0}".$a['identity'], $b['kind']->value."\u{0}".$b['identity']));

            // Starred and Still difficult new items lead the kind; the two
            // groups keep their own varied order.
            $boosted = [];
            $plain = [];

            foreach ($pools[$exercise]['new'] ?? [] as $item) {
                if ($item['boosted']) {
                    $boosted[] = $item;
                } else {
                    $plain[] = $item;
                }
            }

            shuffle($boosted);
            shuffle($plain);
            $pools[$exercise]['new'] = [...$boosted, ...$plain];
        }

        // A leftover-only blank step must not take the guaranteed slot from a
        // natural exercise in a tiny review; a grammar-note-backed one may.
        $mustFill = array_values(array_filter(
            $active,
            fn (string $exercise): bool => $exercise !== self::FILL_EXERCISE
                || $grammarNoteCandidates > 0,
        ));

        $quotas = ReviewAllocation::quotas($active, $size, ReviewSettings::TARGET_SHARES, $mustFill);

        $dueAvailable = 0;
        $candidateTotal = 0;

        foreach ($active as $exercise) {
            $dueAvailable += count($pools[$exercise]['due']);
            $candidateTotal += count($pools[$exercise]['due']) + count($pools[$exercise]['new']);
        }

        // New items are limited when there is enough due work to fill the
        // review; otherwise they may fill whatever is left, so a full sitting
        // is still on offer on a quiet day.
        $newAllowance = $dueAvailable >= max(0, $size - ReviewSettings::MAX_NEW_ITEMS)
            ? ReviewSettings::MAX_NEW_ITEMS
            : max(0, $size - $dueAvailable);

        /** @var array<string, list<array{kind: FeedbackType, identity: string, exercise: string}>> $pickedDue */
        $pickedDue = [];
        /** @var array<string, list<array{kind: FeedbackType, identity: string, exercise: string}>> $pickedNew */
        $pickedNew = [];
        /** @var array<string, int> $dueIndex */
        $dueIndex = [];
        /** @var array<string, int> $newIndex */
        $newIndex = [];
        /** @var array<string, true> $pickedKeys */
        $pickedKeys = [];

        foreach ($active as $exercise) {
            $pickedDue[$exercise] = [];
            $pickedNew[$exercise] = [];
            $dueIndex[$exercise] = 0;
            $newIndex[$exercise] = 0;
        }

        $picked = 0;
        $newPicked = 0;

        // Every kind the shares give a slot gets one before any kind takes a
        // second, so a kind holding only new items is not crowded out of the
        // sitting (and the stepper) by the kinds ahead of it.
        if ($size >= count($active)) {
            foreach ($active as $exercise) {
                if (($quotas[$exercise] ?? 0) < 1) {
                    continue;
                }

                if (isset($pools[$exercise]['due'][$dueIndex[$exercise]])) {
                    $item = $pools[$exercise]['due'][$dueIndex[$exercise]];
                    $pickedDue[$exercise][] = $this->brief($item, $exercise);
                    $dueIndex[$exercise] += 1;
                    $this->markPicked($pickedKeys, $item);
                    $picked += 1;

                    continue;
                }

                if ($newPicked < $newAllowance
                    && isset($pools[$exercise]['new'][$newIndex[$exercise]])) {
                    $item = $pools[$exercise]['new'][$newIndex[$exercise]];
                    $pickedNew[$exercise][] = $this->brief($item, $exercise);
                    $newIndex[$exercise] += 1;
                    $this->markPicked($pickedKeys, $item);
                    $picked += 1;
                    $newPicked += 1;
                }
            }
        }

        $naturalKinds = array_values(array_intersect($active, self::NATURAL_KINDS));

        // Each kind fills its share, due items first.
        foreach ($active as $exercise) {
            $quota = $quotas[$exercise] ?? 0;

            while (count($pickedDue[$exercise]) < $quota
                && isset($pools[$exercise]['due'][$dueIndex[$exercise]])) {
                $item = $pools[$exercise]['due'][$dueIndex[$exercise]];
                $pickedDue[$exercise][] = $this->brief($item, $exercise);
                $dueIndex[$exercise] += 1;
                $this->markPicked($pickedKeys, $item);
                $picked += 1;
            }

            while (count($pickedDue[$exercise]) + count($pickedNew[$exercise]) < $quota
                && $newPicked < $newAllowance
                && isset($pools[$exercise]['new'][$newIndex[$exercise]])) {
                $item = $pools[$exercise]['new'][$newIndex[$exercise]];
                $pickedNew[$exercise][] = $this->brief($item, $exercise);
                $newIndex[$exercise] += 1;
                $this->markPicked($pickedKeys, $item);
                $picked += 1;
                $newPicked += 1;
            }

            // The blank step also takes the items its natural exercises left
            // behind, due before new and within the sitting's new allowance.
            if ($exercise !== self::FILL_EXERCISE) {
                continue;
            }

            while (count($pickedDue[$exercise]) + count($pickedNew[$exercise]) < $quota) {
                $leftover = $this->takeLeftover(
                    $naturalKinds,
                    $pools,
                    $dueIndex,
                    $newIndex,
                    $blankable,
                    $pickedKeys,
                    $newAllowance,
                    $newPicked,
                );

                if ($leftover === null) {
                    break;
                }

                if ($leftover['bucket'] === 'due') {
                    $pickedDue[$exercise][] = $leftover['item'];
                } else {
                    $pickedNew[$exercise][] = $leftover['item'];
                }

                $picked += 1;
            }
        }

        // A kind with fewer candidates than its share leaves a gap. Fill it
        // from the other kinds first — due work, then new work within the
        // sitting's new limit — and then from the blanks left over.
        $limit = min($size, $candidateTotal);

        while ($picked < $limit) {
            $added = false;

            foreach ($active as $exercise) {
                if ($picked >= $limit) {
                    break;
                }

                if (isset($pools[$exercise]['due'][$dueIndex[$exercise]])) {
                    $item = $pools[$exercise]['due'][$dueIndex[$exercise]];
                    $pickedDue[$exercise][] = $this->brief($item, $exercise);
                    $dueIndex[$exercise] += 1;
                    $this->markPicked($pickedKeys, $item);
                    $picked += 1;
                    $added = true;
                }
            }

            if ($picked < $limit && $newPicked < $newAllowance) {
                foreach ($active as $exercise) {
                    if ($picked >= $limit || $newPicked >= $newAllowance) {
                        break;
                    }

                    if (isset($pools[$exercise]['new'][$newIndex[$exercise]])) {
                        $item = $pools[$exercise]['new'][$newIndex[$exercise]];
                        $pickedNew[$exercise][] = $this->brief($item, $exercise);
                        $newIndex[$exercise] += 1;
                        $this->markPicked($pickedKeys, $item);
                        $picked += 1;
                        $newPicked += 1;
                        $added = true;
                    }
                }
            }

            if ($picked < $limit && $blankActive) {
                while ($picked < $limit) {
                    $leftover = $this->takeLeftover(
                        $naturalKinds,
                        $pools,
                        $dueIndex,
                        $newIndex,
                        $blankable,
                        $pickedKeys,
                        $newAllowance,
                        $newPicked,
                    );

                    if ($leftover === null) {
                        break;
                    }

                    if ($leftover['bucket'] === 'due') {
                        $pickedDue[self::FILL_EXERCISE][] = $leftover['item'];
                    } else {
                        $pickedNew[self::FILL_EXERCISE][] = $leftover['item'];
                    }

                    $picked += 1;
                    $added = true;
                }
            }

            if (! $added) {
                break;
            }
        }

        // Each kind keeps its own mixed order; the kinds follow the fixed
        // friendly order, with no order imposed across them.
        $ordered = [];

        foreach ($active as $exercise) {
            foreach ($this->interleave($pickedDue[$exercise], $pickedNew[$exercise]) as $item) {
                $ordered[] = $item;
            }
        }

        return new ReviewSuggestion(array_slice($ordered, 0, $size));
    }

    /**
     * The suggestion card's state for Home and Review Home, worked out from
     * the current data every time. Returns the payload's pieces; the page
     * decides how to draw them.
     *
     * @return array{
     *     state: 'suggest'|'done'|'nothing'|'empty',
     *     count: int|null,
     *     minutes: int|null,
     *     mix: string|null,
     *     canReviewMore: bool|null,
     * }
     */
    public function card(?Request $request = null): array
    {
        if (! PracticeSession::query()->study()->exists()) {
            return ['state' => 'empty', 'count' => null, 'minutes' => null, 'mix' => null, 'canReviewMore' => null];
        }

        $today = $this->day->today($request);

        $doneToday = Review::query()
            ->where('is_daily_review', true)
            ->whereDate('completed_on', $today->toDateString())
            ->exists();

        if ($doneToday) {
            $more = $this->suggest(excludingRatedToday: true, request: $request);

            return [
                'state' => 'done',
                'count' => null,
                'minutes' => null,
                'mix' => null,
                'canReviewMore' => $more->count() > 0,
            ];
        }

        $suggestion = $this->suggest(request: $request);

        if ($suggestion->count() === 0) {
            return ['state' => 'nothing', 'count' => null, 'minutes' => null, 'mix' => null, 'canReviewMore' => null];
        }

        return [
            'state' => 'suggest',
            'count' => $suggestion->count(),
            'minutes' => $suggestion->minutes(),
            'mix' => $suggestion->mix(),
            'canReviewMore' => null,
        ];
    }

    /**
     * Distinct type + identity candidates, the shared definition of what can
     * be reviewed at all, plus the grammar notes that can make a blank.
     *
     * The choices narrow the same queries: topics and collections in the
     * database, and the kinds a sitting cannot hold are not read at all.
     *
     * @return list<array{type: string, collection_key: string}>
     */
    private function candidateRows(ReviewFilters $filters): array
    {
        $entries = array_values($this->candidates->base($filters)
            ->select(['entries.type', 'entries.collection_key'])
            ->distinct()
            ->get()
            ->map(fn (object $row): array => [
                'type' => (string) $row->type,
                'collection_key' => (string) $row->collection_key,
            ])
            ->all());

        $questions = $filters->allows(FeedbackType::Question)
            ? array_values($this->candidates->questionsBase($filters)
                ->select('questions.id as collection_key')
                ->distinct()
                ->get()
                ->map(fn (object $row): array => [
                    'type' => FeedbackType::Question->value,
                    'collection_key' => (string) $row->collection_key,
                ])
                ->all())
            : [];

        $notes = $filters->allows(FeedbackType::GrammarNote)
            ? array_values($this->candidates->grammarNotesBase($filters)
                ->select('entries.collection_key as collection_key')
                ->distinct()
                ->get()
                ->map(fn (object $row): array => [
                    'type' => FeedbackType::GrammarNote->value,
                    'collection_key' => (string) $row->collection_key,
                ])
                ->all())
            : [];

        return [...$entries, ...$questions, ...$notes];
    }

    /**
     * The kinds present in this pool, in the registry's fixed order.
     *
     * @param  array<string, array{due: list<mixed>, new: list<mixed>}>  $pools
     * @return list<string>
     */
    private function activeKeys(array $pools): array
    {
        $active = [];

        foreach (ReviewExercises::all() as $kind) {
            if (isset($pools[$kind->key])
                && ($pools[$kind->key]['due'] !== [] || $pools[$kind->key]['new'] !== [])) {
                $active[] = $kind->key;
            }
        }

        foreach ($pools as $key => $pool) {
            if (! in_array($key, $active, true)
                && ($pool['due'] !== [] || $pool['new'] !== [])) {
                $active[] = $key;
            }
        }

        return $active;
    }

    /**
     * The stored schedules, read once for the whole set: one read per kind
     * rather than one question per item. A few thousand rows is the natural
     * size here, and the read stays honest however big the pool grows.
     *
     * @return array<string, ReviewSchedule> keyed by kind and identity
     */
    private function schedulesFor(): array
    {
        $found = [];

        foreach (FeedbackType::reviewableTypes() as $kind) {
            foreach (ReviewSchedule::query()->where('kind', $kind->value)->get() as $schedule) {
                $found[$this->candidates->key($kind, $schedule->item_identity)] = $schedule;
            }
        }

        return $found;
    }

    /**
     * The star and status behind each candidate, read once for the whole
     * set: a few thousand rows rather than one question per item.
     *
     * @return array<string, CollectionItemMark> keyed by kind and identity
     */
    private function marksFor(): array
    {
        $found = [];

        foreach (FeedbackType::collectable() as $kind) {
            $storage = CollectionItemMark::query()
                ->where('kind', $kind->value)
                ->get(['item_key', 'starred', 'status', 'group_key']);

            foreach ($storage as $mark) {
                // The public identity comes from the mark's own group key,
                // so the storage hash never has to be reversed.
                if ($mark->group_key === null || $mark->group_key === '') {
                    continue;
                }

                $found[$this->candidates->key($kind, $mark->group_key)] = $mark;
            }
        }

        return $found;
    }

    /**
     * The items rated on the learner's local day, for the "Review a bit
     * more" selection.
     *
     * @return array<string, true> keyed by kind and identity
     */
    private function ratedToday(CarbonImmutable $today): array
    {
        $pairs = Rating::query()
            ->where('created_at', '>=', $today->setTimezone('UTC'))
            ->whereIn('kind', array_map(
                fn (FeedbackType $kind): string => $kind->value,
                FeedbackType::reviewableTypes(),
            ))
            ->get(['kind', 'item_identity']);

        $found = [];

        foreach ($pairs as $rating) {
            $found[$this->candidates->key($rating->kind, $rating->item_identity)] = true;
        }

        return $found;
    }

    /**
     * Take the next blankable item the natural pools left behind, due before
     * new and within the sitting's new allowance. The natural pool's own
     * cursor advances, so the natural loops never see the item again.
     *
     * @param  list<string>  $naturalKinds
     * @param  array<string, array{due: list<array{priority?: int, due_on?: int, kind: FeedbackType, identity: string, boosted?: bool}>, new: list<array{kind: FeedbackType, identity: string, boosted: bool}>}>  $pools
     * @param  array<string, int>  $dueIndex
     * @param  array<string, int>  $newIndex
     * @param  array<string, true>  $blankable
     * @param  array<string, true>  $pickedKeys
     * @return array{item: array{kind: FeedbackType, identity: string, exercise: string}, bucket: 'due'|'new'}|null
     */
    private function takeLeftover(
        array $naturalKinds,
        array $pools,
        array &$dueIndex,
        array &$newIndex,
        array $blankable,
        array &$pickedKeys,
        int $newAllowance,
        int &$newPicked,
    ): ?array {
        foreach ($naturalKinds as $exercise) {
            while (isset($pools[$exercise]['due'][$dueIndex[$exercise]])) {
                $item = $pools[$exercise]['due'][$dueIndex[$exercise]];
                $dueIndex[$exercise] += 1;
                $key = $this->candidates->key($item['kind'], $item['identity']);

                if (isset($pickedKeys[$key]) || ! isset($blankable[$key])) {
                    continue;
                }

                $pickedKeys[$key] = true;

                return ['item' => $this->brief($item, self::FILL_EXERCISE), 'bucket' => 'due'];
            }
        }

        foreach ($naturalKinds as $exercise) {
            while ($newPicked < $newAllowance && isset($pools[$exercise]['new'][$newIndex[$exercise]])) {
                $item = $pools[$exercise]['new'][$newIndex[$exercise]];
                $newIndex[$exercise] += 1;
                $key = $this->candidates->key($item['kind'], $item['identity']);

                if (isset($pickedKeys[$key]) || ! isset($blankable[$key])) {
                    continue;
                }

                $pickedKeys[$key] = true;
                $newPicked += 1;

                return ['item' => $this->brief($item, self::FILL_EXERCISE), 'bucket' => 'new'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, true>  $pickedKeys
     * @param  array{kind: FeedbackType, identity: string}  $item
     */
    private function markPicked(array &$pickedKeys, array $item): void
    {
        $pickedKeys[$this->candidates->key($item['kind'], $item['identity'])] = true;
    }

    /**
     * The fields the review needs, without the ordering helpers.
     *
     * @param  array{kind: FeedbackType, identity: string}  $item
     * @return array{kind: FeedbackType, identity: string, exercise: string}
     */
    private function brief(array $item, string $exercise): array
    {
        return ['kind' => $item['kind'], 'identity' => $item['identity'], 'exercise' => $exercise];
    }

    /**
     * Spread the new items evenly through the due items, keeping each
     * group's own order.
     *
     * @param  list<array{kind: FeedbackType, identity: string, exercise: string}>  $due
     * @param  list<array{kind: FeedbackType, identity: string, exercise: string}>  $new
     * @return list<array{kind: FeedbackType, identity: string, exercise: string}>
     */
    private function interleave(array $due, array $new): array
    {
        if ($new === []) {
            return $due;
        }

        if ($due === []) {
            return $new;
        }

        $result = [];
        $dueIndex = 0;
        $newCount = count($new);
        $dueCount = count($due);
        $step = intdiv($dueCount, $newCount);
        $extra = $dueCount % $newCount;

        foreach ($new as $position => $item) {
            $take = $step + ($position < $extra ? 1 : 0);

            for ($i = 0; $i < $take; $i += 1) {
                $result[] = $due[$dueIndex];
                $dueIndex += 1;
            }

            $result[] = $item;
        }

        while ($dueIndex < $dueCount) {
            $result[] = $due[$dueIndex];
            $dueIndex += 1;
        }

        return $result;
    }
}
