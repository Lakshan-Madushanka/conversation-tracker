<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NotebookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A notebook the learner keeps: one board of vocabulary, phrases and grammar
 * notes under a shared topic. It is not a session — nothing here finishes,
 * archives, or counts as a finished practice.
 *
 * @property int $id
 * @property string $name
 * @property int $topic_id
 * @property Topic $topic
 * @property Collection<int, PracticeFeedbackEntry> $entries
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'topic_id'])]
final class Notebook extends Model
{
    /** @use HasFactory<NotebookFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Topic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * The board's rows, grouped by box and kept in the order the learner
     * saved them, the same way a question's entries read.
     *
     * @return HasMany<PracticeFeedbackEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PracticeFeedbackEntry::class)
            ->orderBy('type')
            ->orderBy('position')
            ->orderBy('id');
    }
}
