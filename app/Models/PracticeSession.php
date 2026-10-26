<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackType;
use Carbon\CarbonImmutable;
use Database\Factories\PracticeSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One recorded practice conversation, stored unfinished until it is completed.
 *
 * @property int $id
 * @property int $topic_id
 * @property string $status
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $last_changed_at
 * @property CarbonImmutable|null $archived_at
 * @property int $version
 * @property string|null $client_key
 * @property string|null $last_save_key
 * @property Topic $topic
 * @property Collection<int, PracticeQuestion> $questions
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['topic_id', 'status', 'client_key'])]
class PracticeSession extends Model
{
    /** @use HasFactory<PracticeSessionFactory> */
    use HasFactory;

    public const STATUS_UNFINISHED = 'unfinished';

    public const STATUS_FINISHED = 'finished';

    /**
     * Mirror the database default so a newly created session already reports
     * version 1 to the editor, without a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'finished_at' => 'datetime',
            'last_changed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Sessions the learner can still see in the Library, on Home and in
     * search. Archived sessions are hidden here, once, so every list follows
     * the same rule.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Sessions the learner has put away in the Archived view.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function archived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Sessions included in progress totals, practice days, streaks and
     * exports. Archived sessions still count, because archiving never removes
     * anything from the learner's history.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function countable(Builder $query): Builder
    {
        return $query->finished();
    }

    /**
     * Sessions used as study material by Collections and Review. Only
     * finished sessions that are not archived feed them.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function study(Builder $query): Builder
    {
        return $query->finished()->visible();
    }

    /**
     * Sessions the learner has finished. Only these feed collections, review
     * and progress.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function finished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FINISHED);
    }

    /**
     * Sessions still being worked on, shown on the Home "Continue" card.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function unfinished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNFINISHED);
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return BelongsTo<Topic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * @return HasMany<PracticeQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(PracticeQuestion::class)->orderBy('position');
    }

    /**
     * The day the practice happened. Recorded sessions use the day they were
     * created; imported sessions will keep their original date here later.
     */
    public function sessionDate(): ?CarbonImmutable
    {
        return $this->created_at;
    }

    /**
     * When the learner last changed the content. Opening or finishing a
     * session does not count as an edit.
     */
    public function lastEditedAt(): ?CarbonImmutable
    {
        return $this->last_changed_at;
    }

    /**
     * How much a session holds, counted from its entries each time so the
     * numbers stay correct after later edits. Only non-empty entries and
     * questions count; entries flagged as duplicates still count, because they
     * are real entries.
     *
     * @return array{questions: int, words: int, phrases: int, mistakes: int, grammar_notes: int}
     */
    public function counts(): array
    {
        $this->loadMissing('questions.feedbackEntries');

        $counts = [
            'questions' => 0,
            'words' => 0,
            'phrases' => 0,
            'mistakes' => 0,
            'grammar_notes' => 0,
        ];

        foreach ($this->questions as $question) {
            if ($question->hasContent()) {
                $counts['questions'] += 1;
            }

            foreach ($question->feedbackEntries as $entry) {
                if ($entry->isBlank()) {
                    continue;
                }

                match ($entry->type) {
                    FeedbackType::Vocabulary => $counts['words'] += 1,
                    FeedbackType::Phrase => $counts['phrases'] += 1,
                    FeedbackType::Mistake => $counts['mistakes'] += 1,
                    FeedbackType::GrammarNote => $counts['grammar_notes'] += 1,
                    default => null,
                };
            }
        }

        return $counts;
    }

    /**
     * How many table rows still need a correction or a meaning, matching the
     * "Needs attention" hint in the editor.
     */
    public function needsAttention(): int
    {
        $this->loadMissing('questions.feedbackEntries');

        $count = 0;

        foreach ($this->questions as $question) {
            foreach ($question->feedbackEntries as $entry) {
                if (! $entry->isBlank() && $entry->needsAttention()) {
                    $count += 1;
                }
            }
        }

        return $count;
    }

    /**
     * Whether the session holds no questions, or only questions that are
     * completely empty. Such a session cannot be finished without a warning.
     */
    public function isEmpty(): bool
    {
        $this->loadMissing('questions.feedbackEntries');

        return ! $this->questions->contains(
            fn (PracticeQuestion $question): bool => $question->hasContent(),
        );
    }
}
