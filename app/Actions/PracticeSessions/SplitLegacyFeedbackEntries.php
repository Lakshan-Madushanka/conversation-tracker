<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Support\ListEntries;
use App\Support\MistakeEntries;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Converts feedback saved before entries existed into the stored entries the
 * editor now works with: every line of a pasted list becomes its own entry,
 * every entry gets the id later saves match on, and the order is written back.
 *
 * Mistakes saved as one line are split into their columns the same way the
 * editor splits a paste, and marked as split so the job runs only once.
 *
 * Grammar notes and the natural version keep their content — only the list and
 * mistake boxes are split. Running it again finds one line per entry and
 * changes nothing, so it is safe to run on every open.
 */
final class SplitLegacyFeedbackEntries
{
    /**
     * Convert one session's questions. Sessions already converted are skipped
     * without touching the database.
     */
    public function handle(PracticeSession $session): void
    {
        $session->loadMissing('questions.feedbackEntries');

        foreach ($session->questions as $question) {
            $this->handleQuestion($question);
        }
    }

    /**
     * Convert every saved session, in batches, for the maintenance command.
     *
     * @return int the number of questions that changed
     */
    public function handleAll(): int
    {
        $changed = 0;

        PracticeSession::query()->with('questions.feedbackEntries')->chunkById(50, function (Collection $sessions) use (&$changed): void {
            foreach ($sessions as $session) {
                foreach ($session->questions as $question) {
                    if ($this->handleQuestion($question)) {
                        $changed++;
                    }
                }
            }
        });

        return $changed;
    }

    /**
     * Split one question's boxes and give every entry its id and position.
     *
     * @return bool whether anything changed
     */
    private function handleQuestion(PracticeQuestion $question): bool
    {
        $changed = false;

        foreach (PracticeFeedbackEntry::TYPES as $type) {
            $changed = $this->convertBox($question, $type) || $changed;
        }

        return $changed;
    }

    /**
     * Split one box into entries, one per line, and renumber them in order.
     */
    private function convertBox(PracticeQuestion $question, string $type): bool
    {
        if (PracticeFeedbackEntry::isMistake($type)) {
            return $this->convertMistakeBox($question, $type);
        }

        $rows = $question->feedbackEntries
            ->filter(fn (PracticeFeedbackEntry $entry): bool => $entry->type === $type)
            ->sortBy([['position', 'asc'], ['id', 'asc']])
            ->values();

        if ($rows->isEmpty()) {
            return false;
        }

        $isList = PracticeFeedbackEntry::isList($type);
        $position = 0;
        $changed = false;

        foreach ($rows as $row) {
            $lines = $isList
                ? ListEntries::split($row->content_text)
                : ($row->content_text === '' ? [] : [$row->content_text]);

            // A box saved as blank space holds nothing worth keeping.
            if ($lines === []) {
                $row->delete();
                $changed = true;

                continue;
            }

            $first = array_shift($lines);

            $changed = $this->align($row, $type, $first, $position) || $changed;
            $position++;

            foreach ($lines as $line) {
                $question->feedbackEntries()->create([
                    'type' => $type,
                    'position' => $position,
                    'client_id' => (string) Str::uuid(),
                    'content_html' => '',
                    'content_text' => $line,
                ]);

                $position++;
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Split one mistakes box into its columns. A row saved before the table
     * existed holds one line; it is split by the separator rules into what was
     * said, the correction and a why, and marked as split so it is never
     * touched again.
     */
    private function convertMistakeBox(PracticeQuestion $question, string $type): bool
    {
        $rows = $question->feedbackEntries
            ->filter(fn (PracticeFeedbackEntry $entry): bool => $entry->type === $type)
            ->sortBy([['position', 'asc'], ['id', 'asc']])
            ->values();

        if ($rows->isEmpty()) {
            return false;
        }

        $position = 0;
        $changed = false;

        foreach ($rows as $row) {
            // A row already split into columns only needs its id and order.
            if ($row->is_split) {
                $changed = $this->alignStored($row, $position) || $changed;
                $position++;

                continue;
            }

            $lines = ListEntries::split($row->content_text);

            // A row saved as blank space holds nothing worth keeping.
            if ($lines === []) {
                $row->delete();
                $changed = true;

                continue;
            }

            $first = array_shift($lines);

            $changed = $this->alignMistake($row, $first, $position) || $changed;
            $position++;

            foreach ($lines as $line) {
                $parsed = MistakeEntries::parseLine($line);

                $question->feedbackEntries()->create([
                    'type' => $type,
                    'position' => $position,
                    'client_id' => (string) Str::uuid(),
                    'content_html' => '',
                    'content_text' => $parsed['text'],
                    'correction_html' => $parsed['correction_html'],
                    'correction_text' => $parsed['correction_text'],
                    'reason' => $parsed['reason'],
                    'is_split' => true,
                ]);

                $position++;
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Split one legacy mistake line onto a row's three columns. Returns whether
     * the row changed.
     */
    private function alignMistake(PracticeFeedbackEntry $row, string $line, int $position): bool
    {
        $parsed = MistakeEntries::parseLine($line);

        $row->client_id ??= (string) Str::uuid();
        $row->content_html = '';
        $row->content_text = $parsed['text'];
        $row->correction_html = $parsed['correction_html'];
        $row->correction_text = $parsed['correction_text'];
        $row->reason = $parsed['reason'];
        $row->is_split = true;

        if ($row->position !== $position) {
            $row->position = $position;
        }

        if ($row->isDirty()) {
            $row->save();

            return true;
        }

        return false;
    }

    /**
     * Give an already-split row its id and position, leaving its columns alone.
     * Returns whether the row changed.
     */
    private function alignStored(PracticeFeedbackEntry $row, int $position): bool
    {
        $row->client_id ??= (string) Str::uuid();

        if ($row->position !== $position) {
            $row->position = $position;
        }

        if ($row->isDirty()) {
            $row->save();

            return true;
        }

        return false;
    }

    /**
     * Bring one row in line with the stored shape: a stable id, a position and
     * a single clean line. Returns whether the row changed.
     */
    private function align(PracticeFeedbackEntry $row, string $type, string $text, int $position): bool
    {
        $clientId = $row->client_id ?? (string) Str::uuid();

        $row->client_id ??= $clientId;

        if ($row->content_text !== $text) {
            $row->content_text = $text;
            $row->content_html = PracticeFeedbackEntry::isRich($type) ? $row->content_html : '';
        }

        if ($row->position !== $position) {
            $row->position = $position;
        }

        if ($row->isDirty()) {
            $row->save();

            return true;
        }

        return false;
    }
}
