<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One item inside a review, named by the identity Collections groups by.
 *
 * Only the identity and the order are stored. The word or phrase, its meaning
 * and its example are read fresh from the finished session each time the card
 * is shown, so an edit shows straight away and a removed item is skipped.
 *
 * @property int $id
 * @property int $review_id
 * @property int $position
 * @property FeedbackType $kind
 * @property string $item_identity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['review_id', 'position', 'kind', 'item_identity'])]
final class ReviewItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FeedbackType::class,
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
