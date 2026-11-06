<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The learner's "Not now" to one status suggestion, kept for the run of
 * ratings it was said during.
 *
 * The run is named by the rating that began it, so a longer run of the same
 * ratings stays quiet while a new run that reaches the threshold is free to
 * suggest again. Nothing else is stored about suggestions: they are always
 * worked out from the ratings.
 *
 * @property int $id
 * @property FeedbackType $kind
 * @property string $item_identity
 * @property CollectionItemStatus $status
 * @property int $run_started_id
 */
#[Fillable(['kind', 'item_identity', 'status', 'run_started_id'])]
final class ReviewSuggestionDecline extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => FeedbackType::class,
            'status' => CollectionItemStatus::class,
            'run_started_id' => 'integer',
        ];
    }
}
