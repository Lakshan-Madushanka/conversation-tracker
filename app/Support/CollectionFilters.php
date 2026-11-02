<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CollectionSort;
use App\Enums\CollectionStatusFilter;
use App\Enums\FeedbackType;

/**
 * Everything that narrows or orders one collection, read once from the
 * address so the query, the chips and the result count all agree.
 *
 * Unknown values are ignored quietly. A stale bookmark opens the collection
 * with its default order rather than failing, and a sort a collection cannot
 * offer falls back to that default.
 */
final readonly class CollectionFilters
{
    /**
     * @param  list<int>  $topics
     */
    private function __construct(
        private string $search,
        private bool $starred,
        private CollectionStatusFilter $status,
        private bool $attention,
        private array $topics,
        private CollectionSort $sort,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input, FeedbackType $type): self
    {
        return new self(
            search: self::searchFrom($input['q'] ?? null),
            starred: self::truthy($input['starred'] ?? null),
            status: CollectionStatusFilter::fromQuery(
                is_string($input['status'] ?? null) ? $input['status'] : null,
            ),
            // Grammar notes never need attention, so the choice is dropped
            // there rather than shown as a filter that does nothing.
            attention: $type === FeedbackType::GrammarNote
                ? false
                : self::truthy($input['attention'] ?? null),
            topics: self::topicList($input['topics'] ?? null),
            sort: CollectionSort::fromQueryFor(
                is_string($input['sort'] ?? null) ? $input['sort'] : null,
                $type,
            ),
        );
    }

    /** The quiet state for one collection, with no search and no filters. */
    public static function for(FeedbackType $type): self
    {
        return new self(
            search: '',
            starred: false,
            status: CollectionStatusFilter::All,
            attention: false,
            topics: [],
            sort: CollectionSort::defaultFor($type),
        );
    }

    public function search(): string
    {
        return $this->search;
    }

    /**
     * The separate words search asks for, normalised once.
     *
     * @return list<string>
     */
    public function terms(): array
    {
        return SearchText::terms($this->search);
    }

    public function hasSearch(): bool
    {
        return $this->search !== '';
    }

    public function starred(): bool
    {
        return $this->starred;
    }

    public function status(): CollectionStatusFilter
    {
        return $this->status;
    }

    public function onlyNeedsAttention(): bool
    {
        return $this->attention;
    }

    /**
     * @return list<int>
     */
    public function topics(): array
    {
        return $this->topics;
    }

    public function sort(): CollectionSort
    {
        return $this->sort;
    }

    /** Whether a filter narrows the collection. Search and sort are separate. */
    public function hasFilters(): bool
    {
        return $this->starred
            || $this->status !== CollectionStatusFilter::All
            || $this->attention
            || $this->topics !== [];
    }

    /**
     * Drop topics that no longer feed this collection, so a stale bookmark
     * opens the list it can actually show rather than a dead end.
     *
     * @param  list<int>  $allowed
     */
    public function restrictTopics(array $allowed): self
    {
        if ($this->topics === []) {
            return $this;
        }

        $keep = array_flip($allowed);
        $topics = array_values(array_filter(
            $this->topics,
            fn (int $id): bool => isset($keep[$id]),
        ));

        if ($topics === $this->topics) {
            return $this;
        }

        return new self(
            search: $this->search,
            starred: $this->starred,
            status: $this->status,
            attention: $this->attention,
            topics: $topics,
            sort: $this->sort,
        );
    }

    /** The same state with only the "needs attention" choice switched on. */
    public function withOnlyNeedsAttention(): self
    {
        return new self(
            search: '',
            starred: false,
            status: CollectionStatusFilter::All,
            attention: true,
            topics: [],
            sort: $this->sort,
        );
    }

    /** Whether the list is narrower than the whole collection. */
    public function isFiltered(): bool
    {
        return $this->hasFilters() || $this->hasSearch();
    }

    /**
     * The state the browser works with, including defaults, so the screen
     * never has to guess what the server did with an invalid value.
     *
     * @return array{
     *     q: string,
     *     starred: bool,
     *     status: string,
     *     attention: bool,
     *     topics: list<int>,
     *     sort: string
     * }
     */
    public function toArray(): array
    {
        return [
            'q' => $this->search,
            'starred' => $this->starred,
            'status' => $this->status->value,
            'attention' => $this->attention,
            'topics' => $this->topics,
            'sort' => $this->sort->value,
        ];
    }

    /**
     * Keep a search that can actually find something: trimmed, with its spaces
     * collapsed, at most the shared limit, and at least two non-space
     * characters. Anything shorter is ignored.
     */
    private static function searchFrom(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $search = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (mb_strlen($search) > SearchText::MAX_LENGTH) {
            $search = mb_substr($search, 0, SearchText::MAX_LENGTH);
        }

        $meaningful = (string) preg_replace('/\s+/u', '', $search);

        return mb_strlen($meaningful) >= SearchText::MIN_LENGTH ? $search : '';
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || (is_string($value) && in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true));
    }

    /**
     * Accepts `1,2` as well as repeated values (`topics[]=1&topics[]=2`).
     *
     * @return list<int>
     */
    private static function topicList(mixed $value): array
    {
        $values = is_array($value)
            ? array_values($value)
            : (is_string($value) ? explode(',', $value) : []);

        $ids = [];

        foreach ($values as $candidate) {
            if (! is_string($candidate) && ! is_int($candidate)) {
                continue;
            }

            $text = trim((string) $candidate);

            if ($text !== '' && ctype_digit($text) && (int) $text > 0) {
                $ids[(int) $text] = (int) $text;
            }
        }

        $ids = array_values($ids);
        sort($ids);

        return array_slice($ids, 0, 100);
    }
}
