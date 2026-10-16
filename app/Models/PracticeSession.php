<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PracticeSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One recorded practice conversation, stored unfinished until it is completed.
 *
 * @property int $id
 * @property int $topic_id
 * @property string $status
 * @property Topic $topic
 * @property Collection<int, PracticeQuestion> $questions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['topic_id', 'status'])]
class PracticeSession extends Model
{
    /** @use HasFactory<PracticeSessionFactory> */
    use HasFactory;

    public const STATUS_UNFINISHED = 'unfinished';

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
     * The session shaped for the session editor page.
     *
     * @return array{
     *     id: int,
     *     status: string,
     *     topic: array{id: int, name: string},
     *     questions: array<int, array{
     *         id: int,
     *         position: int,
     *         question_html: string,
     *         answer_html: string,
     *         feedback: array<string, string>
     *     }>
     * }
     */
    public function toEditorPayload(): array
    {
        $this->loadMissing(['topic', 'questions.feedbackEntries']);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'topic' => [
                'id' => $this->topic->id,
                'name' => $this->topic->name,
            ],
            'questions' => $this->questions
                ->map(fn (PracticeQuestion $question): array => [
                    'id' => $question->id,
                    'position' => $question->position,
                    'question_html' => $question->question_html,
                    'answer_html' => $question->answer_html,
                    'feedback' => $question->feedbackEntries
                        ->mapWithKeys(fn (PracticeFeedbackEntry $entry): array => [
                            $entry->type => $entry->editableContent(),
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }
}
