<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\PracticeSessions\SplitLegacyFeedbackEntries;
use App\Support\LibraryFilters;
use Carbon\CarbonImmutable;
use Database\Factories\PracticeSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

/**
 * One recorded practice conversation, stored unfinished until it is completed.
 *
 * @property int $id
 * @property int $topic_id
 * @property string $status
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $last_changed_at
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

    /** How many sessions the Library loads at a time. */
    public const LIBRARY_PAGE_SIZE = 20;

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
            'last_changed_at' => $this->last_changed_at?->toIso8601String(),
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
     * The sessions that belong in the Library and in the Home lists, narrowed
     * by the learner's filters when they have chosen any.
     *
     * Archiving arrives in Step 4. When it does, archived sessions are
     * excluded here, once, so every list follows the same rule.
     *
     * @return Builder<self>
     */
    public static function libraryQuery(?LibraryFilters $filters = null): Builder
    {
        $query = self::query();

        return $filters instanceof LibraryFilters
            ? self::applyFilters($query, $filters)
            : $query;
    }

    /**
     * One page of the Library, in the chosen order. Sessions created on the
     * same day fall back to the newest row first, and the id keeps the order
     * stable when two rows share a moment.
     *
     * The cursor continues from the last session shown, not from a page
     * number, so a session created while the list is open can never make a
     * later page repeat or skip one.
     *
     * @return array{
     *     sessions: list<array{id: int, topic: string, session_date: string|null, last_edited_at: string|null, status: string, counts: array{questions: int, words: int, phrases: int, mistakes: int, grammar_notes: int}}>,
     *     next_cursor: string|null,
     *     has_more: bool,
     *     total: int,
     *     all_total: int
     * }
     */
    public static function libraryPage(
        ?LibraryFilters $filters = null,
        ?string $cursor = null,
        int $perPage = self::LIBRARY_PAGE_SIZE,
    ): array {
        $query = self::libraryQuery($filters)
            ->with(['topic', 'questions.feedbackEntries']);

        self::applySort($query, $filters?->sort() ?? LibraryFilters::SORT_NEWEST);

        /** @var CursorPaginator<int, self> $paginator */
        $paginator = $query->cursorPaginate(perPage: $perPage, cursor: $cursor);

        return [
            'sessions' => array_values(array_map(
                fn (self $session): array => $session->toLibraryPayload(),
                $paginator->items(),
            )),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
            'total' => self::libraryTotal($filters),
            'all_total' => self::libraryTotal(),
        ];
    }

    /**
     * How many sessions the Library lists, both after filtering and in total.
     * A filtered list counts the sessions that match every chosen filter.
     */
    public static function libraryTotal(?LibraryFilters $filters = null): int
    {
        return self::libraryQuery($filters)->count();
    }

    /**
     * The topics used by at least one session in the Library, ordered by name.
     * A topic no session uses any more simply stops being offered.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function libraryTopics(): array
    {
        $topics = self::libraryQuery()
            ->join('topics', 'topics.id', '=', 'practice_sessions.topic_id')
            ->distinct()
            ->orderBy('topics.name')
            ->pluck('topics.name', 'topics.id');

        $options = [];

        foreach ($topics as $id => $name) {
            $options[] = ['id' => (int) $id, 'name' => (string) $name];
        }

        return $options;
    }

    /**
     * Apply every chosen kind of filter. Topic and content type values match
     * any of the chosen options; the kinds themselves must all match.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    private static function applyFilters(Builder $query, LibraryFilters $filters): Builder
    {
        if ($filters->topics() !== []) {
            $query->whereIn('topic_id', $filters->topics());
        }

        if ($filters->status() === self::STATUS_FINISHED) {
            $query->finished();
        } elseif ($filters->status() === self::STATUS_UNFINISHED) {
            $query->unfinished();
        }

        $range = $filters->dateRange();

        if ($range !== null) {
            [$start, $end] = $range;

            if ($start !== null && $end !== null) {
                $query->whereBetween('created_at', [$start, $end]);
            } elseif ($start !== null) {
                $query->where('created_at', '>=', $start);
            } elseif ($end !== null) {
                $query->where('created_at', '<=', $end);
            }
        }

        if ($filters->types() !== []) {
            $types = $filters->types();

            $query->whereHas('questions.feedbackEntries', function (Builder $entries) use ($types): void {
                $entries
                    ->whereIn('type', $types)
                    ->where(function (Builder $nonEmpty): void {
                        $nonEmpty
                            ->where(function (Builder $rich): void {
                                $rich
                                    ->whereIn('type', PracticeFeedbackEntry::RICH_TYPES)
                                    ->whereRaw("TRIM(content_text) <> ''");
                            })
                            ->orWhere(function (Builder $table): void {
                                $table
                                    ->whereIn('type', PracticeFeedbackEntry::TABLE_TYPES)
                                    ->where(function (Builder $columns): void {
                                        $columns
                                            ->whereRaw("TRIM(content_text) <> ''")
                                            ->orWhereRaw("TRIM(correction_text) <> ''")
                                            ->orWhereRaw("TRIM(reason) <> ''")
                                            ->orWhereRaw("TRIM(example) <> ''")
                                            ->orWhereRaw("TRIM(content_html) <> ''")
                                            ->orWhereRaw("TRIM(correction_html) <> ''");
                                    });
                            });
                    });
            });
        }

        return $query;
    }

    /**
     * @param  Builder<self>  $query
     */
    private static function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            LibraryFilters::SORT_OLDEST => $query
                ->orderBy('created_at')
                ->orderBy('id'),
            LibraryFilters::SORT_RECENTLY_EDITED => $query
                ->orderByDesc('last_changed_at')
                ->orderByDesc('id'),
            default => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
        };
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
     * The most recently touched unfinished session, shaped for the Home page's
     * "Continue where you left off" card. Returns null when nothing is waiting.
     *
     * @return array{id: int, topic: string, updated_at: string}|null
     */
    public static function continueCard(): ?array
    {
        $session = self::libraryQuery()
            ->unfinished()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        return $session?->toContinueCardPayload();
    }

    /**
     * Finished sessions for the Home page's "Recent sessions" list, newest
     * change first. They use the same card shape as the Library so both lists
     * look and behave the same.
     *
     * @return list<array{id: int, topic: string, session_date: string|null, last_edited_at: string|null, status: string, counts: array{questions: int, words: int, phrases: int, mistakes: int, grammar_notes: int}}>
     */
    public static function finishedSessions(?int $limit = null): array
    {
        $query = self::libraryQuery()
            ->finished()
            ->with(['topic', 'questions.feedbackEntries'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return array_values(
            $query
                ->get()
                ->map(fn (self $session): array => $session->toLibraryPayload())
                ->all(),
        );
    }

    /**
     * A session as a card in the Library or on Home.
     *
     * @return array{id: int, topic: string, session_date: string|null, last_edited_at: string|null, status: string, counts: array{questions: int, words: int, phrases: int, mistakes: int, grammar_notes: int}}
     */
    public function toLibraryPayload(): array
    {
        $this->loadMissing(['topic', 'questions.feedbackEntries']);

        return [
            'id' => $this->id,
            'topic' => $this->topic->name,
            'session_date' => $this->sessionDate()?->toIso8601String(),
            'last_edited_at' => $this->lastEditedAt()?->toIso8601String(),
            'status' => $this->status,
            'counts' => $this->counts(),
        ];
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
