<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\SearchText;
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
 * @property string|null $question_search_text
 * @property string $answer_html
 * @property string $answer_text
 * @property string|null $answer_search_text
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
     * Keep the searchable copy in step with the words, so a saved question is
     * found straight away and an edited one stops matching its old wording.
     */
    protected static function booted(): void
    {
        static::saving(function (self $question): void {
            $question->question_search_text = SearchText::normalize($question->question_text);
            $question->answer_search_text = SearchText::normalize($question->answer_text);
        });
    }

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

    /**
     * Whether the question holds anything at all: a tutor's question, an
     * answer, or any non-empty feedback entry. Mirrors the editor's
     * `questionHasContent`, so a card the learner never filled in is treated
     * as empty on both sides.
     */
    public function hasContent(): bool
    {
        if (trim($this->question_text) !== '' || trim($this->answer_text) !== '') {
            return true;
        }

        return $this->feedbackEntries->contains(
            fn (PracticeFeedbackEntry $entry): bool => ! $entry->isBlank(),
        );
    }
}
