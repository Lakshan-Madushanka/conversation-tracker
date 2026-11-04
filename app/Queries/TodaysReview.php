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
 */
final readonly class TodaysReview
{
    public function __construct(
        private ReviewCandidates $candidates,
        private LearnerDay $day,
    ) {}

    /**
     * The items worth reviewing right now, grouped by exercise kind.
     */
    public function suggest(
        int $size = ReviewSettings::SIZE,
        bool $excludingRatedToday = false,
        ?Request $request = null,
    ): ReviewSuggestion {
        $today = $this->day->today($request);
        $rows = $this->candidateRows();

        if ($rows === []) {
            return new ReviewSuggestion([]);
        }

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

            $mark = $marks[$key] ?? null;
            $starred = $mark !== null && $mark->starred;
            $status = $mark?->status;
            $schedule = $schedules[$key] ?? null;

            $pools[$exercise] ??= ['due' => [], 'new' => []];

            if ($schedule === null) {
                // A never-reviewed Learned item is not suggested.
                if ($status === CollectionItemStatus::Learned) {
                    continue;
                }

                $pools[$exercise]['new'][] = [
                    'kind' => $kind,
                    'identity' => $identity,
                    'boosted' => $starred || $status === CollectionItemStatus::StillDifficult,
                ];

                continue;
            }

            if ($schedule->due_on->toDateString() > $today->toDateString()) {
                // Rated, and not due yet; resting until its day comes.
                continue;
            }

            $priority = match (true) {
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

        $active = $this->activeKeys($pools);

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

        $quotas = ReviewAllocation::quotas($active, $size, ReviewSettings::TARGET_SHARES);

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

        /** @var array<string, list<array{kind: FeedbackType, identity: string}>> $pickedDue */
        $pickedDue = [];
        /** @var array<string, list<array{kind: FeedbackType, identity: string}>> $pickedNew */
        $pickedNew = [];
        /** @var array<string, int> $dueIndex */
        $dueIndex = [];
        /** @var array<string, int> $newIndex */
        $newIndex = [];

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
                    $pickedDue[$exercise][] = $this->brief($pools[$exercise]['due'][$dueIndex[$exercise]]);
                    $dueIndex[$exercise] += 1;
                    $picked += 1;

                    continue;
                }

                if ($newPicked < $newAllowance
                    && isset($pools[$exercise]['new'][$newIndex[$exercise]])) {
                    $pickedNew[$exercise][] = $this->brief($pools[$exercise]['new'][$newIndex[$exercise]]);
                    $newIndex[$exercise] += 1;
                    $picked += 1;
                    $newPicked += 1;
                }
            }
        }

        // Each kind fills its share, due items first.
        foreach ($active as $exercise) {
            $quota = $quotas[$exercise] ?? 0;

            while (count($pickedDue[$exercise]) < $quota
                && isset($pools[$exercise]['due'][$dueIndex[$exercise]])) {
                $pickedDue[$exercise][] = $this->brief($pools[$exercise]['due'][$dueIndex[$exercise]]);
                $dueIndex[$exercise] += 1;
                $picked += 1;
            }

            while (count($pickedDue[$exercise]) + count($pickedNew[$exercise]) < $quota
                && $newPicked < $newAllowance
                && isset($pools[$exercise]['new'][$newIndex[$exercise]])) {
                $pickedNew[$exercise][] = $this->brief($pools[$exercise]['new'][$newIndex[$exercise]]);
                $newIndex[$exercise] += 1;
                $picked += 1;
                $newPicked += 1;
            }
        }

        // A kind with fewer candidates than its share leaves a gap. Fill it
        // from the other kinds: due work first, then new work within the
        // sitting's new limit.
        $limit = min($size, $candidateTotal);

        while ($picked < $limit) {
            $added = false;

            foreach ($active as $exercise) {
                if ($picked >= $limit) {
                    break;
                }

                if (isset($pools[$exercise]['due'][$dueIndex[$exercise]])) {
                    $pickedDue[$exercise][] = $this->brief($pools[$exercise]['due'][$dueIndex[$exercise]]);
                    $dueIndex[$exercise] += 1;
                    $picked += 1;
                    $added = true;
                }
            }

            if ($picked >= $limit || $newPicked >= $newAllowance) {
                if (! $added) {
                    break;
                }

                continue;
            }

            foreach ($active as $exercise) {
                if ($picked >= $limit || $newPicked >= $newAllowance) {
                    break;
                }

                if (isset($pools[$exercise]['new'][$newIndex[$exercise]])) {
                    $pickedNew[$exercise][] = $this->brief($pools[$exercise]['new'][$newIndex[$exercise]]);
                    $newIndex[$exercise] += 1;
                    $picked += 1;
                    $newPicked += 1;
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
     * be reviewed at all.
     *
     * @return list<array{type: string, collection_key: string}>
     */
    private function candidateRows(): array
    {
        $entries = array_values($this->candidates->base()
            ->select(['entries.type', 'entries.collection_key'])
            ->distinct()
            ->get()
            ->map(fn (object $row): array => [
                'type' => (string) $row->type,
                'collection_key' => (string) $row->collection_key,
            ])
            ->all());

        $questions = array_values($this->candidates->questionsBase()
            ->select('questions.id as collection_key')
            ->distinct()
            ->get()
            ->map(fn (object $row): array => [
                'type' => FeedbackType::Question->value,
                'collection_key' => (string) $row->collection_key,
            ])
            ->all());

        return [...$entries, ...$questions];
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

        foreach (FeedbackType::tableTypes() as $kind) {
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
     * Just the two fields the review needs, without the ordering helpers.
     *
     * @param  array{kind: FeedbackType, identity: string}  $item
     * @return array{kind: FeedbackType, identity: string}
     */
    private function brief(array $item): array
    {
        return ['kind' => $item['kind'], 'identity' => $item['identity']];
    }

    /**
     * Spread the new items evenly through the due items, keeping each
     * group's own order.
     *
     * @param  list<array{kind: FeedbackType, identity: string}>  $due
     * @param  list<array{kind: FeedbackType, identity: string}>  $new
     * @return list<array{kind: FeedbackType, identity: string}>
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
