<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;
use Illuminate\Support\Arr;

/**
 * The rules shared by the table feedback boxes (mistakes, vocabulary and
 * phrases): how a pasted line splits into its columns, how a row maps onto the
 * stored columns, and how the columns are configured per box.
 *
 * This mirrors `resources/js/lib/feedback-table.ts`, so the browser and the
 * server always agree on what a paste becomes. The server applies the same
 * rules on save, so what is stored always matches what the learner saw.
 */
final class FeedbackTable
{
    /** The most rows one table box can hold. */
    public const int MAX_ROWS = 200;

    /** The longest single cell, in characters. */
    public const int MAX_CELL_LENGTH = 1000;

    /**
     * The only column that holds a list of entries: a vocabulary or phrase
     * row's example. Every other column holds one line of text or markup.
     */
    public const string EXAMPLE_COLUMN = 'example_html';

    /**
     * A separator inside a cleaned line. Arrows count wherever they appear; a
     * dash (single, double, en or em) counts only with a space on both sides,
     * so hyphenated words such as "3-day" stay whole.
     */
    private const string SEPARATOR = '/(?:→|->|=>|(?<=\s)-{1,2}(?=\s)|(?<=\s)[–—](?=\s))/u';

    /**
     * The columns of a box, in order. The first column is the row's subject,
     * the second is the one whose emptiness asks for attention, and the last
     * absorbs any extra pasted cells. The columns map onto the stored columns
     * by position: subject to the content columns, the attention column to the
     * correction columns, and the last to a mistake's why or a vocabulary or
     * phrase row's example.
     *
     * @return list<array{key: string, formatted: bool}>
     */
    public static function columns(FeedbackType $type): array
    {
        if ($type === FeedbackType::Mistake) {
            return [
                ['key' => 'text', 'formatted' => false],
                ['key' => 'correction_html', 'formatted' => true],
                ['key' => 'reason', 'formatted' => false],
            ];
        }

        // Vocabulary and phrases share the subject, meaning and example shape.
        return [
            ['key' => 'text', 'formatted' => false],
            ['key' => 'meaning', 'formatted' => false],
            ['key' => self::EXAMPLE_COLUMN, 'formatted' => true],
        ];
    }

    /**
     * The payload key of the column whose emptiness asks for attention: the
     * correction of a mistake, the meaning of a vocabulary row. A phrase row's
     * empty meaning is normal and never asks for attention.
     */
    public static function attentionKey(FeedbackType $type): string
    {
        return self::columns($type)[1]['key'];
    }

    /**
     * Split one cleaned line into its first two columns. Cleaning runs first,
     * so a leading bullet is a bullet, never a separator.
     *
     * - one separator: the text before it is the first column, the text after
     *   it is the second;
     * - no separator, or more than one: the whole cleaned line stays in the
     *   first column, so nothing is guessed at and nothing is lost.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitLine(string $line): array
    {
        $cleaned = ListEntries::cleanLine($line);
        $matches = self::separatorMatches($cleaned);

        if (count($matches) === 1) {
            $offset = $matches[0][1];
            $length = strlen($matches[0][0]);

            return [
                trim(substr($cleaned, 0, $offset)),
                trim(substr($cleaned, $offset + $length)),
            ];
        }

        return [$cleaned, ''];
    }

    /** How many separators a cleaned line holds. */
    public static function countSeparators(string $line): int
    {
        return count(self::separatorMatches(ListEntries::cleanLine($line)));
    }

    /**
     * Split one legacy plain-text line onto a row's stored columns. A formatted
     * column keeps its words escaped, since a plain text paste brings no
     * formatting.
     *
     * @return array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>}
     */
    public static function storedFromLine(FeedbackType $type, string $line): array
    {
        $parts = [...self::splitLine($line), ''];

        return self::mapStored($type, $parts);
    }

    /**
     * Map the columns the editor sends onto the stored columns. A formatted
     * column is reduced to a single inline line keeping only bold and italic.
     *
     * @param  array<string, mixed>  $cells
     * @return array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>}
     */
    public static function storedFromCells(FeedbackType $type, array $cells): array
    {
        [$first, $second, $third] = self::columns($type);

        $content = self::cleanColumn($first, $cells);
        $correction = self::cleanColumn($second, $cells);
        $last = self::cleanLastColumn($third, $cells);

        return self::withLastColumn($type, [
            'content_html' => $content['html'],
            'content_text' => $content['text'],
            'correction_html' => $correction['html'],
            'correction_text' => $correction['text'],
            'reason' => '',
            'example' => '',
            'example_html' => '',
        ], $last);
    }

    /**
     * Clean one column of the cells the editor sends. A formatted column keeps
     * its words and a single inline line of bold and italic; a plain column is
     * cleaned to one line. A value that is not one piece of text holds nothing
     * for this column, since only the example column holds anything else.
     *
     * @param  array{key: string, formatted: bool}  $column
     * @param  array<string, mixed>  $cells
     * @return array{text: string, html: string}
     */
    private static function cleanColumn(array $column, array $cells): array
    {
        $value = Arr::get($cells, $column['key'], '');

        if (! is_string($value)) {
            $value = '';
        }

        if ($column['formatted']) {
            $clean = RichText::cleanInlineWithText($value);

            return ['text' => $clean['text'], 'html' => $clean['html']];
        }

        return ['text' => ListEntries::cleanLine($value), 'html' => ''];
    }

    /**
     * Clean the last column of a row: a mistake's why, or a vocabulary or
     * phrase row's example. An example arrives as a list of entries when the
     * editor holds it as one, and as a single line of markup otherwise.
     *
     * @param  array{key: string, formatted: bool}  $column
     * @param  array<string, mixed>  $cells
     * @return array{text: string, html: string|list<array{id: string, text: string, html: string}>}
     */
    private static function cleanLastColumn(array $column, array $cells): array
    {
        $value = Arr::get($cells, $column['key'], '');

        if (is_array($value)) {
            // Only the example column holds a list of entries; every other
            // column holds one line, so a list here leaves it empty.
            if ($column['key'] !== self::EXAMPLE_COLUMN) {
                return ['text' => '', 'html' => ''];
            }

            $entries = self::cleanEntries($value);

            // Keep the words in the plain column too, so search and the
            // collection views can find an example saved as a list.
            return [
                'text' => implode(' ', array_column($entries, 'text')),
                'html' => $entries,
            ];
        }

        return self::cleanColumn($column, $cells);
    }

    /**
     * The entries the editor holds in the example column, cleaned one by one:
     * each entry keeps bold and italic around its words. An entry with no id
     * to save it under is dropped, since a later edit could never find it.
     *
     * @param  array<mixed>  $value
     * @return list<array{id: string, text: string, html: string}>
     */
    private static function cleanEntries(array $value): array
    {
        $data = [];

        foreach ($value as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            $text = is_array($item) ? ($item['text'] ?? null) : null;

            if (! is_string($id) || ! $id || ! is_string($text) || ! $text) {
                continue;
            }

            $clean = RichText::cleanInlineWithText($text);
            $data[] = ['id' => $id, 'text' => $clean['text'], 'html' => $clean['html']];
        }

        return $data;
    }

    /**
     * Whether a row built from the stored columns holds nothing at all.
     *
     * @param  array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>}  $stored
     */
    public static function isBlank(array $stored): bool
    {
        foreach ($stored as $value) {
            if (! blank($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Write one value per column into the stored shape, in order.
     *
     * @param  list<string>  $parts
     * @return array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>}
     */
    private static function mapStored(FeedbackType $type, array $parts): array
    {
        [$first, $second, $third] = self::columns($type);

        $content = self::mapColumn($first, $parts[0] ?? '');
        $correction = self::mapColumn($second, $parts[1] ?? '');
        $last = self::mapColumn($third, $parts[2] ?? '');

        return self::withLastColumn($type, [
            'content_html' => $content['html'],
            'content_text' => $content['text'],
            'correction_html' => $correction['html'],
            'correction_text' => $correction['text'],
            'reason' => '',
            'example' => '',
            'example_html' => '',
        ], $last);
    }

    /**
     * Store a box's last column: a mistake's optional why, or a vocabulary or
     * phrase row's optional example. The other pair stays empty, so no type
     * ever writes its content into another type's columns.
     *
     * @param  array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>}  $stored
     * @param  array{text: string, html: string|list<array{id: string, text: string, html: string}>}  $last
     * @return array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>}
     */
    private static function withLastColumn(FeedbackType $type, array $stored, array $last): array
    {
        if ($type === FeedbackType::Mistake) {
            $stored['reason'] = $last['text'];

            return $stored;
        }

        $stored['example'] = $last['text'];
        $stored['example_html'] = $last['html'];

        return $stored;
    }

    /**
     * Map one legacy plain-text part onto a column. A formatted column keeps
     * its words escaped, since a plain text paste brings no formatting.
     *
     * @param  array{key: string, formatted: bool}  $column
     * @return array{text: string, html: string}
     */
    private static function mapColumn(array $column, string $value): array
    {
        return $column['formatted']
            ? ['text' => $value, 'html' => self::escape($value)]
            : ['text' => $value, 'html' => ''];
    }

    /**
     * Every separator in a line, with its byte offset, so a match can be cut
     * out of the line with substr and stay correct on UTF-8 text.
     *
     * @return list<array{0: string, 1: int}>
     */
    private static function separatorMatches(string $line): array
    {
        $found = [];

        if (preg_match_all(self::SEPARATOR, $line, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[0] as $match) {
            $found[] = [$match[0], $match[1]];
        }

        return $found;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
