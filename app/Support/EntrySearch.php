<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;

/**
 * The searchable view of one feedback entry: which columns it holds words in,
 * the one normalised string the search column keeps, and the column that best
 * explains why a row matched.
 *
 * A table row's columns are separate fields; a rich note keeps all its words
 * in one. Keeping this apart from the model lets the stored columns and the
 * search projection change for their own reasons.
 */
final class EntrySearch
{
    /**
     * The plain text of an entry, split by the column it came from.
     *
     * @return array<string, string>
     */
    public static function fields(PracticeFeedbackEntry $entry): array
    {
        if (! $entry->type->isTable()) {
            return ['content' => (string) $entry->content_text];
        }

        $fields = [
            'content' => (string) $entry->content_text,
            'correction' => (string) $entry->correction_text,
            'reason' => (string) $entry->reason,
        ];

        if (in_array($entry->type, [FeedbackType::Vocabulary, FeedbackType::Phrase], true)) {
            $fields['example'] = self::exampleText($entry);
        }

        return array_filter(
            $fields,
            fn (string $value): bool => trim($value) !== '',
        );
    }

    /**
     * Everything an entry holds, normalised into the one column search reads.
     * A table row is one item, so all of its columns are searched together.
     */
    public static function text(PracticeFeedbackEntry $entry): string
    {
        return SearchText::normalize(
            implode(' ', array_values(self::fields($entry))),
        );
    }

    /**
     * The field that best shows why a row matched, used for its snippet. The
     * field holding the most terms wins; the first one wins a tie.
     *
     * @param  list<string>  $terms
     * @return array{field: string, text: string}
     */
    public static function bestField(PracticeFeedbackEntry $entry, array $terms): array
    {
        $best = null;
        $bestScore = -1;

        foreach (self::fields($entry) as $field => $text) {
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

        return $best ?? ['field' => 'content', 'text' => (string) $entry->content_text];
    }

    /** The words of a vocabulary or phrase example, whichever way it was saved. */
    public static function exampleText(PracticeFeedbackEntry $entry): string
    {
        $example = (string) $entry->example;

        if (trim($example) !== '') {
            return $example;
        }

        // The column is read raw, because older rows can still hold a plain
        // string where newer ones hold a list of parts.
        $exampleHtml = $entry->getAttribute('example_html');

        if (! is_array($exampleHtml)) {
            return '';
        }

        return implode(' ', array_map(
            fn (mixed $item): string => is_array($item) && isset($item['text'])
                ? (string) $item['text']
                : '',
            $exampleHtml,
        ));
    }
}
