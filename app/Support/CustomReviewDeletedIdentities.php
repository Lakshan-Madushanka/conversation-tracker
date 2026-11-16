<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;

/**
 * Naming the custom-review items that rows about to be deleted held.
 *
 * A deletion is the one moment the app knows what has stopped existing, so the
 * rows it removes are named the way custom reviews name them everywhere else.
 * The Collections key already carries each kind's rule — the note's id, the
 * mistake's pair, the normalised word or phrase — and a question is named by
 * its own id.
 *
 * The read of a question's rows lives here so a caller hands over ids instead
 * of picking columns and leaving a kind out. A row whose key is blank named
 * nothing, so it is left out too.
 */
final class CustomReviewDeletedIdentities
{
    /**
     * The identities held by the given questions and every row inside them.
     *
     * @param  array<int>  $questionIds
     * @return array<string, list<string>>
     */
    public static function forQuestions(array $questionIds): array
    {
        if ($questionIds === []) {
            return [];
        }

        /** @var list<PracticeFeedbackEntry> $entries */
        $entries = PracticeFeedbackEntry::query()
            ->whereIn('practice_question_id', $questionIds)
            ->get()
            ->all();

        return self::grouped($entries, $questionIds);
    }

    /**
     * The identities the given rows carry, plus the questions they belong to.
     *
     * @param  iterable<PracticeFeedbackEntry>  $entries
     * @param  array<int>  $questionIds
     * @return array<string, list<string>>
     */
    public static function grouped(iterable $entries, array $questionIds): array
    {
        /** @var array<string, array<array-key, true>> $found */
        $found = [];

        foreach ($entries as $entry) {
            self::remember($found, $entry);
        }

        foreach ($questionIds as $questionId) {
            $found[FeedbackType::Question->value][(string) $questionId] = true;
        }

        return self::groupedFrom($found);
    }

    /**
     * Adds one row to a grouping being built, if it names anything. The caller
     * decides which rows count; a row whose key is blank names nothing.
     *
     * @param  array<string, array<array-key, true>>  $found
     */
    public static function remember(array &$found, PracticeFeedbackEntry $entry): void
    {
        if (! $entry->type->isCustomReviewType()) {
            return;
        }

        $identity = $entry->collection_key;

        if ($identity === null || $identity === '') {
            return;
        }

        $found[$entry->type->value][$identity] = true;
    }

    /**
     * The grouping in the shape callers read. A question id is digits, and PHP
     * turns such a key into an int, so every identity is handed back as the
     * words it stores.
     *
     * @param  array<string, array<array-key, true>>  $found
     * @return array<string, list<string>>
     */
    public static function groupedFrom(array $found): array
    {
        /** @var array<string, list<string>> $grouped */
        $grouped = [];

        foreach ($found as $kind => $identities) {
            $grouped[$kind] = array_map(strval(...), array_keys($identities));
        }

        return $grouped;
    }
}
