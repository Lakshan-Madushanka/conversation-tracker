<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackType;
use Database\Factories\BasketItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One item the learner is gathering for a custom review.
 *
 * Only the item's kind and identity are kept — the same identity the
 * Collections and Review use — never a copy of its words. One row per item,
 * so a repeated Add, a retry or two tabs cannot add the same item twice.
 *
 * The basket is not a review: it has no name, limits, attempts, statuses or
 * ratings, and nothing here is read by any other part of the app.
 *
 * @property int $id
 * @property FeedbackType $kind
 * @property string $item_identity
 * @property Carbon $added_at
 */
#[Fillable(['kind', 'item_identity', 'added_at'])]
#[WithoutTimestamps]
final class BasketItem extends Model
{
    /** @use HasFactory<BasketItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => FeedbackType::class,
            'added_at' => 'immutable_datetime',
        ];
    }
}
