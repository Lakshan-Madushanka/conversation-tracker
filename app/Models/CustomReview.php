<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Support\SearchText;
use Database\Factories\CustomReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A practice set the learner built themselves and keeps.
 *
 * This is the saved set, not a sitting: `Review` is a sitting the learner works
 * through, and a review here holds only references to items. Its status tells
 * the learner what is left to do — nothing in it yet, still being built, or
 * ready to practise — and nothing else in the app reads it.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property list<string>|null $allowed_kinds
 * @property string|null $search_text
 * @property string|null $creation_key
 * @property CustomReviewStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'allowed_kinds', 'status'])]
final class CustomReview extends Model
{
    /** @use HasFactory<CustomReviewFactory> */
    use HasFactory;

    /**
     * Keep the searchable copy in step with the name and description, so a
     * renamed review stops matching its old words straight away and a search
     * never has to read the two columns itself.
     */
    protected static function booted(): void
    {
        self::saving(function (self $review): void {
            $review->search_text = SearchText::normalize(
                trim($review->name.' '.($review->description)),
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomReviewStatus::class,
            'allowed_kinds' => 'array',
        ];
    }

    /**
     * Whether the learner has let this review hold items of the kind.
     *
     * An empty choice allows every kind, which is how a review starts and how
     * an unchanged one reads; only a narrowed review carries a list.
     */
    public function allowsKind(FeedbackType $kind): bool
    {
        $allowed = $this->allowed_kinds;

        return $allowed === null
            || $allowed === []
            || in_array($kind->value, $allowed, true);
    }

    /**
     * The items gathered so far, newest first, as the review's page lists them.
     *
     * @return HasMany<CustomReviewItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CustomReviewItem::class)->orderByDesc('added_at')->orderByDesc('id');
    }

    /**
     * Every attempt at this review, the most recent first.
     *
     * @return HasMany<CustomReviewAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(CustomReviewAttempt::class)
            ->orderByDesc('started_at')
            ->orderByDesc('id');
    }

    /** Whether the learner is still building this review. */
    public function isBeingBuilt(): bool
    {
        return $this->status->isBeingBuilt();
    }

    /**
     * The reviews the learner is still building, which "Add to review" offers.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function beingBuilt(Builder $query): Builder
    {
        return $query->whereIn('status', CustomReviewStatus::beingBuiltValues());
    }

    /**
     * The newest first, which is the order the picker lists and defaults to.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function newestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
