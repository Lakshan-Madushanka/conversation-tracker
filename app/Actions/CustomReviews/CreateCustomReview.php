<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Enums\CustomReviewStatus;
use App\Models\CustomReview;
use Illuminate\Support\Facades\DB;

/**
 * Starts a custom review, which is saved the moment it exists.
 *
 * The learner never has to name it or save it: it takes the next number no
 * review of theirs already carries, so deleting one cannot leave two reviews
 * with the same default name, and it starts `Empty` because nothing is in it.
 *
 * Naming is the app's only write here. No item, mark, rating or session is
 * read or touched.
 */
final class CreateCustomReview
{
    /** The name a review the learner has not named yet carries. */
    public const string NAME_PREFIX = 'Custom Review ';

    public function handle(): CustomReview
    {
        return DB::transaction(function (): CustomReview {
            $review = new CustomReview;
            $review->name = $this->nextName();
            $review->status = CustomReviewStatus::Empty;
            $review->save();

            return $review;
        });
    }

    /**
     * The default name with the first number none of the learner's reviews
     * already uses. Gaps left by a deleted review are filled, so a name is
     * never reused while another review carries it.
     */
    private function nextName(): string
    {
        /** @var array<int, true> $used */
        $used = [];

        foreach (CustomReview::query()->pluck('name') as $name) {
            if (preg_match('/^'.preg_quote(self::NAME_PREFIX, '/').'([1-9][0-9]*)$/', (string) $name, $matches) === 1) {
                $used[(int) $matches[1]] = true;
            }
        }

        for ($number = 1; ; $number++) {
            if (! isset($used[$number])) {
                return self::NAME_PREFIX.$number;
            }
        }
    }
}
