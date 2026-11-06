<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The page "Back to collection" returns to: the Collections or Review page
 * the basket was opened from, with its tab, filters and panel intact.
 *
 * Only a local address on one of those pages is accepted. Anything else — an
 * outside link or a page that does not show the basket — falls back to
 * Collections, so the link can never send the learner off the app.
 */
final class BasketReturn
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

        if (! self::isBasketPage($path)) {
            return $fallback;
        }

        $query = isset($parts['query']) && $parts['query'] !== ''
            ? '?'.$parts['query']
            : '';

        return $path.$query;
    }

    /** Whether this is a page that shows the basket bar in this step. */
    private static function isBasketPage(string $path): bool
    {
        return $path === '/collections'
            || str_starts_with($path, '/collections/')
            || $path === '/review'
            || str_starts_with($path, '/review/');
    }
}
