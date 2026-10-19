<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\PracticeSessions\SplitLegacyFeedbackEntries;
use Database\Factories\PracticeSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
 * @property Carbon|null $finished_at
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
        ];
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
     *     finished_at: string|null,
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
            'finished_at' => $this->finished_at?->toIso8601String(),
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
            ->unfinished()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        return $session?->toContinueCardPayload();
    }

    /**
     * Finished sessions for a list — the Home page's most recent ones and the
     * whole Library — newest change first. The limit keeps Home to its five
     * newest; the Library asks for all of them.
     *
     * @return list<array{id: int, topic: string, finished_at: string|null, counts: array{questions: int, words: int, phrases: int, mistakes: int, grammar_notes: int}}>
     */
    public static function finishedSessions(?int $limit = null): array
    {
        $query = self::query()
            ->finished()
            ->with(['topic', 'questions.feedbackEntries'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $sessions = $query->get();

        $payload = [];

        foreach ($sessions as $session) {
            $payload[] = [
                'id' => $session->id,
                'topic' => $session->topic->name,
                'finished_at' => $session->finished_at?->toIso8601String(),
                'counts' => $session->counts(),
            ];
        }

        return $payload;
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
                    PracticeFeedbackEntry::TYPE_VOCABULARY => $counts['words'] += 1,
                    PracticeFeedbackEntry::TYPE_PHRASE => $counts['phrases'] += 1,
                    PracticeFeedbackEntry::TYPE_MISTAKE => $counts['mistakes'] += 1,
                    PracticeFeedbackEntry::TYPE_GRAMMAR_NOTE => $counts['grammar_notes'] += 1,
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
