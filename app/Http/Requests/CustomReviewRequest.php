<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ReviewCollection;
use App\Support\ReviewFilters;
use App\Support\ReviewSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the custom review page reads from its address: the topics, the
 * collections, the two marks choices and how many items were asked for.
 *
 * The choices travel in the address and are never saved, so a refresh keeps the
 * sitting the learner was about to start. An unknown value is not an error: a
 * topic that has gone or a collection that is not offered is quietly dropped,
 * exactly as the Collections page treats a stale bookmark, and the page's own
 * count then says what is left.
 */
final class CustomReviewRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Only the size is bounded: it decides how many items a started
            // sitting holds, and a sitting is only ever one of the offered
            // lengths.
            'size' => ['nullable', 'integer', Rule::in(ReviewSettings::CUSTOM_SIZES)],
        ];
    }

    /** The choices as the selection piece understands them. */
    public function filters(): ReviewFilters
    {
        return ReviewFilters::custom(
            topicIds: $this->idList('topics'),
            collections: $this->collectionChoices(),
            starredOnly: $this->isSet('starred'),
            includeLearned: $this->isSet('learned'),
        );
    }

    /** How many items the sitting was asked for, the daily default when none. */
    public function size(): int
    {
        $size = (int) $this->input('size');

        return in_array($size, ReviewSettings::CUSTOM_SIZES, true)
            ? $size
            : ReviewSettings::SIZE;
    }

    /**
     * The ids an address carries, whether as a list or as a comma-separated
     * string, deduplicated and in a stable order.
     *
     * @return list<int>
     */
    private function idList(string $key): array
    {
        $values = $this->pieces($key);
        $ids = [];

        foreach ($values as $candidate) {
            $text = trim((string) $candidate);

            if ($text !== '' && ctype_digit($text) && (int) $text > 0) {
                $ids[(int) $text] = (int) $text;
            }
        }

        $ids = array_values($ids);
        sort($ids);

        return $ids;
    }

    /**
     * The collections an address names, in the order the page lists them.
     *
     * @return list<ReviewCollection>
     */
    private function collectionChoices(): array
    {
        $chosen = [];

        foreach ($this->pieces('collections') as $candidate) {
            $collection = ReviewCollection::tryFrom(trim((string) $candidate));

            if ($collection instanceof ReviewCollection) {
                $chosen[$collection->value] = $collection;
            }
        }

        $order = array_flip(ReviewCollection::values());
        $list = array_values($chosen);

        usort($list, fn (ReviewCollection $a, ReviewCollection $b): int => $order[$a->value] <=> $order[$b->value]);

        return $list;
    }

    /** Whether the address switches one of the two marks choices on. */
    private function isSet(string $key): bool
    {
        $value = $this->input($key);

        return $value === true
            || $value === 1
            || (is_string($value) && in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true));
    }

    /**
     * The raw list behind a choice, split however the address carries it.
     *
     * @return list<mixed>
     */
    private function pieces(string $key): array
    {
        $value = $this->input($key);

        if (is_array($value)) {
            return array_values($value);
        }

        return is_string($value) ? explode(',', $value) : [];
    }
}
