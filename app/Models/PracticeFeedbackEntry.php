<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PracticeFeedbackEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry inside a feedback box, kept as formatted text (for grammar notes
 * and the natural version) or plain text (for the list boxes) plus a
 * plain-text version for search and review. A list box holds many entries, one
 * row each, ordered by `position` and identified by `client_id`.
 *
 * A mistake carries three columns: what was said (`content_text`), the
 * correction (`correction_html` plus its plain-text copy) and an optional why
 * (`reason`).
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
 * @property bool $is_split
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
    'is_split',
])]
class PracticeFeedbackEntry extends Model
{
    /** @use HasFactory<PracticeFeedbackEntryFactory> */
    use HasFactory;

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
     * Boxes shown as a list of plain text entries, split from pasted lines.
     *
     * @var list<string>
     */
    public const LIST_TYPES = [
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
        ...self::MISTAKE_TYPES,
        ...self::LIST_TYPES,
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

    /** Whether a box holds plain text entries split from pasted lines. */
    public static function isList(string $type): bool
    {
        return in_array($type, self::LIST_TYPES, true);
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
        ];
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
     * @return BelongsTo<PracticeQuestion, $this>
     */
    public function practiceQuestion(): BelongsTo
    {
        return $this->belongsTo(PracticeQuestion::class);
    }
}
