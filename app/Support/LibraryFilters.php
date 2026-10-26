<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The filters and sort chosen on the Library, normalised once so the list,
 * the result count and the chips on screen all read the same state.
 *
 * Values that cannot be understood are ignored quietly: an unknown topic,
 * content type, status, sort, date or time zone simply falls back to the
 * default. A custom range whose end is before its start is swapped, so the
 * learner always gets the days they meant.
 */
final class LibraryFilters
{
    public const string DATE_ANY = 'any';

    public const string DATE_TODAY = 'today';

    public const string DATE_LAST_7 = 'last7';

    public const string DATE_LAST_30 = 'last30';

    public const string DATE_CUSTOM = 'custom';

    public const string STATUS_ALL = 'all';

    public const string VIEW_LIBRARY = 'library';

    public const string VIEW_ARCHIVED = 'archived';

    public const string SORT_NEWEST = 'newest';

    public const string SORT_OLDEST = 'oldest';

    public const string SORT_RECENTLY_EDITED = 'recently_edited';

    /** How many non-space characters a search needs before it runs. */
    public const int MIN_SEARCH_LENGTH = SearchText::MIN_LENGTH;

    /** The longest search the Library keeps. */
    public const int MAX_SEARCH_LENGTH = SearchText::MAX_LENGTH;

    /** @var list<int> */
    private array $topics = [];

    /** @var list<string> */
    private array $types = [];

    /** Which Library view is on screen: the normal one or the Archived one. */
    private string $view = self::VIEW_LIBRARY;

    private string $date = self::DATE_ANY;

    private ?string $from = null;

    private ?string $to = null;

    private string $status = self::STATUS_ALL;

    private string $sort = self::SORT_NEWEST;

    private string $timezone = 'UTC';

    /** The Library's search, trimmed and kept as typed. */
    private string $search = '';

    /** The Archived view's search, kept apart from the Library's. */
    private string $archivedSearch = '';

    /** @var list<string>|null */
    private ?array $searchTerms = null;

    private function __construct() {}

    /**
     * Read filters from request input. Accepted values are deliberately
     * forgiving: a hand-made or stale address never fails the page.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input, ?string $fallbackTimezone = null): self
    {
        $filters = new self;

        $filters->timezone = self::timezoneFrom($input['tz'] ?? $fallbackTimezone);
        $filters->view = self::viewFrom($input['view'] ?? null);
        $filters->topics = self::topicList($input['topics'] ?? null);
        $filters->types = self::typeList($input['types'] ?? null);
        // The Archived view holds only finished sessions, so a status filter
        // would have nothing to narrow and is quietly left at "all".
        $filters->status = $filters->archivedView()
            ? self::STATUS_ALL
            : self::statusFrom($input['status'] ?? null);
        $filters->sort = self::sortFrom($input['sort'] ?? null);
        $filters->search = self::searchFrom($input['q'] ?? null);
        $filters->archivedSearch = self::searchFrom($input['aq'] ?? null);

        $filters->from = self::dateValue($input['from'] ?? null);
        $filters->to = self::dateValue($input['to'] ?? null);

        if ($filters->from !== null && $filters->to !== null && $filters->to < $filters->from) {
            [$filters->from, $filters->to] = [$filters->to, $filters->from];
        }

        $filters->date = self::dateFrom(
            $input['date'] ?? null,
            $filters->from,
            $filters->to,
        );

        return $filters;
    }

    /**
     * A quiet filter state for one view, used when the page only needs the
     * count of that view (or all of its sessions).
     */
    public static function forView(string $view): self
    {
        $filters = new self;
        $filters->view = self::viewFrom($view);

        return $filters;
    }

    /**
     * Drop topic ids the learner can no longer choose, such as a topic that no
     * session uses any more. The URL is then quietly tidied the next time a
     * filter changes.
     *
     * @param  list<int>  $allowed
     */
    public function restrictTopics(array $allowed): self
    {
        $this->topics = array_values(array_filter(
            $this->topics,
            fn (int $topic): bool => in_array($topic, $allowed, true),
        ));

        return $this;
    }

    /**
     * @return list<int>
     */
    public function topics(): array
    {
        return $this->topics;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return $this->types;
    }

    public function date(): string
    {
        return $this->date;
    }

    public function from(): ?string
    {
        return $this->from;
    }

    public function to(): ?string
    {
        return $this->to;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function view(): string
    {
        return $this->view;
    }

    /** Whether the learner is looking at the Archived view. */
    public function archivedView(): bool
    {
        return $this->view === self::VIEW_ARCHIVED;
    }

    public function sort(): string
    {
        return $this->sort;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    /**
     * The words the view on screen is looking for. The Library and the
     * Archived view each remember their own search, so switching lists never
     * carries the words from one into the other.
     */
    public function search(): string
    {
        return $this->archivedView() ? $this->archivedSearch : $this->search;
    }

    /**
     * The separate words search asks for, normalised once.
     *
     * @return list<string>
     */
    public function searchTerms(): array
    {
        return $this->searchTerms ??= SearchText::terms($this->search());
    }

    /** Whether a search is in force. A search is not a filter chip. */
    public function hasSearch(): bool
    {
        return $this->search() !== '';
    }

    /** Whether anything narrows the list. Sorting never counts as a filter. */
    public function hasFilters(): bool
    {
        return $this->topics !== []
            || $this->types !== []
            || $this->date !== self::DATE_ANY
            || $this->status !== self::STATUS_ALL;
    }

    /**
     * The learner's days, converted to the UTC moments the database stores.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}|null
     */
    public function dateRange(): ?array
    {
        $today = CarbonImmutable::now($this->timezone)->startOfDay();

        return match ($this->date) {
            self::DATE_TODAY => [$today->utc(), $today->endOfDay()->utc()],
            self::DATE_LAST_7 => [$today->subDays(6)->utc(), $today->endOfDay()->utc()],
            self::DATE_LAST_30 => [$today->subDays(29)->utc(), $today->endOfDay()->utc()],
            self::DATE_CUSTOM => $this->customRange(),
            default => null,
        };
    }

    /**
     * The state the browser works with. Defaults are included so the client
     * never has to guess what the server did with an invalid value.
     *
     * @return array{
     *     topics: list<int>,
     *     types: list<string>,
     *     date: string,
     *     from: string|null,
     *     to: string|null,
     *     status: string,
     *     sort: string,
     *     q: string,
     *     archivedSearch: string,
     *     view: string
     * }
     */
    public function toArray(): array
    {
        return [
            'topics' => $this->topics,
            'types' => $this->types,
            'date' => $this->date,
            'from' => $this->from,
            'to' => $this->to,
            'status' => $this->status,
            'sort' => $this->sort,
            'q' => $this->search,
            'archivedSearch' => $this->archivedSearch,
            'view' => $this->view,
        ];
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}|null
     */
    private function customRange(): ?array
    {
        $start = $this->from === null
            ? null
            : $this->moment($this->from)->startOfDay();

        $end = $this->to === null
            ? null
            : $this->moment($this->to)->endOfDay();

        if ($start === null && $end === null) {
            return null;
        }

        return [$start?->utc(), $end?->utc()];
    }

    private function moment(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->timezone)
            ->startOfDay();
    }

    /**
     * Keep a search that can actually find something: trimmed, with its spaces
     * collapsed, at most the Library's limit, and at least two non-space
     * characters. Anything shorter is ignored, so typing one letter never
     * searches the whole Library.
     */
    private static function searchFrom(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $search = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($search === '') {
            return '';
        }

        if (mb_strlen($search) > self::MAX_SEARCH_LENGTH) {
            $search = mb_substr($search, 0, self::MAX_SEARCH_LENGTH);
        }

        $meaningful = (string) preg_replace('/\s+/u', '', $search);

        return mb_strlen($meaningful) >= self::MIN_SEARCH_LENGTH ? $search : '';
    }

    private static function dateFrom(mixed $value, ?string $from, ?string $to): string
    {
        $date = is_string($value) ? $value : '';

        if (in_array($date, [
            self::DATE_TODAY,
            self::DATE_LAST_7,
            self::DATE_LAST_30,
            self::DATE_CUSTOM,
        ], true)) {
            return $date === self::DATE_CUSTOM && $from === null && $to === null
                ? self::DATE_ANY
                : $date;
        }

        return $from !== null || $to !== null ? self::DATE_CUSTOM : self::DATE_ANY;
    }

    private static function statusFrom(mixed $value): string
    {
        return in_array($value, ['finished', 'unfinished'], true)
            ? $value
            : self::STATUS_ALL;
    }

    private static function viewFrom(mixed $value): string
    {
        return $value === self::VIEW_ARCHIVED
            ? self::VIEW_ARCHIVED
            : self::VIEW_LIBRARY;
    }

    private static function sortFrom(mixed $value): string
    {
        return in_array($value, [self::SORT_OLDEST, self::SORT_RECENTLY_EDITED], true)
            ? $value
            : self::SORT_NEWEST;
    }

    /**
     * @return list<int>
     */
    private static function topicList(mixed $value): array
    {
        $ids = [];

        foreach (self::listValues($value) as $item) {
            if (! is_string($item) && ! is_int($item)) {
                continue;
            }

            $text = trim((string) $item);

            if ($text !== '' && ctype_digit($text) && (int) $text > 0) {
                $ids[(int) $text] = (int) $text;
            }
        }

        $ids = array_values($ids);
        sort($ids);

        return array_slice($ids, 0, 100);
    }

    /**
     * @return list<string>
     */
    private static function typeList(mixed $value): array
    {
        $wanted = array_map(
            trim(...),
            array_filter(self::listValues($value), is_string(...)),
        );

        return array_values(array_filter(
            FeedbackType::values(),
            fn (string $type): bool => in_array($type, $wanted, true),
        ));
    }

    /**
     * Accepts `a,b` as well as repeated values (`types[]=a&types[]=b`).
     *
     * @return list<mixed>
     */
    private static function listValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        return is_string($value) ? explode(',', $value) : [];
    }

    private static function dateValue(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');

            return $date->format('Y-m-d') === $value ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function timezoneFrom(mixed $value): string
    {
        $fallback = config('app.timezone');
        $default = is_string($fallback) && self::isTimezone($fallback) ? $fallback : 'UTC';

        return is_string($value) && self::isTimezone($value) ? $value : $default;
    }

    private static function isTimezone(string $value): bool
    {
        return in_array($value, timezone_identifiers_list(), true);
    }
}
