<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CollectionItemMark;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewSuggestionDecline;
use App\Support\LearnerDay;
use App\Support\ReviewSchedule;
use App\Support\ReviewSettings;
use App\Support\ScheduleState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The status changes a finished review may gently suggest.
 *
 * Only what the learner actually rated is described, and only the run of
 * ratings that just ended is consulted: three Easy ratings in a row can offer
 * Learned, two Hard in a row can offer Still difficult, and the words for those
 * thresholds live in `App\Support\ReviewSettings`. The run is walked by the one
 * rebuild the schedule uses, so a suggestion and a schedule can never disagree.
 *
 * A past question and a grammar note carry no status, so they are never
 * suggested. An item already carrying the offered status is left alone, and a
 * run the learner already said "Not now" to is not raised again — while a new
 * run that reaches the threshold is free to ask once more.
 *
 * Four bounded reads whatever the size of the review: the ratings of the items
 * in it, their marks, the refusals kept for them, and the words of the two
 * suggestions that come out at the end.
 *
 * @phpstan-type SuggestionRow array{
 *     kind: string,
 *     item_identity: string,
 *     status: string,
 *     status_label: string,
 *     rating_label: string,
 *     run: int,
 *     text: string,
 *     run_started_id: int
 * }
 */
final readonly class StatusSuggestions
{
    public function __construct(
        private ReviewCandidates $candidates,
        private LearnerDay $day,
    ) {}

    /**
     * @return list<SuggestionRow>
     */
    public function for(Review $review, ?Request $request = null): array
    {
        $wanted = $this->ratedItems($review);

        if ($wanted === []) {
            return [];
        }

        $histories = $this->historiesFor($wanted);
        $marks = $this->marksFor($wanted);
        $declines = $this->declinesFor($wanted);

        /** @var list<SuggestionRow> $suggestions */
        $suggestions = [];

        foreach ($wanted as $item) {
            $key = $this->candidates->key($item['kind'], $item['identity']);
            $history = $histories[$key] ?? [];

            if ($history === []) {
                continue;
            }

            $run = $this->runOf($history, $request);

            if ($run === null) {
                continue;
            }

            $status = $this->suggestedStatus($run['rating'], $run['count']);

            // Already the status on offer, or nothing the run has earned yet.
            if (! $status instanceof CollectionItemStatus || ($marks[$key] ?? null)?->status === $status) {
                continue;
            }

            // The learner already said "Not now" during this very run.
            $declined = $declines[$this->declineKey($key, $status)] ?? null;

            if ($declined !== null && $declined->run_started_id === $run['started_id']) {
                continue;
            }

            $suggestions[] = [
                'kind' => $item['kind']->value,
                'item_identity' => $item['identity'],
                'status' => $status->value,
                'status_label' => $status->label(),
                'rating_label' => $run['rating']->label(),
                'run' => $run['count'],
                'text' => '',
                'run_started_id' => $run['started_id'],
            ];

            if (count($suggestions) === ReviewSettings::MAX_STATUS_SUGGESTIONS) {
                break;
            }
        }

        if ($suggestions === []) {
            return [];
        }

        return $this->withText($suggestions);
    }

    /**
     * The items rated in this sitting, in the order the learner met them,
     * limited to the kinds that carry a status at all.
     *
     * @return list<array{kind: FeedbackType, identity: string}>
     */
    private function ratedItems(Review $review): array
    {
        /** @var array<string, true> $rated */
        $rated = [];

        foreach ($review->ratings as $rating) {
            $rated[$this->candidates->key($rating->kind, $rating->item_identity)] = true;
        }

        $wanted = [];

        foreach ($review->items as $item) {
            if (! $item->kind->isMarkable()) {
                continue;
            }

            if (isset($rated[$this->candidates->key($item->kind, $item->item_identity)])) {
                $wanted[] = ['kind' => $item->kind, 'identity' => $item->item_identity];
            }
        }

        return $wanted;
    }

    /**
     * Every rating each of these items has ever earned, oldest first, in one
     * read: a run is only honest when it sees the whole history, and nothing
     * the learner took back is in it.
     *
     * @param  list<array{kind: FeedbackType, identity: string}>  $wanted
     * @return array<string, list<Rating>> keyed by kind and identity
     */
    private function historiesFor(array $wanted): array
    {
        $rows = Rating::query()
            ->where(function (Builder $query) use ($wanted): void {
                foreach ($wanted as $item) {
                    $query->orWhere(fn (Builder $pair): Builder => $pair
                        ->where('kind', $item['kind']->value)
                        ->where('item_identity', $item['identity']));
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        /** @var array<string, list<Rating>> $histories */
        $histories = [];

        foreach ($rows as $rating) {
            $histories[$this->candidates->key($rating->kind, $rating->item_identity)][] = $rating;
        }

        return $histories;
    }

    /**
     * @param  list<array{kind: FeedbackType, identity: string}>  $wanted
     * @return array<string, CollectionItemMark> keyed by kind and identity
     */
    private function marksFor(array $wanted): array
    {
        [$kinds, $identities] = $this->scope($wanted);

        $found = [];

        foreach (CollectionItemMark::query()->whereIn('kind', $kinds)->whereIn('group_key', $identities)->get() as $mark) {
            if ($mark->group_key === null || $mark->group_key === '') {
                continue;
            }

            $found[$this->candidates->key($mark->kind, $mark->group_key)] = $mark;
        }

        return $found;
    }

    /**
     * The refusals kept for these items, keyed by the item and the status the
     * learner turned down.
     *
     * @param  list<array{kind: FeedbackType, identity: string}>  $wanted
     * @return array<string, ReviewSuggestionDecline>
     */
    private function declinesFor(array $wanted): array
    {
        [$kinds, $identities] = $this->scope($wanted);

        $found = [];

        $declines = ReviewSuggestionDecline::query()
            ->whereIn('kind', $kinds)
            ->whereIn('item_identity', $identities)
            ->get();

        foreach ($declines as $decline) {
            $found[$this->declineKey(
                $this->candidates->key($decline->kind, $decline->item_identity),
                $decline->status,
            )] = $decline;
        }

        return $found;
    }

    /**
     * The kinds and identities a read is scoped to, so nothing here looks at
     * the whole library.
     *
     * @param  list<array{kind: FeedbackType, identity: string}>  $wanted
     * @return array{list<string>, list<string>}
     */
    private function scope(array $wanted): array
    {
        $kinds = [];
        $identities = [];

        foreach ($wanted as $item) {
            $kinds[] = $item['kind']->value;
            $identities[] = $item['identity'];
        }

        return [array_values(array_unique($kinds)), array_values(array_unique($identities))];
    }

    /**
     * The run of identical ratings that ends the history, with the rating that
     * began it named so a refusal can be tied to that run alone. The counts
     * come from the schedule's own rebuild, so a suggestion and a schedule are
     * always worked out from the same walk.
     *
     * @param  list<Rating>  $history
     * @return array{rating: ReviewRating, count: int, started_id: int}|null
     */
    private function runOf(array $history, ?Request $request): ?array
    {
        $entries = [];

        foreach ($history as $rating) {
            $entries[] = [
                'rating' => $rating->rating,
                'day' => $this->day->dayOf($rating->created_at, $request),
            ];
        }

        $state = ReviewSchedule::rebuild($entries);

        if (! $state instanceof ScheduleState) {
            return null;
        }

        $started = $history[count($history) - $state->streakCount];

        return [
            'rating' => $state->lastRating,
            'count' => $state->streakCount,
            'started_id' => (int) $started->id,
        ];
    }

    /** The status the run earns, or null when it earns none. */
    private function suggestedStatus(ReviewRating $rating, int $count): ?CollectionItemStatus
    {
        if ($rating === ReviewRating::Easy && $count >= ReviewSettings::LEARNED_EASY_RUN) {
            return CollectionItemStatus::Learned;
        }

        if ($rating === ReviewRating::Hard && $count >= ReviewSettings::STILL_DIFFICULT_HARD_RUN) {
            return CollectionItemStatus::StillDifficult;
        }

        return null;
    }

    /**
     * The words each suggestion shows, read from the current entries the same
     * way every other review card reads them.
     *
     * @param  list<SuggestionRow>  $suggestions
     * @return list<SuggestionRow>
     */
    private function withText(array $suggestions): array
    {
        $pairs = [];

        foreach ($suggestions as $suggestion) {
            $pairs[] = ['kind' => FeedbackType::from($suggestion['kind']), 'identity' => $suggestion['item_identity']];
        }

        $entries = $this->candidates->cardsFor($pairs);

        foreach ($suggestions as $index => $suggestion) {
            $entry = $entries[$suggestion['kind']."\u{0}".$suggestion['item_identity']]['entry'] ?? null;

            $suggestions[$index]['text'] = $entry === null
                ? $suggestion['item_identity']
                : trim((string) $entry->content_text);
        }

        return $suggestions;
    }

    private function declineKey(string $itemKey, CollectionItemStatus $status): string
    {
        return $itemKey."\u{0}".$status->value;
    }
}
