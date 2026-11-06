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

    /** How long one fill-in-the-blank item takes, for the estimate. */
    public const int FILL_IN_THE_BLANK_SECONDS = 40;

    /** The sizes a custom review offers, with the daily default among them. */
    public const array CUSTOM_SIZES = [5, self::SIZE, 10, 15, 20];

    /**
     * How many items the learner may still be short of before a custom review
     * gently says so. A match this size or bigger is a fine sitting, however
     * many items were asked for.
     */
    public const int MIN_COMFORTABLE_MATCH = 4;

    /** How many recent results Review Home lists. */
    public const int RECENT_RESULTS = 5;

    /**
     * How many Easy ratings in a row make the finish screen suggest marking an
     * item Learned, and how many Hard ratings in a row suggest Still difficult.
     * The learner always confirms either one.
     */
    public const int LEARNED_EASY_RUN = 3;

    /** @see self::LEARNED_EASY_RUN */
    public const int STILL_DIFFICULT_HARD_RUN = 2;

    /** The most the finish screen ever raises at once. */
    public const int MAX_STATUS_SUGGESTIONS = 2;

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
