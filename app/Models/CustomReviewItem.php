<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackType;
use Database\Factories\CustomReviewItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One item a custom review holds.
 *
 * Only the item's kind and identity are kept — the same identity the
 * Collections and Review use — never a copy of its words, so an edited item
 * shows its current text wherever it appears. One row per review and item, so
 * a repeated Add, a retry or two tabs cannot gather the same item twice.
 *
 * @property int $id
 * @property int $custom_review_id
 * @property FeedbackType $kind
 * @property string $item_identity
 * @property Carbon $added_at
 */
#[Fillable(['custom_review_id', 'kind', 'item_identity', 'added_at'])]
#[WithoutTimestamps]
final class CustomReviewItem extends Model
{
    /** @use HasFactory<CustomReviewItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FeedbackType::class,
            'added_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<CustomReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(CustomReview::class, 'custom_review_id');
    }
}
