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
        /** @var array<string, array<string, true>> $found */
        $found = [];

        foreach ($entries as $entry) {
            if (! $entry->type->isCustomReviewType()) {
                continue;
            }

            $identity = self::identityOf($entry);

            if ($identity === null) {
                continue;
            }

            $found[$entry->type->value][$identity] = true;
        }

        foreach ($questionIds as $questionId) {
            $found[FeedbackType::Question->value][(string) $questionId] = true;
        }

        /** @var array<string, list<string>> $grouped */
        $grouped = [];

        foreach ($found as $kind => $identities) {
            // A question id is digits, and PHP turns such a key into an int,
            // so every identity is read back as the words it stores.
            $grouped[$kind] = array_map(strval(...), array_keys($identities));
        }

        return $grouped;
    }

    /** The identity this row names, or null when it names nothing. */
    private static function identityOf(PracticeFeedbackEntry $entry): ?string
    {
        $identity = $entry->collection_key;

        return ($identity === null || $identity === '') ? null : $identity;
    }
}
