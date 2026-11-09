<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CustomReview;

/**
 * Where the review's page goes back to: the page the learner came from, with
 * its tab, filters and opened item intact, or the custom reviews listing when
 * there is nowhere to go back to.
 *
 * Only a local address inside the app is accepted. Anything else — an outside
 * link, or another review the learner would rather not be bounced between —
 * falls back to the listing, so the link can never send them off the app or
 * from one review into another.
 */
final class CustomReviewReturn
{
    public static function url(?string $from): string
    {
        $fallback = route('customReviews.index');

        if ($from === null || trim($from) === '') {
            return $fallback;
        }

        $parts = parse_url($from);

        if ($parts === false || isset($parts['host']) || isset($parts['scheme'])) {
            return $fallback;
        }

        $path = $parts['path'] ?? '';

        if (! self::isAPage($path)) {
            return $fallback;
        }

        $query = isset($parts['query']) && $parts['query'] !== ''
            ? '?'.$parts['query']
            : '';

        return $path.$query;
    }

    /** Whether this address is a page of the app that can hold the learner. */
    private static function isAPage(string $path): bool
    {
        if ($path === '/' || str_starts_with($path, '//')) {
            return false;
        }

        // The listing itself is a fine place to come back to; any other
        // address inside the feature would bounce the learner from one review
        // to another.
        if (str_starts_with($path, '/custom-reviews') && $path !== '/custom-reviews') {
            return false;
        }

        return true;
    }

    /**
     * The address a collection or reading page sends the learner to when they
     * open the review their items went into.
     */
    public static function reviewUrl(CustomReview $review, ?string $from): string
    {
        $back = self::url($from);
        $query = $back === self::url(null) ? [] : ['from' => $back];

        return route('customReviews.show', ['customReview' => $review->id, ...$query]);
    }
}
