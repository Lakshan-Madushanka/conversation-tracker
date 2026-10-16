<?php

declare(strict_types=1);

namespace App\Actions\Topics;

use App\Models\Topic;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Finds the topic with this name, or creates it. Names that differ only by
 * capitalisation or spacing share one record; the first spelling used is kept
 * as the display name.
 */
final class FindOrCreateTopic
{
    public function handle(string $name): Topic
    {
        $name = Topic::normalizeName($name);
        $normalized = mb_strtolower($name);

        try {
            return Topic::query()->firstOrCreate(
                ['normalized_name' => $normalized],
                ['name' => $name],
            );
        } catch (UniqueConstraintViolationException) {
            // Another request created it a moment ago; use that one.
            return Topic::query()->where('normalized_name', $normalized)->firstOrFail();
        }
    }
}
