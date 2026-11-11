<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where the items of a collection come from. A session word and the same word
 * saved in a notebook are separate items, so the learner can look at either
 * source alone or at both together.
 */
enum CollectionSource: string
{
    case All = 'all';
    case Sessions = 'sessions';
    case Notebooks = 'notebooks';

    /** An unknown value falls back to both sources, like a stale bookmark. */
    public static function fromQuery(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::All;
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'All sources',
            self::Sessions => 'Sessions',
            self::Notebooks => 'Notebooks',
        };
    }
}
