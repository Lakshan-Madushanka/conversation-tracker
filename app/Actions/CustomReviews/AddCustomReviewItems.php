<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Models\CustomReviewItem;
use App\Queries\CustomReviewItems;
use App\Support\CustomReviewRules;
use App\Support\CustomReviewStatusRules;
use Illuminate\Support\Facades\DB;

/**
 * Adds items to one custom review by their identity.
 *
 * The request always carries the whole set the learner chose, never a toggle,
 * so repeating it — a double press, a retry, a second tab — adds nothing new.
 * The unique pair of review, kind and identity makes that true even when two
 * requests arrive at the same moment.
 *
 * The maximum is checked again inside the transaction, with the review's row
 * locked, so two adds arriving together cannot both fit: the second hears the
 * review as the first left it, and is refused whole. The kinds are re-checked
 * under the same lock, so an add that raced a narrowing in another tab is
 * refused too rather than landing an item the review no longer allows.
 *
 * The first item to arrive also moves the review from `Empty` to `Draft`: the
 * learner is building something now. A review already marked `Finished` keeps
 * that mark, because only the learner moves it.
 *
 * Only references are stored: no text, marks, statuses, ratings or sessions
 * are read or written here.
 */
final readonly class AddCustomReviewItems
{
    /** How many rows one insert carries; well under every driver's limit. */
    private const int CHUNK = 300;

    public function __construct(
        private CustomReviewItems $items,
    ) {}

    /**
     * @param  list<string>  $identities
     * @return array{
     *     added: list<string>,
     *     already: list<string>,
     *     total: int,
     *     refused: bool,
     *     refused_kind: bool
     * }
     */
    public function handle(CustomReview $review, FeedbackType $kind, array $identities): array
    {
        $identities = array_values(array_unique(array_filter(
            $identities,
            fn (string $identity): bool => $identity !== '',
        )));

        if ($identities === []) {
            return [
                'added' => [],
                'already' => [],
                'total' => $this->items->count($review),
                'refused' => false,
                'refused_kind' => false,
            ];
        }

        return DB::transaction(function () use ($review, $kind, $identities): array {
            // The lock makes the count, the kinds and the existing rows below
            // the ones the next add waits for.
            /** @var CustomReview $locked */
            $locked = CustomReview::query()->lockForUpdate()->findOrFail($review->id);

            if (! $locked->allowsKind($kind)) {
                return [
                    'added' => [],
                    'already' => [],
                    'total' => $this->items->count($review),
                    'refused' => true,
                    'refused_kind' => true,
                ];
            }

            /** @var list<string> $existing */
            $existing = CustomReviewItem::query()
                ->where('custom_review_id', $review->id)
                ->where('kind', $kind->value)
                ->whereIn('item_identity', $identities)
                ->pluck('item_identity')
                ->all();

            $already = array_values(array_intersect($identities, $existing));
            $added = array_values(array_diff($identities, $existing));
            $held = $this->items->count($review);

            if ($added !== [] && CustomReviewRules::crossesMaximum($held + count($added))) {
                return [
                    'added' => [],
                    'already' => [],
                    'total' => $held,
                    'refused' => true,
                    'refused_kind' => false,
                ];
            }

            if ($added !== []) {
                $addedAt = now();

                foreach (array_chunk($added, self::CHUNK) as $chunk) {
                    CustomReviewItem::query()->insertOrIgnore(array_map(
                        fn (string $identity): array => [
                            'custom_review_id' => $review->id,
                            'kind' => $kind->value,
                            'item_identity' => $identity,
                            'added_at' => $addedAt,
                        ],
                        $chunk,
                    ));
                }

                // The move starts from what the lock just read, and lands on
                // the instance the caller keeps.
                $review->status = $locked->status;
                CustomReviewStatusRules::followItemCount(
                    $review,
                    $this->items->count($review),
                );
            }

            return [
                'added' => $added,
                'already' => $already,
                'total' => $this->items->count($review),
                'refused' => false,
                'refused_kind' => false,
            ];
        });
    }
}
