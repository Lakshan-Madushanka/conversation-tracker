<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * A short label a session is about, reusable across sessions.
 *
 * @property int $id
 * @property string $name
 * @property string $normalized_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Topic extends Model
{
    /** @use HasFactory<TopicFactory> */
    use HasFactory;

    /**
     * Keep the display name tidy and derive the key that spots duplicates, so
     * "Travel", "travel" and " Travel " all point at the same topic.
     */
    public function setNameAttribute(string $value): void
    {
        $name = self::normalizeName($value);

        $this->attributes['name'] = $name;
        $this->attributes['normalized_name'] = mb_strtolower($name);
    }

    /**
     * Trim and collapse whitespace, keeping the original spelling of words.
     */
    public static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Find the topic with this name, or create it. The first spelling used is
     * kept as the display name.
     */
    public static function findOrCreate(string $name): self
    {
        $name = self::normalizeName($name);
        $normalized = mb_strtolower($name);

        try {
            return self::query()->firstOrCreate(
                ['normalized_name' => $normalized],
                ['name' => $name],
            );
        } catch (UniqueConstraintViolationException) {
            // Another request created it a moment ago; use that one.
            return self::query()->where('normalized_name', $normalized)->firstOrFail();
        }
    }
}
