<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;

/**
 * The one item a notebook's duplicate rows make, resolved the way the board
 * saved them.
 *
 * A notebook word or phrase can appear in several rows: the first row with a
 * meaning supplies it, the first row with an example supplies that, and any
 * complete row makes the item usable. Rows are read in saved order, never by
 * database row order or session date, so the same item always shows the same
 * words. A grammar note is its own row, so its own content decides.
 *
 * Collections, the notebook review candidates and a custom review's live
 * content all ask this class, so no surface can resolve an item differently
 * or borrow a meaning from another scope.
 */
final readonly class NotebookItemContent
{
    /**
     * @param  array<int, PracticeFeedbackEntry>  $rows  in saved order
     * @return array{
     *     title: string,
     *     meaning: string,
     *     example: string,
     *     example_html: string,
     *     split_broke: bool,
     *     usable: bool
     * }
     */
    public static function of(FeedbackType $kind, array $rows): array
    {
        $title = '';
        $meaning = '';
        $example = '';
        $exampleHtml = '';
        $splitBroke = false;
        $usable = false;

        foreach ($rows as $row) {
            $subject = trim((string) $row->content_text);
            $rowMeaning = trim((string) $row->correction_text);
            $rowExample = trim(EntrySearch::exampleText($row));

            if ($title === '' && $subject !== '') {
                $title = $subject;
            }

            if ($meaning === '' && $rowMeaning !== '') {
                $meaning = $rowMeaning;
            }

            if ($example === '' && $rowExample !== '') {
                $example = $rowExample;
                $exampleHtml = RichText::inlineExampleHtml($row->example_html);
            }

            $splitBroke = $splitBroke || EntrySearch::splitBroke($row);

            $usable = $usable || match ($kind) {
                FeedbackType::Vocabulary, FeedbackType::Phrase => $rowMeaning !== '',
                FeedbackType::GrammarNote => BlankExercise::build($row) !== null,
                default => false,
            };
        }

        return [
            'title' => $title,
            'meaning' => $meaning,
            'example' => $example,
            'example_html' => $exampleHtml,
            'split_broke' => $splitBroke,
            'usable' => $usable,
        ];
    }
}
