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
     * The item's public collection identity, by the same rule the Collections
     * page groups by: the normalised word or phrase (scoped to its notebook
     * when it has one), the normalised mistake pair, or a grammar note's own
     * row key, which is unique wherever the note lives.
     *
     * A blank row has no identity at all, so it never joins a collection.
     */
    public static function collectionKey(PracticeFeedbackEntry $entry): ?string
    {
        if ($entry->type === FeedbackType::GrammarNote) {
            return $entry->exists ? 'note-'.$entry->getKey() : null;
        }

        if ($entry->type === FeedbackType::Mistake) {
            $said = (string) $entry->content_text;
            $correction = (string) $entry->correction_text;

            return MistakeIdentity::isBlank($said, $correction)
                ? null
                : MistakeIdentity::pairKey($said, $correction);
        }

        if ($entry->type === FeedbackType::Vocabulary || $entry->type === FeedbackType::Phrase) {
            $key = SearchText::normalize((string) $entry->content_text);

            if ($key === '') {
                return null;
            }

            // A notebook word stays inside its notebook; a session word keeps
            // the shared normalised text it has always had.
            return $entry->notebook_id === null
                ? $key
                : NotebookIdentity::wordKey($entry->notebook_id, $key);
        }

        return null;
    }

    /** The words A to Z reads: the row's subject without capitals or accents. */
    public static function sortText(PracticeFeedbackEntry $entry): string
    {
        return SearchText::normalize((string) $entry->content_text);
    }

    /**
     * Why this row asks for attention, in the one shared wording. Grammar
     * notes and rows outside Collections never ask for it.
     */
    public static function attentionReason(PracticeFeedbackEntry $entry): ?string
    {
        if (! $entry->type->isTable()) {
            return null;
        }

        return RowAttention::reason(
            $entry->type,
            (string) $entry->content_text,
            (string) $entry->correction_text,
            self::exampleText($entry),
        );
    }

    /**
     * Whether this row's line held too many separators to split. A grouped
     * item that holds one such row shows that calm reason instead of the
     * plainer missing-meaning one.
     */
    public static function splitBroke(PracticeFeedbackEntry $entry): bool
    {
        return self::attentionReason($entry) === RowAttention::MORE_THAN_ONE;
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
