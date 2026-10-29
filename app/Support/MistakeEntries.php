<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;

/**
 * The mistakes box's view of the shared table rules: named access to how a
 * pasted line splits into what was said and the correction, and why a row
 * still needs the learner's attention.
 *
 * The rules themselves live in `FeedbackTable`, so the mistakes and vocabulary
 * boxes always split a paste the same way. This mirrors
 * `resources/js/lib/mistakes.ts`.
 */
final class MistakeEntries
{
    /** The most rows one mistakes box can hold. */
    public const int MAX_ROWS = FeedbackTable::MAX_ROWS;

    /** The longest single cell, in characters. */
    public const int MAX_CELL_LENGTH = FeedbackTable::MAX_CELL_LENGTH;

    /** What the column says when a correction is missing. */
    public const string ATTENTION_ADD_CORRECTION = RowAttention::ADD_CORRECTION;

    /** What the column says when the line held more than one separator. */
    public const string ATTENTION_MORE_THAN_ONE = RowAttention::MORE_THAN_ONE;

    /**
     * Split one pasted line into its parts: what was said, the correction and
     * an empty why.
     *
     * @return array{text: string, correction_html: string, correction_text: string, reason: string}
     */
    public static function parseLine(string $line): array
    {
        [$text, $correction] = FeedbackTable::splitLine($line);

        return [
            'text' => $text,
            'correction_html' => self::escape($correction),
            'correction_text' => $correction,
            'reason' => '',
        ];
    }

    /** How many separators a cleaned line holds. */
    public static function countSeparators(string $line): int
    {
        return FeedbackTable::countSeparators($line);
    }

    /**
     * Why a row needs the learner's attention, worked out from its content
     * rather than stored. A row with a correction is fine. The rule itself
     * lives in `RowAttention`, shared with the vocabulary and phrase rows.
     */
    public static function attention(string $text, string $correctionText): ?string
    {
        return RowAttention::reason(FeedbackType::Mistake, $text, $correctionText);
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
