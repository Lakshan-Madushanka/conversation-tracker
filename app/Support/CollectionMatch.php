<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;
use App\Models\CollectionItemMark;

/**
 * The server-computed hint that says where a search found an item.
 *
 * Search matches normalised words, but the learner sees the words they wrote.
 * This class picks the field that holds the most of their terms and cuts a
 * short piece of that original text around the match, split into plain and
 * matched pieces. The page renders those pieces as text, never as saved HTML,
 * so a highlight can never carry anything a session did not save safely.
 */
final class CollectionMatch
{
    /**
     * The hint for one word or phrase.
     *
     * @param  array<string, mixed>  $item
     * @param  list<string>  $terms
     * @return array{label: string, segments: list<array{text: string, match: bool}>}|null
     */
    public static function forItem(
        array $item,
        ?CollectionItemMark $mark,
        array $terms,
        FeedbackType $type,
    ): ?array {
        $title = (string) ($item['title'] ?? '');

        return self::fromFields([
            ($type === FeedbackType::Phrase ? 'Phrase' : 'Word') => $title,
            'Meaning' => (string) ($item['meaning'] ?? ''),
            'Example' => (string) ($item['example'] ?? ''),
            'Your notes' => (string) ($mark->notes_text ?? ''),
        ], $terms);
    }

    /**
     * The hint for one mistake.
     *
     * @param  array<string, mixed>  $item
     * @param  list<string>  $terms
     * @return array{label: string, segments: list<array{text: string, match: bool}>}|null
     */
    public static function forMistake(
        array $item,
        ?CollectionItemMark $mark,
        array $terms,
    ): ?array {
        return self::fromFields([
            'What I said' => (string) ($item['title'] ?? ''),
            'Correction' => (string) ($item['correction'] ?? ''),
            'Why' => (string) ($item['reason'] ?? ''),
            'Your notes' => (string) ($mark->notes_text ?? ''),
        ], $terms);
    }

    /**
     * The hint for one grammar note.
     *
     * @param  array<string, mixed>  $item
     * @param  list<string>  $terms
     * @return array{label: string, segments: list<array{text: string, match: bool}>}|null
     */
    public static function forNote(
        array $item,
        ?CollectionItemMark $mark,
        array $terms,
    ): ?array {
        return self::fromFields([
            'Note' => (string) ($item['text'] ?? ''),
            'Your notes' => (string) ($mark->notes_text ?? ''),
        ], $terms);
    }

    /**
     * The field holding the most of the learner's terms wins; the first field
     * wins a tie. Nothing is shown when no field holds a term, so the page
     * never labels a match that is not there.
     *
     * @param  array<string, string>  $fields
     * @param  list<string>  $terms
     * @return array{label: string, segments: list<array{text: string, match: bool}>}|null
     */
    private static function fromFields(array $fields, array $terms): ?array
    {
        if ($terms === []) {
            return null;
        }

        $label = null;
        $text = '';
        $best = -1;

        foreach ($fields as $candidateLabel => $candidateText) {
            if (trim($candidateText) === '') {
                continue;
            }

            $normalized = SearchText::normalize($candidateText);
            $score = 0;

            foreach ($terms as $term) {
                if (str_contains($normalized, $term)) {
                    $score += 1;
                }
            }

            if ($score > $best) {
                $best = $score;
                $label = $candidateLabel;
                $text = $candidateText;
            }

            if ($score === count($terms)) {
                break;
            }
        }

        if ($label === null || $best <= 0) {
            return null;
        }

        return [
            'label' => $label,
            'segments' => SearchText::snippet($text, $terms),
        ];
    }
}
