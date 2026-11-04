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
use App\Support\LearnerDay;
use App\Support\ReviewSettings;
use App\Support\ReviewSuggestion;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Choosing today's items, in one place on the server.
 *
 * Candidates are reviewable words and phrases from finished, non-archived
 * sessions that are either due again (by the item's schedule) or never
 * reviewed. Learned items are left out unless they are due again; a
 * never-reviewed Learned item is never suggested.
 *
 * The choice is worked out from the current data every time, in bulk: one
 * grouped candidate query and one read of the schedules and one of the
 * marks for the whole set — the number of queries never grows with the
 * number of items.
 *
 * The fill order is: due Hard items, then due Still difficult items, then
 * due starred items, then the other due items most overdue first, then new
 * items (starred or Still difficult first). An item appears at most once.
 * Due items crowd out new ones, and new items take what is left, up to the
 * size of the review. Within the review, due and new items are interleaved
 * evenly so the sitting feels varied.
 */
final readonly class TodaysReview
{
    public function __construct(
        private ReviewCandidates $candidates,
        private LearnerDay $day,
    ) {}

    /**
     * The items worth reviewing right now, in the order the learner meets
     * them.
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

        /** @var list<array{priority: int, due_on: int, kind: FeedbackType, identity: string}> $due */
        $due = [];
        $boostedNew = [];
        $plainNew = [];

        foreach ($rows as $row) {
            $kind = FeedbackType::from($row['type']);
            $identity = $row['collection_key'];
            $key = $this->candidates->key($kind, $identity);

            if (isset($ratedToday[$key])) {
                continue;
            }

            $mark = $marks[$key] ?? null;
            $starred = $mark !== null && $mark->starred;
            $status = $mark?->status;
            $schedule = $schedules[$key] ?? null;

            if ($schedule === null) {
                // A never-reviewed Learned item is not suggested.
                if ($status === CollectionItemStatus::Learned) {
                    continue;
                }

                $candidate = [
                    'kind' => $kind,
                    'identity' => $identity,
                ];

                if ($starred || $status === CollectionItemStatus::StillDifficult) {
                    $boostedNew[] = $candidate;
                } else {
                    $plainNew[] = $candidate;
                }

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

            $due[] = [
                'priority' => $priority,
                'due_on' => $schedule->due_on->startOfDay()->getTimestamp(),
                'kind' => $kind,
                'identity' => $identity,
            ];
        }

        usort($due, fn (array $a, array $b): int => $a['priority'] <=> $b['priority']
            ?: $a['due_on'] <=> $b['due_on']
            ?: strcmp($a['kind']->value."\u{0}".$a['identity'], $b['kind']->value."\u{0}".$b['identity']));

        // New items, starred or Still difficult first, then in a varied
        // order; the groups stay in that order in the final mix.
        shuffle($boostedNew);
        shuffle($plainNew);
        $new = [...$boostedNew, ...$plainNew];

        $pickedDue = array_slice($due, 0, $size);
        $dueCount = count($pickedDue);
        $remaining = $size - $dueCount;

        // New items are limited when there is enough due work to fill the
        // review; otherwise they fill whatever is left, so a full sitting is
        // still on offer on a quiet day.
        $maxNew = max(0, ReviewSettings::MAX_NEW_ITEMS);

        $newTake = $dueCount >= $size - $maxNew
            ? min($remaining, $maxNew)
            : $remaining;

        $pickedNew = array_slice($new, 0, max(0, $newTake));

        // Due items mixed with new ones, evenly spread through the sitting.
        $ordered = $this->interleave($pickedDue, $pickedNew);

        return new ReviewSuggestion(array_map(
            fn (array $item): array => [
                'kind' => $item['kind'],
                'identity' => $item['identity'],
            ],
            $ordered,
        ));
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
        return array_values($this->candidates->base()
            ->select(['entries.type', 'entries.collection_key'])
            ->distinct()
            ->get()
            ->map(fn (object $row): array => [
                'type' => (string) $row->type,
                'collection_key' => (string) $row->collection_key,
            ])
            ->all());
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

        foreach ([FeedbackType::Vocabulary, FeedbackType::Phrase] as $kind) {
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

        foreach ([FeedbackType::Vocabulary, FeedbackType::Phrase] as $kind) {
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
            ->whereIn('kind', [FeedbackType::Vocabulary->value, FeedbackType::Phrase->value])
            ->get(['kind', 'item_identity']);

        $found = [];

        foreach ($pairs as $rating) {
            $found[$this->candidates->key($rating->kind, $rating->item_identity)] = true;
        }

        return $found;
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
