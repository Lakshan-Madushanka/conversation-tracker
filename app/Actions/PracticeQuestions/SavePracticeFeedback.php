<?php

declare(strict_types=1);

namespace App\Actions\PracticeQuestions;

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Support\ListEntries;
use App\Support\MistakeEntries;
use App\Support\RichText;
use Illuminate\Support\Str;

/**
 * Replaces a question's feedback boxes with the submitted content.
 *
 * A mistake holds three columns, a list box holds one row per entry, and a
 * grammar note box one row per note, so every entry keeps the id the editor
 * gave it. A retry with the same payload matches those ids and updates the
 * same rows instead of storing them again.
 */
final class SavePracticeFeedback
{
    /**
     * @param  array<string, mixed>  $feedback
     */
    public function handle(PracticeQuestion $question, array $feedback): void
    {
        foreach (PracticeFeedbackEntry::TYPES as $type) {
            $this->saveBox($question, $type, $feedback[$type] ?? null);
        }
    }

    /**
     * Stores one box and removes the entries it no longer holds.
     *
     * @param  mixed  $submitted  the box as the editor sent it
     */
    private function saveBox(PracticeQuestion $question, string $type, mixed $submitted): void
    {
        $rows = $question->feedbackEntries()->where('type', $type)->get()->all();
        $claimed = [];
        $stored = [];
        $seenIds = [];

        foreach ($this->entriesToStore($type, $submitted) as $entry) {
            // The same id twice in one box is one entry, not two rows.
            if ($entry['client_id'] !== null && isset($seenIds[$entry['client_id']])) {
                continue;
            }

            if ($entry['client_id'] !== null) {
                $seenIds[$entry['client_id']] = true;
            }

            $row = $this->claimRow($question, $type, $rows, $claimed, $entry['client_id']);

            $row->position = count($stored);
            $row->content_html = $entry['html'];
            $row->content_text = $entry['text'];
            $row->correction_html = $entry['correction_html'] ?? '';
            $row->correction_text = $entry['correction_text'] ?? '';
            $row->reason = $entry['reason'] ?? '';
            $row->is_split = $entry['is_split'] ?? false;
            $row->save();

            $claimed[spl_object_id($row)] = true;
            $stored[] = $row;
        }

        foreach ($rows as $row) {
            if (! isset($claimed[spl_object_id($row)])) {
                $row->delete();
            }
        }
    }

    /**
     * Turns the submitted box into the rows it should hold. An empty box
     * produces none, so removing the last entry removes the row.
     *
     * @return list<array{html: string, text: string, client_id: string|null, correction_html?: string, correction_text?: string, reason?: string, is_split?: bool}>
     */
    private function entriesToStore(string $type, mixed $submitted): array
    {
        if ($submitted === null) {
            return [];
        }

        // A box saved before entries existed arrives as one piece of text and
        // stays one entry until the editor splits it when the session opens.
        if (is_string($submitted)) {
            return $this->singleEntry($type, $submitted);
        }

        if (! is_array($submitted)) {
            return [];
        }

        $entries = [];

        foreach ($submitted as $item) {
            if (! is_array($item)) {
                continue;
            }

            $clientId = is_string($item['id'] ?? null) ? $item['id'] : null;
            $entriesFromItem = $this->itemEntries($type, $item);

            foreach ($entriesFromItem as $index => $entry) {
                // Only the first line of a pasted item keeps the id the editor
                // gave it; the lines after it are new entries.
                $entries[] = [...$entry, 'client_id' => $index === 0 ? $clientId : null];
            }
        }

        return $entries;
    }

    /**
     * One submitted item, split into the rows it holds. A mistake item is one
     * row; a pasted list arrives as several lines in one item and becomes one
     * row per line.
     *
     * @param  array<mixed>  $item
     * @return list<array{html: string, text: string, correction_html?: string, correction_text?: string, reason?: string, is_split?: bool}>
     */
    private function itemEntries(string $type, array $item): array
    {
        if (PracticeFeedbackEntry::isMistake($type)) {
            return $this->mistakeEntries($item);
        }

        $content = is_string($item[$this->contentKey($type)] ?? null)
            ? $item[$this->contentKey($type)]
            : '';

        if (! PracticeFeedbackEntry::isList($type)) {
            $clean = RichText::cleanWithText($content);

            return $clean['text'] === '' ? [] : [['html' => $clean['html'], 'text' => $clean['text']]];
        }

        return array_map(
            fn (string $line): array => ['html' => '', 'text' => $line],
            ListEntries::split($content),
        );
    }

    /**
     * One mistake item, as the editor sends it: the three columns, with the
     * correction reduced to a single inline line.
     *
     * A mistake saved before the table existed arrives with only `text`, so its
     * line is split by the separator rules instead.
     *
     * @param  array<mixed>  $item
     * @return list<array{html: string, text: string, correction_html: string, correction_text: string, reason: string, is_split: bool}>
     */
    private function mistakeEntries(array $item): array
    {
        $text = is_string($item['text'] ?? null) ? $item['text'] : '';

        if (! array_key_exists('correction_html', $item)) {
            return $this->splitMistakeLines($text);
        }

        $correction = RichText::cleanInlineWithText(
            is_string($item['correction_html']) ? $item['correction_html'] : '',
        );

        $reason = is_string($item['reason'] ?? null)
            ? ListEntries::cleanLine($item['reason'])
            : '';

        $cleanText = ListEntries::cleanLine($text);

        if ($cleanText === '' && $correction['text'] === '' && $reason === '') {
            return [];
        }

        return [[
            'html' => '',
            'text' => $cleanText,
            'correction_html' => $correction['html'],
            'correction_text' => $correction['text'],
            'reason' => $reason,
            'is_split' => true,
        ]];
    }

    /**
     * Split a line saved before the table existed into mistake rows, applying
     * the same separator rules as the editor.
     *
     * @return list<array{html: string, text: string, correction_html: string, correction_text: string, reason: string, is_split: bool}>
     */
    private function splitMistakeLines(string $content): array
    {
        $entries = [];

        foreach (ListEntries::split($content) as $line) {
            $parsed = MistakeEntries::parseLine($line);

            if ($parsed['text'] === '' && $parsed['correction_text'] === '') {
                continue;
            }

            $entries[] = [
                'html' => '',
                'text' => $parsed['text'],
                'correction_html' => $parsed['correction_html'],
                'correction_text' => $parsed['correction_text'],
                'reason' => $parsed['reason'],
                'is_split' => true,
            ];
        }

        return $entries;
    }

    /**
     * A box saved as plain text keeps its content as one entry.
     *
     * @return list<array{html: string, text: string, client_id: string|null, correction_html?: string, correction_text?: string, reason?: string, is_split?: bool}>
     */
    private function singleEntry(string $type, string $content): array
    {
        if (PracticeFeedbackEntry::isMistake($type)) {
            return array_map(
                fn (array $entry): array => [...$entry, 'client_id' => null],
                $this->splitMistakeLines($content),
            );
        }

        if (PracticeFeedbackEntry::isList($type)) {
            $text = RichText::normalizePlainText($content);

            return $text === '' ? [] : [['html' => '', 'text' => $text, 'client_id' => null]];
        }

        $clean = RichText::cleanWithText($content);

        return $clean['text'] === ''
            ? []
            : [['html' => $clean['html'], 'text' => $clean['text'], 'client_id' => null]];
    }

    /**
     * The row an entry belongs to: the one saved under the same id, or the
     * oldest row that no entry claimed yet, so an edit never adds a second row.
     *
     * @param  array<int, PracticeFeedbackEntry>  $rows
     * @param  array<int, bool>  $claimed
     */
    private function claimRow(
        PracticeQuestion $question,
        string $type,
        array $rows,
        array $claimed,
        ?string $clientId,
    ): PracticeFeedbackEntry {
        foreach ($rows as $row) {
            if ($clientId !== null && $row->client_id === $clientId && ! isset($claimed[spl_object_id($row)])) {
                return $row;
            }
        }

        foreach ($rows as $row) {
            if ($row->client_id === null && ! isset($claimed[spl_object_id($row)])) {
                return $row;
            }
        }

        return $question->feedbackEntries()->make([
            'type' => $type,
            'client_id' => $clientId ?? (string) Str::uuid(),
        ]);
    }

    private function contentKey(string $type): string
    {
        return PracticeFeedbackEntry::isList($type) || PracticeFeedbackEntry::isMistake($type)
            ? 'text'
            : 'html';
    }
}
