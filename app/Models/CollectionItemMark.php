<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The learner's own marks for one collection item: a star, a status and
 * personal notes.
 *
 * One row per item, keyed by the item's identity in `kind` and `item_key`,
 * never by a session, so marks stay the same wherever the item appears and
 * follow it when its text is edited. They are kept when the item disappears
 * and return with it. Nothing but the learner ever writes the star, status or
 * notes.
 *
 * @property int $id
 * @property FeedbackType $kind
 * @property string $item_key
 * @property bool $starred
 * @property CollectionItemStatus|null $status
 * @property string $notes_html
 * @property string $notes_text
 * @property int $notes_version
 * @property Carbon|null $last_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'kind',
    'item_key',
    'starred',
    'status',
    'notes_html',
    'notes_text',
    'notes_version',
    'last_changed_at',
])]
final class CollectionItemMark extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => FeedbackType::class,
            'starred' => 'boolean',
            'status' => CollectionItemStatus::class,
            'notes_version' => 'integer',
            'last_changed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Derive the storage key from the shared identity, in one place, so marks,
     * grouping and a later review can never disagree. The shared pieces (
     * `SearchText` for words and phrases, `MistakeIdentity` for mistakes, the
     * saved note row for grammar notes) name the item; this shortens it to a
     * fixed-length, opaque key so the item key column and its unique index
     * stay small whatever the learner wrote.
     */
    public static function keyFor(FeedbackType $kind, string $itemKey): string
    {
        return 'mark-'.substr(sha1($kind->value."\u{0}".$itemKey), 0, 32);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function forItem(Builder $query, FeedbackType $kind, string $itemKey): Builder
    {
        return $query
            ->where('kind', $kind->value)
            ->where('item_key', self::keyFor($kind, $itemKey));
    }

    /**
     * Whether a note is saved: blank space or empty formatting counts as no
     * note, and `notes_html` is stored empty for these.
     */
    public function hasNotes(): bool
    {
        return trim((string) $this->notes_text) !== '';
    }

    /**
     * Keep `last_changed_at` truthful whenever the learner's marks change.
     */
    public function markChanged(): void
    {
        $this->last_changed_at = Carbon::now();
    }
}
