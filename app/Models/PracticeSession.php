<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\PracticeSessions\SplitLegacyFeedbackEntries;
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
 * @property int $version
 * @property string|null $client_key
 * @property string|null $last_save_key
 * @property Topic $topic
 * @property Collection<int, PracticeQuestion> $questions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['topic_id', 'status', 'client_key'])]
class PracticeSession extends Model
{
    /** @use HasFactory<PracticeSessionFactory> */
    use HasFactory;

    public const STATUS_UNFINISHED = 'unfinished';

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
        ];
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
     * The session shaped for the session editor page.
     *
     * Every list box and the grammar notes arrive as a list of entries, each
     * with the id the editor saved it under, so a later save can update the
     * same entry instead of adding another. The natural version stays one
     * piece of formatted text.
     *
     * @return array{
     *     id: int,
     *     status: string,
     *     version: int,
     *     topic: array{id: int, name: string},
     *     questions: array<int, array{
     *         id: int,
     *         position: int,
     *         question_html: string,
     *         answer_html: string,
     *         feedback: array<string, list<array{id: string, text: string, correction_html: string, reason: string}|array{id: string, text: string, meaning: string, example_html: string|list<array{id: string, text: string, html: string}>}|array{id: string, html: string}>|string>
     *     }>
     * }
     */
    public function toEditorPayload(): array
    {
        // Sessions saved before entries existed are split once, on open, so
        // old content looks the same as new content in the editor.
        app(SplitLegacyFeedbackEntries::class)->handle($this);

        $this->load(['questions.feedbackEntries']);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'version' => $this->version,
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
                        ->groupBy('type')
                        ->map(fn (Collection $entries, string $type): mixed => $this->feedbackPayload($type, $entries))
                        ->all(),
                ])
                ->all(),
        ];
    }

    /**
     * One box shaped for the editor: table rows with their columns, one
     * formatted entry per grammar note, and the raw text for the natural
     * version.
     *
     * @param  Collection<int, PracticeFeedbackEntry>  $entries
     * @return list<array{id: string, text: string, correction_html: string, reason: string}|array{id: string, text: string, meaning: string, example_html: string|list<array{id: string, text: string, html: string}>}|array{id: string, html: string}>|string
     */
    private function feedbackPayload(string $type, Collection $entries): array|string
    {
        if ($type === PracticeFeedbackEntry::TYPE_NATURAL_VERSION) {
            return $entries->first()?->editableContent() ?? '';
        }

        $payload = [];

        foreach ($entries as $entry) {
            $id = $entry->client_id ?? '';

            if ($type === PracticeFeedbackEntry::TYPE_MISTAKE) {
                $payload[] = [
                    'id' => $id,
                    'text' => $entry->content_text,
                    'correction_html' => $entry->correction_html,
                    'reason' => $entry->reason,
                ];

                continue;
            }

            if (PracticeFeedbackEntry::isTable($type)) {
                $payload[] = [
                    'id' => $id,
                    'text' => $entry->content_text,
                    'meaning' => $entry->correction_text,
                    'example_html' => $entry->example_html,
                ];

                continue;
            }

            $payload[] = ['id' => $id, 'html' => $entry->content_html];
        }

        return $payload;
    }

    /**
     * The most recently touched unfinished session, shaped for the Home page's
     * "Continue where you left off" card. Returns null when nothing is waiting.
     *
     * @return array{id: int, topic: string, updated_at: string}|null
     */
    public static function continueCard(): ?array
    {
        $session = self::query()
            ->where('status', self::STATUS_UNFINISHED)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        return $session?->toContinueCardPayload();
    }

    /**
     * @return array{id: int, topic: string, updated_at: string}
     */
    public function toContinueCardPayload(): array
    {
        $this->loadMissing('topic');

        return [
            'id' => $this->id,
            'topic' => $this->topic->name,
            'updated_at' => $this->updated_at?->toIso8601String() ?? '',
        ];
    }
}
