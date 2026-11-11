<?php

declare(strict_types=1);

namespace App\Actions\Notebooks;

use App\Enums\FeedbackType;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Support\FeedbackTable;
use App\Support\ListEntries;
use App\Support\RichText;
use Illuminate\Support\Str;

/**
 * Stores the board's three boxes using the same column, cleaning and blank-row
 * rules as session feedback. Rows are claimed by their client id, so a retry
 * or a repeated id never duplicates an entry, and rows the board no longer
 * holds are removed.
 */
final readonly class SaveNotebookFeedback
{
    /**
     * @param  array<string, mixed>  $feedback
     */
    public function handle(Notebook $notebook, array $feedback): void
    {
        foreach ([FeedbackType::Vocabulary, FeedbackType::Phrase, FeedbackType::GrammarNote] as $type) {
            $this->saveBox($notebook, $type, $feedback[$type->value] ?? null);
        }
    }

    /**
     * Stores one box and removes the entries it no longer holds.
     *
     * @param  mixed  $submitted  the box as the editor sent it
     */
    private function saveBox(Notebook $notebook, FeedbackType $type, mixed $submitted): void
    {
        $rows = $notebook->entries()->where('type', $type->value)->get()->all();
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

            $row = $this->claimRow($notebook, $type, $rows, $claimed, $entry['client_id']);

            $row->position = count($stored);
            $row->content_html = $entry['content_html'];
            $row->content_text = $entry['content_text'];
            $row->correction_html = $entry['correction_html'];
            $row->correction_text = $entry['correction_text'];
            $row->reason = $entry['reason'];
            $row->example = $entry['example'];
            $row->example_html = $entry['example_html'];
            $row->is_split = $entry['is_split'];
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
     * @return list<array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>, client_id: string|null, is_split: bool}>
     */
    private function entriesToStore(FeedbackType $type, mixed $submitted): array
    {
        if ($submitted === null) {
            return [];
        }

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

            foreach ($this->itemEntries($type, $item) as $index => $entry) {
                $entries[] = [...$entry, 'client_id' => $index === 0 ? $clientId : null];
            }
        }

        return $entries;
    }

    /**
     * One submitted item, split into the rows it holds. A table item is one
     * row; a grammar note is one formatted entry.
     *
     * @param  array<mixed>  $item
     * @return list<array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>, is_split: bool}>
     */
    private function itemEntries(FeedbackType $type, array $item): array
    {
        if ($type->isTable()) {
            return $this->tableEntries($type, $item);
        }

        $content = is_string($item[$this->contentKey($type)] ?? null)
            ? $item[$this->contentKey($type)]
            : '';

        $clean = RichText::cleanWithText($content);

        return $clean['text'] === ''
            ? []
            : [[...$this->blankRow(), 'content_html' => $clean['html'], 'content_text' => $clean['text']]];
    }

    /**
     * One table item, as the editor sends it: every column, with any formatted
     * column reduced to a single inline line.
     *
     * @param  array<mixed>  $item
     * @return list<array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>, is_split: bool}>
     */
    private function tableEntries(FeedbackType $type, array $item): array
    {
        if (! array_key_exists(FeedbackTable::attentionKey($type), $item)) {
            $text = is_string($item['text'] ?? null) ? $item['text'] : '';

            return $this->tableLines($type, $text);
        }

        $cells = [];

        foreach (FeedbackTable::columns($type) as $column) {
            $cells[$column['key']] = $item[$column['key']] ?? '';
        }

        $stored = FeedbackTable::storedFromCells($type, $cells);

        if (FeedbackTable::isBlank($stored)) {
            return [];
        }

        return [[...$stored, 'is_split' => true]];
    }

    /**
     * Split a plain-text box into table rows, applying the same separator
     * rules as the editor.
     *
     * @return list<array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>, is_split: bool}>
     */
    private function tableLines(FeedbackType $type, string $content): array
    {
        $entries = [];

        foreach (ListEntries::split($content) as $line) {
            $stored = FeedbackTable::storedFromLine($type, $line);

            if (FeedbackTable::isBlank($stored)) {
                continue;
            }

            $entries[] = [...$stored, 'is_split' => true];
        }

        return $entries;
    }

    /**
     * A box arriving as one piece of text: a table box splits into rows, a
     * grammar note stays one entry.
     *
     * @return list<array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>, client_id: string|null, is_split: bool}>
     */
    private function singleEntry(FeedbackType $type, string $content): array
    {
        if ($type->isTable()) {
            return array_map(
                fn (array $entry): array => [...$entry, 'client_id' => null],
                $this->tableLines($type, $content),
            );
        }

        $clean = RichText::cleanWithText($content);

        return $clean['text'] === ''
            ? []
            : [[...$this->blankRow(), 'content_html' => $clean['html'], 'content_text' => $clean['text'], 'client_id' => null]];
    }

    /**
     * Find the row this entry edits, or make a new one. A matching client id
     * wins; a row without an id is claimed next; otherwise the entry is new.
     *
     * @param  array<int, PracticeFeedbackEntry>  $rows
     * @param  array<int, bool>  $claimed
     */
    private function claimRow(
        Notebook $notebook,
        FeedbackType $type,
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

        return $notebook->entries()->make([
            'type' => $type,
            'client_id' => $clientId ?? (string) Str::uuid(),
        ]);
    }

    /**
     * @return array{content_html: string, content_text: string, correction_html: string, correction_text: string, reason: string, example: string, example_html: string|list<array{id: string, text: string, html: string}>, is_split: bool}
     */
    private function blankRow(): array
    {
        return [
            'content_html' => '',
            'content_text' => '',
            'correction_html' => '',
            'correction_text' => '',
            'reason' => '',
            'example' => '',
            'example_html' => '',
            'is_split' => false,
        ];
    }

    private function contentKey(FeedbackType $type): string
    {
        return $type->isRich() ? 'html' : 'text';
    }
}
