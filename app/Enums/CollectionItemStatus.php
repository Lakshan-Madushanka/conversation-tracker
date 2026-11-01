<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The learner's own label on a collection item. There is no stored "none"
 * case: an item without a status simply carries null, so the learner is the
 * only one who can ever set or clear it.
 */
enum CollectionItemStatus: string
{
    case Learned = 'learned';

    case StillDifficult = 'still_difficult';

    /** The wording the learner sees, as text — never colour alone. */
    public function label(): string
    {
        return match ($this) {
            self::Learned => 'Learned',
            self::StillDifficult => 'Still difficult',
        };
    }

    /**
     * The value a request sends to clear a status, alongside the two real
     * values.
     */
    public const string NONE = 'none';

    /**
     * @return list<string>
     */
    public static function requestValues(): array
    {
        return [self::Learned->value, self::StillDifficult->value, self::NONE];
    }

    /** The status a request value names, or null when it clears the status. */
    public static function fromRequestValue(?string $value): ?self
    {
        if ($value === null || $value === self::NONE) {
            return null;
        }

        $status = self::tryFrom($value);

        return $status ?? null;
    }
}
