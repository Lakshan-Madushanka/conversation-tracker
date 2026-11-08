<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What the listing says when a custom review is gone, in the review's own calm
 * words.
 *
 * Deleting a review leaves the listing, so the one line that answers the learner
 * is written here and nowhere else: the same words for the same fact, whether
 * the request came from a card, the review's page or a second tab.
 */
final class CustomReviewDeletion
{
    /** What a delete answers with when the review was already gone. */
    public const string GONE = 'This custom review is no longer there.';

    /** What a delete answers with, naming the review that went. */
    public static function deleted(string $name): string
    {
        return $name.' was deleted.';
    }
}
