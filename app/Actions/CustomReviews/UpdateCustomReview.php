<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Models\CustomReview;
use Illuminate\Support\Facades\DB;

/**
 * Changes a review's name or description, leaving anything not sent alone.
 *
 * The page saves each field on its own with autosave, so the same action may
 * receive one field or both. The request has already trimmed the name and
 * refused a blank one; this only writes what it is given.
 */
final class UpdateCustomReview
{
    /**
     * @param  array{name?: string, description?: string|null}  $changes
     */
    public function handle(CustomReview $review, array $changes): CustomReview
    {
        return DB::transaction(function () use ($review, $changes): CustomReview {
            if (array_key_exists('name', $changes)) {
                $review->name = $changes['name'];
            }

            if (array_key_exists('description', $changes)) {
                $review->description = $changes['description'];
            }

            if ($review->isDirty()) {
                $review->save();
            }

            return $review->refresh();
        });
    }
}
