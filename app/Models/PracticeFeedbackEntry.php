<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackType;
use App\Support\EntrySearch;
use App\Support\FeedbackTable;
use Database\Factories\PracticeFeedbackEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property FeedbackType $type
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
            $entry->search_text = EntrySearch::text($entry);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FeedbackType::class,
            'is_split' => 'boolean',
            'example_html' => 'array',
        ];
    }

    /**
     * The content as it is edited: formatted markup for rich types, plain text
     * for the rest.
     */
    public function editableContent(): string
    {
        return $this->type->isRich() ? $this->content_html : $this->content_text;
    }

    /**
     * Whether the stored entry holds nothing at all. Only non-empty entries are
     * counted towards a session's totals, so a stray blank row never inflates
     * the summary.
     */
    public function isBlank(): bool
    {
        if ($this->type->isTable()) {
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
        if ($this->type === FeedbackType::Mistake) {
            return trim($this->correction_text) === ''
                && trim(strip_tags($this->correction_html)) === '';
        }

        if ($this->type === FeedbackType::Vocabulary) {
            return trim($this->correction_text) === '';
        }

        return false;
    }

    /**
     * @return BelongsTo<PracticeQuestion, $this>
     */
    public function practiceQuestion(): BelongsTo
    {
        return $this->belongsTo(PracticeQuestion::class);
    }
}
