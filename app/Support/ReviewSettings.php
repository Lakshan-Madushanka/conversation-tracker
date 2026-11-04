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

    /** How long one fix-the-mistake item takes, for the estimate. */
    public const int MISTAKE_SECONDS = 60;

    /** How long one say-it-again question takes, for the estimate. */
    public const int SAY_IT_AGAIN_SECONDS = 75;

    /**
     * The target share of every planned exercise kind. Only built kinds with
     * candidates take part; the rest are dropped and the remaining shares are
     * renormalised, so the setting can list the whole plan from day one.
     *
     * @var array<string, float>
     */
    public const array TARGET_SHARES = [
        'flashcard' => 0.5,
        'mistake' => 0.25,
        'say_it_again' => 0.125,
        'fill_in_the_blank' => 0.125,
    ];
}
