<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The few numbers that shape a review, in one place. Changing them changes
 * the review without touching anything else.
 */
final class ReviewSettings
{
    /** How many items one review holds by default. */
    public const int SIZE = 8;

    /**
     * At most this many never-reviewed items in one review when there are
     * enough due items to fill the rest.
     */
    public const int MAX_NEW_ITEMS = 4;

    /** How long one flashcard takes, for the estimate. */
    public const int FLASHCARD_SECONDS = 35;
}
