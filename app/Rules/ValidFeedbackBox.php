<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\PracticeFeedbackEntry;
use App\Support\ListEntries;
use App\Support\RichText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates one feedback box of a question.
 *
 * The mistakes box holds a list of rows, each with what was said, a correction
 * and an optional why; the vocabulary and phrases boxes and the grammar notes
 * box hold a list of entries; the learner's browser sends them as an array of
 * items, each with a stable `id` and its content. A box saved before entries
 * existed arrives as one piece of text and is still accepted, so content from
 * earlier steps is never refused.
 *
 * The limits are checked against what would actually be stored — cleaned
 * entries, one per line or one per cell — so the message always matches what
 * the learner sees.
 */
final readonly class ValidFeedbackBox implements ValidationRule
{
    public const int MAX_ENTRIES = ListEntries::MAX_ENTRIES;

    public const int MAX_LENGTH = ListEntries::MAX_LENGTH;

    /**
     * @param  string  $type  The feedback box this rule checks.
     * @param  int  $maxTextLength  Longest single piece of text, for a box saved before entries existed or one grammar note.
     */
    public function __construct(
        private string $type,
        private int $maxTextLength,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_string($value)) {
            if (mb_strlen($value) > $this->maxTextLength) {
                $fail('This feedback is too long to save at once. Try splitting it into shorter parts.');
            }

            return;
        }

        if (! is_array($value)) {
            $fail('This entry could not be saved. Check the text and try again.');

            return;
        }

        if (count($value) > self::MAX_ENTRIES) {
            $fail('A box can hold up to '.self::MAX_ENTRIES.' entries. Remove one to add another.');

            return;
        }

        $entries = $this->countAndCheck($value, $fail);

        if ($entries > self::MAX_ENTRIES) {
            $fail('A box can hold up to '.self::MAX_ENTRIES.' entries. Remove one to add another.');
        }
    }

    /**
     * Checks every submitted item and returns how many entries it would store.
     *
     * @param  array<mixed>  $value
     */
    private function countAndCheck(array $value, Closure $fail): int
    {
        if (PracticeFeedbackEntry::isMistake($this->type)) {
            return $this->countMistakes($value, $fail);
        }

        $isList = PracticeFeedbackEntry::isList($this->type);
        $count = 0;

        foreach ($value as $item) {
            if (! is_array($item)) {
                $fail('This entry could not be saved. Check the text and try again.');

                return 0;
            }

            $content = $item[$isList ? 'text' : 'html'] ?? null;

            if (! is_string($content)) {
                $fail('This entry could not be saved. Check the text and try again.');

                return 0;
            }

            if (! $isList) {
                if (mb_strlen($content) > $this->maxTextLength) {
                    $fail('This feedback is too long to save at once. Try splitting it into shorter parts.');

                    return 0;
                }

                $count++;

                continue;
            }

            foreach (ListEntries::split($content) as $line) {
                if (mb_strlen($line) > self::MAX_LENGTH) {
                    $fail('Each entry can be up to '.self::MAX_LENGTH.' characters. Split it into two entries.');

                    return 0;
                }

                $count++;
            }
        }

        return $count;
    }

    /**
     * Checks every mistake item and returns how many rows it would store. Each
     * of the three cells has to fit the same limit as a list entry.
     *
     * @param  array<mixed>  $value
     */
    private function countMistakes(array $value, Closure $fail): int
    {
        $count = 0;

        foreach ($value as $item) {
            if (! is_array($item) || ! is_string($item['text'] ?? null)) {
                $fail('This entry could not be saved. Check the text and try again.');

                return 0;
            }

            $text = $item['text'];

            // A mistake saved before the table existed arrives with only text
            // and is split into rows, one per line, when it is stored.
            if (! array_key_exists('correction_html', $item)) {
                foreach (ListEntries::split($text) as $line) {
                    if (! $this->fits($line, $fail)) {
                        return 0;
                    }

                    $count++;
                }

                continue;
            }

            $correction = $item['correction_html'];
            $reason = $item['reason'] ?? '';

            if (! is_string($correction) || ! is_string($reason)) {
                $fail('This entry could not be saved. Check the text and try again.');

                return 0;
            }

            $correctionText = RichText::cleanInlineWithText($correction)['text'];

            if (! $this->fits(ListEntries::cleanLine($text), $fail)
                || ! $this->fits($correctionText, $fail)
                || ! $this->fits(ListEntries::cleanLine($reason), $fail)) {
                return 0;
            }

            $count++;
        }

        return $count;
    }

    /** Fails when one cell is longer than the limit. */
    private function fits(string $cell, Closure $fail): bool
    {
        if (mb_strlen($cell) > self::MAX_LENGTH) {
            $fail('Each entry can be up to '.self::MAX_LENGTH.' characters. Split it into two entries.');

            return false;
        }

        return true;
    }
}
