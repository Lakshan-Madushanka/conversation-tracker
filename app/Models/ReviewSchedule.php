<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The one stored schedule per item: the day it is next worth revisiting.
 *
 * This row is a stored copy of what the ratings already say — the rebuild
 * rule in `App\Support\ReviewSchedule` is the source of truth, and every
 * change to the ratings rewrites this row in the same transaction. Items the
 * learner has never rated have no row and are due right away.
 *
 * @property int $id
 * @property FeedbackType $kind
 * @property string $item_identity
 * @property CarbonImmutable $due_on
 * @property CarbonImmutable $last_rated_on
 * @property ReviewRating $last_rating
 * @property int $rated_count
 * @property int $easy_run
 */
#[Fillable(['kind', 'item_identity', 'due_on', 'last_rated_on', 'last_rating', 'rated_count', 'easy_run'])]
final class ReviewSchedule extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => FeedbackType::class,
            'last_rating' => ReviewRating::class,
            'due_on' => 'immutable_date',
            'last_rated_on' => 'immutable_date',
            'rated_count' => 'integer',
            'easy_run' => 'integer',
        ];
    }
}
