<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Models\CustomReviewItem;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Queries\CustomReviewItems;
use App\Support\CustomReviewIdentity;
use App\Support\CustomReviewStatusRules;
use App\Support\MistakeIdentity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Removes review references whose item no longer exists at all.
 *
 * An item that is only archived still exists, so it stays and is shown as not
 * available right now. An item whose last session was deleted has nothing left
 * to point at, so its reference is removed for good from every review that
 * holds it, instead of leaving a ghost row or an empty line.
 *
 * This is the one repair a review read performs, called when a review's page
 * opens, so every view of a review keeps the same rule. A review that has lost
 * its last item this way becomes `Empty` like any other removal.
 */
final readonly class PruneCustomReviewItems
{
    public function __construct(
        private CustomReviewItems $items,
    ) {}

    /** @return int how many references were removed */
    public function handle(): int
    {
        /** @var Collection<string, list<string>> $grouped */
        $grouped = CustomReviewItem::query()
            ->get(['kind', 'item_identity'])
            ->groupBy(fn (CustomReviewItem $item): string => $item->kind->value)
            ->map(fn (Collection $rows): array => $rows
                ->pluck('item_identity')
                ->unique()
                ->values()
                ->all());

        /** @var array<string, list<string>> $dead */
        $dead = [];

        foreach ($grouped as $kindValue => $identities) {
            $kind = FeedbackType::tryFrom($kindValue);

            if ($kind === null || ! $kind->isCustomReviewType()) {
                $dead[$kindValue] = $identities;

                continue;
            }

            $missing = array_values(array_diff($identities, $this->liveIdentities($kind, $identities)));

            if ($missing !== []) {
                $dead[$kindValue] = $missing;
            }
        }

        if ($dead === []) {
            return 0;
        }

        return DB::transaction(function () use ($dead): int {
            $affected = [];
            $removed = 0;

            foreach ($dead as $kindValue => $identities) {
                $rows = CustomReviewItem::query()
                    ->where('kind', $kindValue)
                    ->whereIn('item_identity', $identities);

                $affected = array_merge($affected, $rows->pluck('custom_review_id')->all());

                $removed += $rows->delete();
            }

            $this->syncStatuses(array_values(array_unique($affected)));

            return $removed;
        });
    }

    /**
     * The identities of one kind that still have a source row, including rows
     * in archived sessions. Availability is decided later; here only existence
     * matters.
     *
     * @param  list<string>  $identities
     * @return list<string>
     */
    private function liveIdentities(FeedbackType $kind, array $identities): array
    {
        if ($kind === FeedbackType::GrammarNote) {
            $ids = array_values(array_filter(array_map(
                CustomReviewIdentity::noteId(...),
                $identities,
            )));

            if ($ids === []) {
                return [];
            }

            /** @var list<string> $keys */
            $keys = PracticeFeedbackEntry::query()
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn (int $id): string => CustomReviewIdentity::noteKey($id))
                ->all();

            return $keys;
        }

        if ($kind === FeedbackType::Mistake) {
            $wanted = array_fill_keys($identities, true);
            $live = [];

            // The pair key is derived from both columns, so the rows of this
            // kind are read and matched by the same rule the collections use.
            PracticeFeedbackEntry::query()
                ->where('type', FeedbackType::Mistake->value)
                ->select(['id', 'content_text', 'correction_text'])
                ->chunkById(500, function (Collection $rows) use ($wanted, &$live): void {
                    foreach ($rows as $row) {
                        $said = trim((string) $row->content_text);
                        $correction = trim((string) $row->correction_text);

                        if (MistakeIdentity::isBlank($said, $correction)) {
                            continue;
                        }

                        $key = MistakeIdentity::pairKey($said, $correction);

                        if (isset($wanted[$key])) {
                            $live[$key] = true;
                        }
                    }
                });

            return array_keys($live);
        }

        if ($kind === FeedbackType::Question) {
            /** @var list<string> $ids */
            $ids = PracticeQuestion::query()
                ->whereIn('id', array_map(intval(...), $identities))
                ->pluck('id')
                ->map(fn (int $id): string => (string) $id)
                ->all();

            return $ids;
        }

        /** @var list<string> $keys */
        $keys = PracticeFeedbackEntry::query()
            ->where('type', $kind->value)
            ->whereIn('collection_key', $identities)
            ->pluck('collection_key')
            ->unique()
            ->values()
            ->all();

        return $keys;
    }

    /**
     * The reviews that lost a reference, given the status their remaining
     * items tell them to hold. One grouped count covers them all.
     *
     * @param  list<int>  $reviewIds
     */
    private function syncStatuses(array $reviewIds): void
    {
        if ($reviewIds === []) {
            return;
        }

        $counts = $this->items->counts($reviewIds);

        /** @var Collection<int, CustomReview> $reviews */
        $reviews = CustomReview::query()
            ->whereIn('id', $reviewIds)
            ->get();

        foreach ($reviews as $review) {
            CustomReviewStatusRules::followItemCount(
                $review,
                $counts[$review->id] ?? 0,
            );
        }
    }
}
