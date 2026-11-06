<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CustomReview;

/**
 * Where the review's page goes back to: the page the learner came from, with
 * its tab, filters and opened item intact, or Collections when there is
 * nowhere to go back to.
 *
 * Only a local address inside the app is accepted. Anything else — an outside
 * link, or another review the learner would rather not be bounced between —
 * falls back to Collections, so the link can never send them off the app.
 */
final class CustomReviewReturn
{
    public static function url(?string $from): string
    {
        $fallback = route('collections.index');

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
        return $path !== '/'
            && ! str_starts_with($path, '/custom-reviews')
            && ! str_starts_with($path, '//');
    }

    /**
     * The address a collection or reading page sends the learner to when they
     * open the review their items went into.
     */
    public static function reviewUrl(CustomReview $review, ?string $from): string
    {
        $query = [];

        $back = self::url($from);

        if ($back !== route('collections.index')) {
            $query['from'] = $back;
        }

        return route('customReviews.show', ['customReview' => $review->id, ...$query]);
    }
}
