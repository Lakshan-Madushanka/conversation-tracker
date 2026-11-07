<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomReviewAttemptStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CustomReviewAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One attempt the learner made at a custom review.
 *
 * The attempt keeps its own list of snapshotted items and its own ratings.
 * Nothing here is read or written by Review, Collections or any schedule, so
 * an attempt can never change an item or another page.
 *
 * `number` counts the attempts actually started, so an In progress attempt is
 * always the latest one. A Not started shell keeps no number.
 *
 * @property int $id
 * @property int $custom_review_id
 * @property int|null $number
 * @property CustomReviewAttemptStatus $status
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['custom_review_id', 'number', 'status', 'started_at', 'finished_at'])]
final class CustomReviewAttempt extends Model
{
    /** @use HasFactory<CustomReviewAttemptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomReviewAttemptStatus::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<CustomReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(CustomReview::class, 'custom_review_id');
    }

    /**
     * The items in the order the learner met them.
     *
     * @return HasMany<CustomReviewAttemptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CustomReviewAttemptItem::class)->orderBy('position');
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function isInProgress(): bool
    {
        return $this->status === CustomReviewAttemptStatus::InProgress;
    }

    public function isNotStarted(): bool
    {
        return $this->status === CustomReviewAttemptStatus::NotStarted;
    }
}
