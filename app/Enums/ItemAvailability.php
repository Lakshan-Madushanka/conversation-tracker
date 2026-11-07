<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether an item's own rows can be used, read from the sessions that hold it.
 *
 * `Available` means at least one row sits in a finished session that is not
 * archived, which is the same study-sessions rule Collections and Review use.
 * `Archived` means the item still exists but every finished session holding it
 * is archived, so a review keeps it and says it is not available right now.
 * `Missing` means nothing holds it any more, so no review may keep it.
 */
enum ItemAvailability: string
{
    case Available = 'available';

    case Archived = 'archived';

    case Missing = 'missing';

    /** Whether the item can be used in an attempt right now. */
    public function isAvailable(): bool
    {
        return $this === self::Available;
    }

    /** Whether the item exists but only inside archived sessions. */
    public function isArchived(): bool
    {
        return $this === self::Archived;
    }

    /** Whether the item was deleted and can never come back. */
    public function isMissing(): bool
    {
        return $this === self::Missing;
    }
}
