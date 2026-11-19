<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which results Review Home's recent list shows.
 *
 * A daily sitting and a custom review sit in the same list; this is the one
 * choice that narrows it. A custom review counts both a sitting started from
 * a saved review and an attempt at one, so the two never need separate kinds.
 */
enum RecentReviewKind: string
{
    case All = 'all';

    case Daily = 'daily';

    case Custom = 'custom';

    /** Whether the list holds daily sittings. */
    public function includesDaily(): bool
    {
        return $this !== self::Custom;
    }

    /** Whether the list holds custom sittings and attempts. */
    public function includesCustom(): bool
    {
        return $this !== self::Daily;
    }

    /**
     * The choice named in the address, falling back to every kind when it
     * names nothing the list knows.
     */
    public static function fromQuery(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::All) : self::All;
    }
}
