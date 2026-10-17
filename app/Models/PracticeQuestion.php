<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PracticeQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One tutor question with the learner's answer, kept as formatted text plus a
 * plain-text version for search and review.
 *
 * @property int $id
 * @property int $practice_session_id
 * @property int $position
 * @property string $question_html
 * @property string $question_text
 * @property string $answer_html
 * @property string $answer_text
 * @property Collection<int, PracticeFeedbackEntry> $feedbackEntries
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'practice_session_id',
    'position',
    'question_html',
    'question_text',
    'answer_html',
    'answer_text',
])]
class PracticeQuestion extends Model
{
    /** @use HasFactory<PracticeQuestionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PracticeSession, $this>
     */
    public function practiceSession(): BelongsTo
    {
        return $this->belongsTo(PracticeSession::class);
    }

    /**
     * Every entry of the question, grouped by box in the order the boxes are
     * shown, and inside each box in the order the learner pasted or added them.
     *
     * @return HasMany<PracticeFeedbackEntry, $this>
     */
    public function feedbackEntries(): HasMany
    {
        return $this->hasMany(PracticeFeedbackEntry::class)
            ->orderBy('type')
            ->orderBy('position')
            ->orderBy('id');
    }
}
