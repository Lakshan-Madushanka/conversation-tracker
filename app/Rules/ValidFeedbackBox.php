<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\PracticeFeedbackEntry;
use App\Support\ListEntries;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates one feedback box of a question.
 *
 * The mistakes, vocabulary and phrases boxes and the grammar notes box hold a
 * list of entries; the learner's browser sends them as an array of items, each
 * with a stable `id` and its text. A box saved before entries existed arrives
 * as one piece of text and is still accepted, so content from earlier steps is
 * never refused.
 *
 * The limits are checked against what would actually be stored — cleaned
 * entries, one per line — so the message always matches what the learner sees.
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
}
