<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Support\ListEntries;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Converts feedback saved before entries existed into the stored entries the
 * editor now works with: every line of a pasted list becomes its own entry,
 * every entry gets the id later saves match on, and the order is written back.
 *
 * Grammar notes and the natural version keep their content — only the list
 * boxes are split. Running it again finds one line per entry and changes
 * nothing, so it is safe to run on every open.
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
