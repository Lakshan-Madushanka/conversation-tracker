<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CustomReviewSort;
use App\Enums\CustomReviewStatus;

/**
 * The search, statuses and order chosen on the Custom reviews listing,
 * normalised once so the list, the count and the chips on screen all read the
 * same state.
 *
 * Values that cannot be understood are ignored quietly: an unknown status or
 * sort simply falls back to what the listing shows by default, so a stale
 * bookmark never fails the page.
 */
final readonly class CustomReviewFilters
{
    /**
     * @param  list<CustomReviewStatus>  $statuses
     */
    private function __construct(
        private string $search,
        private array $statuses,
        private CustomReviewSort $sort,
    ) {}

    /**
     * Read the listing's choices from request input.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            SearchText::keptSearch($input['q'] ?? null),
            self::statusList($input['status'] ?? null),
            CustomReviewSort::fromInput($input['sort'] ?? null),
        );
    }

    /** The search as it is kept in the address, or an empty string. */
    public function search(): string
    {
        return $this->search;
    }

    /**
     * The separate words search asks for.
     *
     * @return list<string>
     */
    public function searchTerms(): array
    {
        return SearchText::terms($this->search);
    }

    /**
     * The statuses narrowing the list, in the order the filter shows them.
     *
     * @return list<CustomReviewStatus>
     */
    public function statuses(): array
    {
        return $this->statuses;
    }

    /**
     * The statuses as they travel to the query.
     *
     * @return list<string>
     */
    public function statusValues(): array
    {
        return array_map(fn (CustomReviewStatus $status): string => $status->value, $this->statuses);
    }

    public function sort(): CustomReviewSort
    {
        return $this->sort;
    }

    /** Whether a search is in force. A search is not a filter chip. */
    public function hasSearch(): bool
    {
        return $this->search !== '';
    }

    /** Whether any status chip is on. */
    public function hasStatuses(): bool
    {
        return $this->statuses !== [];
    }

    /** Whether anything narrows the list. Sorting never counts as a filter. */
    public function isNarrowed(): bool
    {
        return $this->hasSearch() || $this->hasStatuses();
    }

    /**
     * The state the browser works with, so the page and the address never
     * disagree about what the server understood.
     *
     * @return array{
     *     q: string,
     *     statuses: list<string>,
     *     sort: string
     * }
     */
    public function toArray(): array
    {
        return [
            'q' => $this->search,
            'statuses' => $this->statusValues(),
            'sort' => $this->sort->value,
        ];
    }

    /**
     * Accepts `draft,finished` as well as repeated values
     * (`status[]=draft&status[]=finished`).
     *
     * @return list<CustomReviewStatus>
     */
    private static function statusList(mixed $value): array
    {
        $wanted = is_array($value)
            ? array_values($value)
            : (is_string($value) ? explode(',', $value) : []);

        $wanted = array_map(
            trim(...),
            array_filter($wanted, is_string(...)),
        );

        return array_values(array_filter(
            CustomReviewStatus::ordered(),
            fn (CustomReviewStatus $status): bool => in_array($status->value, $wanted, true),
        ));
    }
}
