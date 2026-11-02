<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The learner's own status, as a filter.
 *
 * "Not marked" is a filter of its own, because an item can be starred or
 * carry notes without ever having been labelled.
 */
enum CollectionStatusFilter: string
{
    case All = 'all';

    case NotMarked = 'none';

    case Learned = 'learned';

    case StillDifficult = 'still_difficult';

    public static function fromQuery(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::All;
    }

    /** The stored status this filter asks for, or null when it asks for none. */
    public function statusValue(): ?string
    {
        return match ($this) {
            self::Learned => CollectionItemStatus::Learned->value,
            self::StillDifficult => CollectionItemStatus::StillDifficult->value,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'All statuses',
            self::NotMarked => 'Not marked',
            self::Learned => 'Learned',
            self::StillDifficult => 'Still difficult',
        };
    }
}
