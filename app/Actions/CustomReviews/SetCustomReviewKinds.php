<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Queries\CustomReviewItems;
use App\Support\CustomReviewKinds;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The learner's kinds choice, saved whole.
 *
 * Narrowing is refused while the review still holds items of a kind being
 * turned off, so a choice can never quietly drop something: the refusal names
 * the items to remove first. Turning a kind on is always allowed. Choosing
 * every kind clears the column, because empty means "all kinds".
 *
 * The check and the write share the review's row lock, so a narrowing and an
 * add arriving together serialize: the add re-checks the kinds under the same
 * lock and refuses rather than landing an item the review no longer allows.
 *
 * Nothing stored on an attempt is read or changed here, so past attempts keep
 * their snapshots and an attempt in progress carries on.
 */
final readonly class SetCustomReviewKinds
{
    public function __construct(
        private CustomReviewItems $items,
    ) {}

    /**
     * @param  list<FeedbackType>  $kinds  the complete choice
     *
     * @throws ValidationException when a kind being turned off still has items
     */
    public function handle(CustomReview $review, array $kinds): CustomReview
    {
        return DB::transaction(function () use ($review, $kinds): CustomReview {
            /** @var CustomReview $locked */
            $locked = CustomReview::query()->lockForUpdate()->findOrFail($review->id);

            $held = $this->items->countsByKind($locked);

            foreach (FeedbackType::customReviewTypes() as $kind) {
                if (in_array($kind, $kinds, true) || ! $locked->allowsKind($kind)) {
                    continue;
                }

                $count = $held[$kind->value] ?? 0;

                if ($count > 0) {
                    throw ValidationException::withMessages([
                        'kinds' => CustomReviewKinds::narrowingMessage($kind, $count),
                    ]);
                }
            }

            $review->allowed_kinds = CustomReviewKinds::storedChoice($kinds);

            if ($review->isDirty()) {
                $review->save();
            }

            return $review->refresh();
        });
    }
}
