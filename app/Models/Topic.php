<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\SearchText;
use Database\Factories\TopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A short label a session is about, reusable across sessions.
 *
 * @property int $id
 * @property string $name
 * @property string $normalized_name
 * @property string|null $search_text
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
     *
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(set: function (string $value): array {
            $name = self::normalizeName($value);

            return [
                'name' => $name,
                'normalized_name' => mb_strtolower($name),
                'search_text' => SearchText::normalize($name),
            ];
        });
    }

    /**
     * Trim and collapse whitespace, keeping the original spelling of words.
     */
    public static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * The topics offered in the picker, ordered by name.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function pickerOptions(): array
    {
        return self::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (self $topic): array => [
                'id' => $topic->id,
                'name' => $topic->name,
            ])
            ->values()
            ->all();
    }
}
