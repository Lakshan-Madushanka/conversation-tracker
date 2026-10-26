<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\FeedbackTable;
use App\Support\SearchText;
use Database\Factories\PracticeFeedbackEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * One entry inside a feedback box, kept as formatted text (for grammar notes
 * and the natural version) or plain text (for the table boxes) plus a
 * plain-text version for search and review. A table box holds many rows, one
 * per pasted line, ordered by `position` and identified by `client_id`.
 *
 * A table box (mistakes, vocabulary and phrases) holds three columns per row.
 * Mistakes keep what was said (`content_text`), a correction
 * (`correction_html` plus its plain-text copy) and an optional why
 * (`reason`). Vocabulary and phrases keep the subject (`content_text`), its
 * meaning (`correction_text`) and an optional example (`example_html` plus
 * its plain-text copy in `example`). An example saved before the list existed
 * is one piece of markup; an example saved since is a list of entries, each
 * with the id the editor gave it.
 *
 * @property int $id
 * @property int $practice_question_id
 * @property string $type
 * @property int $position
 * @property string|null $client_id
 * @property string $content_html
 * @property string $content_text
 * @property string $correction_html
 * @property string $correction_text
 * @property string $reason
 * @property string $example
 * @property string|list<array{id: string, text: string, html: string}> $example_html
 * @property bool $is_split
 * @property string|null $search_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'practice_question_id',
    'type',
    'position',
    'client_id',
    'content_html',
    'content_text',
    'correction_html',
    'correction_text',
    'reason',
    'example',
    'example_html',
    'is_split',
])]
class PracticeFeedbackEntry extends Model
{
    /** @use HasFactory<PracticeFeedbackEntryFactory> */
    use HasFactory;

    /**
     * Keep the searchable copy in step with the words, so an edited row stops
     * matching its old wording and a new one is found straight away.
     */
    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            $entry->search_text = $entry->searchableText();
        });
    }

    public const TYPE_MISTAKE = 'mistake';

    public const TYPE_VOCABULARY = 'vocabulary';

    public const TYPE_PHRASE = 'phrase';

    public const TYPE_GRAMMAR_NOTE = 'grammar_note';

    public const TYPE_NATURAL_VERSION = 'natural_version';

    /**
     * Boxes shown as a table of mistakes, each with a correction and an
     * optional why.
     *
     * @var list<string>
     */
    public const MISTAKE_TYPES = [
        self::TYPE_MISTAKE,
    ];

    /**
     * Boxes shown as a table: a fixed set of columns, one row per pasted line.
     *
     * @var list<string>
     */
    public const TABLE_TYPES = [
        self::TYPE_MISTAKE,
        self::TYPE_VOCABULARY,
        self::TYPE_PHRASE,
    ];

    /**
     * Boxes that hold many entries under one question. Only the natural
     * version stays a single piece of text.
     *
     * @var list<string>
     */
    public const ENTRY_TYPES = [
        ...self::TABLE_TYPES,
        self::TYPE_GRAMMAR_NOTE,
    ];

    /**
     * Every feedback box a question can have, in the order they are shown.
     *
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_MISTAKE,
        self::TYPE_VOCABULARY,
        self::TYPE_PHRASE,
        self::TYPE_GRAMMAR_NOTE,
        self::TYPE_NATURAL_VERSION,
    ];

    /**
     * Types edited as rich text; everything else is plain text.
     *
     * @var list<string>
     */
    public const RICH_TYPES = [
        self::TYPE_GRAMMAR_NOTE,
        self::TYPE_NATURAL_VERSION,
    ];

    public static function isRich(string $type): bool
    {
        return in_array($type, self::RICH_TYPES, true);
    }

    /** Whether a box holds a table of rows, one column set per box. */
    public static function isTable(string $type): bool
    {
        return in_array($type, self::TABLE_TYPES, true);
    }

    /** Whether a box holds mistakes, each with a correction and a why. */
    public static function isMistake(string $type): bool
    {
        return in_array($type, self::MISTAKE_TYPES, true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_split' => 'boolean',
            'example_html' => 'array',
        ];
    }

    /**
     * One page of entries of a single type, newest first, optionally narrowed
     * to a search term.
     *
     * Only finished sessions feed a collection, so a draft the learner is
     * still working on never shows up half-written. An entry that holds
     * nothing at all is left out too, so a stray blank row can never render
     * an empty card or inflate a tab's total.
     *
     * The term is matched literally against the word or phrase, what it means
     * and its example: LIKE wildcards the learner typed (`%` and `_`) are
     * escaped, so searching for `100%` finds `100% sure` and nothing else. An
     * escape character of `!` keeps the query portable — sqlite has no default
     * escape character, and MySQL's backslash would have to be escaped
     * differently in each dialect.
     *
     * @return LengthAwarePaginator<int, PracticeFeedbackEntry>
     */
    public static function collection(string $type, ?string $search): LengthAwarePaginator
    {
        return self::query()
            ->with([
                'practiceQuestion:id,practice_session_id',
                'practiceQuestion.practiceSession:id,topic_id',
                'practiceQuestion.practiceSession.topic:id,name',
            ])
            ->when($search, function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query
                        ->whereRaw("content_text LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("content_html LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("correction_text LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("correction_html LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("example LIKE ? ESCAPE '!'", [$like]);
                });
            })
            ->whereHas('practiceQuestion.practiceSession', fn (Builder $query): Builder => $query->finished())
            ->where(fn (Builder $query): Builder => $query
                ->where('content_text', '!=', '')
                ->orWhere('content_html', '!=', ''))
            ->where('type', $type)
            ->latest()
            ->paginate(perPage: 4, pageName: $type);
    }

    /**
     * The content as it is edited: formatted markup for rich types, plain text
     * for the rest.
     */
    public function editableContent(): string
    {
        return self::isRich($this->type) ? $this->content_html : $this->content_text;
    }

    /**
     * Whether the stored entry holds nothing at all. Only non-empty entries are
     * counted towards a session's totals, so a stray blank row never inflates
     * the summary.
     */
    public function isBlank(): bool
    {
        if (self::isTable($this->type)) {
            return FeedbackTable::isBlank([
                'content_html' => $this->content_html,
                'content_text' => $this->content_text,
                'correction_html' => $this->correction_html,
                'correction_text' => $this->correction_text,
                'reason' => $this->reason,
                'example' => $this->example,
                'example_html' => $this->example_html,
            ]);
        }

        // Rich notes keep their words in the content columns only.
        return trim($this->content_text) === ''
            && trim(strip_tags($this->content_html)) === '';
    }

    /**
     * Whether a table row still needs the learner's attention, mirroring the
     * editor: a mistake is missing its correction, or a word is missing its
     * meaning. Phrases and rich notes never ask for attention.
     */
    public function needsAttention(): bool
    {
        if ($this->type === self::TYPE_MISTAKE) {
            return trim($this->correction_text) === ''
                && trim(strip_tags($this->correction_html)) === '';
        }

        if ($this->type === self::TYPE_VOCABULARY) {
            return trim($this->correction_text) === '';
        }

        return false;
    }

    /**
     * The plain text of this entry, split by the column it came from. A table
     * row's columns are separate fields; a rich note keeps all its words in one.
     *
     * @return array<string, string>
     */
    public function searchableFields(): array
    {
        if (! self::isTable($this->type)) {
            return ['content' => (string) $this->content_text];
        }

        $fields = [
            'content' => (string) $this->content_text,
            'correction' => (string) $this->correction_text,
            'reason' => (string) $this->reason,
        ];

        if (in_array($this->type, [self::TYPE_VOCABULARY, self::TYPE_PHRASE], true)) {
            $fields['example'] = $this->exampleText();
        }

        return array_filter(
            $fields,
            fn (string $value): bool => trim($value) !== '',
        );
    }

    /**
     * Everything this entry holds, normalised into the one column search reads.
     * A table row is one item, so all of its columns are searched together.
     */
    public function searchableText(): string
    {
        return SearchText::normalize(
            implode(' ', array_values($this->searchableFields())),
        );
    }

    /**
     * The field that best shows why this row matched, used for its snippet.
     * The field holding the most terms wins; the first one wins a tie.
     *
     * @param  list<string>  $terms
     * @return array{field: string, text: string}
     */
    public function bestSearchField(array $terms): array
    {
        $best = null;
        $bestScore = -1;

        foreach ($this->searchableFields() as $field => $text) {
            $normalized = SearchText::normalize($text);
            $score = 0;

            foreach ($terms as $term) {
                if (str_contains($normalized, $term)) {
                    $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = ['field' => $field, 'text' => $text];
            }

            if ($score === count($terms)) {
                break;
            }
        }

        return $best ?? ['field' => 'content', 'text' => (string) $this->content_text];
    }

    /** The words of a vocabulary or phrase example, whichever way it was saved. */
    private function exampleText(): string
    {
        $example = (string) $this->example;

        if (trim($example) !== '') {
            return $example;
        }

        // The column is read raw, because older rows can still hold a plain
        // string where newer ones hold a list of parts.
        $exampleHtml = $this->getAttribute('example_html');

        if (! is_array($exampleHtml)) {
            return '';
        }

        return implode(' ', array_map(
            fn (mixed $entry): string => is_array($entry) && isset($entry['text'])
                ? (string) $entry['text']
                : '',
            $exampleHtml,
        ));
    }

    /**
     * @return BelongsTo<PracticeQuestion, $this>
     */
    public function practiceQuestion(): BelongsTo
    {
        return $this->belongsTo(PracticeQuestion::class);
    }
}
