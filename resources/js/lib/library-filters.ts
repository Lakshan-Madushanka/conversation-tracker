import type { FeedbackTone } from '@/components/SectionLabel.vue';
import type { FeedbackType, TopicSummary } from '@/types';

/** The choices the Library offers, shared by the controls, chips and URL. */

export type LibraryDateFilter = 'any' | 'today' | 'last7' | 'last30' | 'custom';

export type LibraryStatusFilter = 'all' | 'finished' | 'unfinished';

export type LibrarySort = 'newest' | 'oldest' | 'recently_edited';

export interface LibraryFilters {
    topics: number[];
    types: FeedbackType[];
    date: LibraryDateFilter;
    from: string | null;
    to: string | null;
    status: LibraryStatusFilter;
    sort: LibrarySort;
}

export interface ContentTypeOption {
    type: FeedbackType;
    label: string;
    tone: FeedbackTone;
}

/** The content types a session can be filtered by, in the order shown. */
export const contentTypes: readonly ContentTypeOption[] = [
    { type: 'mistake', label: 'Mistakes', tone: 'mistakes' },
    { type: 'vocabulary', label: 'Vocabulary', tone: 'vocabulary' },
    { type: 'phrase', label: 'Phrases', tone: 'phrases' },
    { type: 'grammar_note', label: 'Grammar notes', tone: 'grammar' },
    {
        type: 'natural_version',
        label: 'More natural versions',
        tone: 'natural',
    },
];

export const dateChoices: ReadonlyArray<{
    value: Exclude<LibraryDateFilter, 'custom'>;
    label: string;
}> = [
    { value: 'any', label: 'Any time' },
    { value: 'today', label: 'Today' },
    { value: 'last7', label: 'Last 7 days' },
    { value: 'last30', label: 'Last 30 days' },
];

export const statusChoices: ReadonlyArray<{
    value: LibraryStatusFilter;
    label: string;
}> = [
    { value: 'all', label: 'All sessions' },
    { value: 'finished', label: 'Finished' },
    { value: 'unfinished', label: 'Unfinished' },
];

export const sortChoices: ReadonlyArray<{
    value: LibrarySort;
    label: string;
}> = [
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'recently_edited', label: 'Recently edited' },
];

/** A fresh state: no filters, and the Library's usual newest-first order. */
export function defaultLibraryFilters(): LibraryFilters {
    return {
        topics: [],
        types: [],
        date: 'any',
        from: null,
        to: null,
        status: 'all',
        sort: 'newest',
    };
}

/**
 * Keep a state safe to send and to show. Values this app does not know fall
 * back to their default, a custom range without days stops being custom, and
 * a range whose end is before its start is swapped.
 */
export function normalizeLibraryFilters(
    filters: Partial<LibraryFilters> | null | undefined,
): LibraryFilters {
    const defaults = defaultLibraryFilters();

    if (!filters) {
        return defaults;
    }

    const topics = Array.isArray(filters.topics)
        ? [...new Set(filters.topics.filter(isTopicId))].sort((a, b) => a - b)
        : [];

    const types = contentTypes
        .map((option) => option.type)
        .filter((type) => filters.types?.includes(type));

    const from = isIsoDay(filters.from) ? filters.from : null;
    const to = isIsoDay(filters.to) ? filters.to : null;

    let date: LibraryDateFilter =
        filters.date === 'today' ||
        filters.date === 'last7' ||
        filters.date === 'last30' ||
        filters.date === 'custom'
            ? filters.date
            : from !== null || to !== null
              ? 'custom'
              : 'any';

    if (date === 'custom' && from === null && to === null) {
        date = 'any';
    }

    const [start, end] =
        from !== null && to !== null && to < from ? [to, from] : [from, to];

    return {
        topics,
        types,
        date,
        from: date === 'custom' || start !== null ? start : null,
        to: date === 'custom' || end !== null ? end : null,
        status:
            filters.status === 'finished' || filters.status === 'unfinished'
                ? filters.status
                : 'all',
        sort:
            filters.sort === 'oldest' || filters.sort === 'recently_edited'
                ? filters.sort
                : 'newest',
    };
}

/** A stable copy, so a component can pass a state around without sharing it. */
export function copyLibraryFilters(filters: LibraryFilters): LibraryFilters {
    return {
        ...filters,
        topics: [...filters.topics],
        types: [...filters.types],
    };
}

/**
 * The address for one filter state. Defaults are left out, so an unfiltered
 * Library keeps a short address. The learner's timezone travels with the
 * filters because "today" depends on where they are.
 */
export function filtersToQuery(
    filters: LibraryFilters,
    timezone: string,
): Record<string, string> {
    const query: Record<string, string> = {};

    if (filters.topics.length > 0) {
        query.topics = filters.topics.join(',');
    }

    if (filters.types.length > 0) {
        query.types = filters.types.join(',');
    }

    if (filters.date !== 'any') {
        query.date = filters.date;

        if (filters.date === 'custom') {
            if (filters.from !== null) {
                query.from = filters.from;
            }

            if (filters.to !== null) {
                query.to = filters.to;
            }
        }
    }

    if (filters.status !== 'all') {
        query.status = filters.status;
    }

    if (filters.sort !== 'newest') {
        query.sort = filters.sort;
    }

    if (timezone !== '') {
        query.tz = timezone;
    }

    return query;
}

/** Whether anything narrows the list. The sort never counts as a filter. */
export function hasActiveFilters(filters: LibraryFilters): boolean {
    return activeFilterCount(filters) > 0;
}

/** How many separate filters are active, shown on the phone Filters button. */
export function activeFilterCount(filters: LibraryFilters): number {
    return (
        filters.topics.length +
        filters.types.length +
        (filters.date === 'any' ? 0 : 1) +
        (filters.status === 'all' ? 0 : 1)
    );
}

export function withSort(
    filters: LibraryFilters,
    sort: LibrarySort,
): LibraryFilters {
    return { ...copyLibraryFilters(filters), sort };
}

/** Clear every filter while keeping the order the learner chose. */
export function clearLibraryFilters(filters: LibraryFilters): LibraryFilters {
    return { ...defaultLibraryFilters(), sort: filters.sort };
}

export type LibraryFilterChip =
    | { kind: 'topic'; key: string; label: string; value: number }
    | { kind: 'type'; key: string; label: string; value: FeedbackType }
    | { kind: 'date'; key: string; label: string }
    | { kind: 'status'; key: string; label: string };

/**
 * One chip per active choice. Topics follow the topic picker's order, so the
 * chips stay predictable even when they were chosen in another order.
 */
export function libraryFilterChips(
    filters: LibraryFilters,
    topics: TopicSummary[],
): LibraryFilterChip[] {
    const chips: LibraryFilterChip[] = [];

    for (const topic of topics) {
        if (filters.topics.includes(topic.id)) {
            chips.push({
                kind: 'topic',
                key: `topic:${topic.id}`,
                label: `Topic: ${topic.name}`,
                value: topic.id,
            });
        }
    }

    for (const option of contentTypes) {
        if (filters.types.includes(option.type)) {
            chips.push({
                kind: 'type',
                key: `type:${option.type}`,
                label: option.label,
                value: option.type,
            });
        }
    }

    if (filters.date !== 'any') {
        chips.push({
            kind: 'date',
            key: 'date',
            label: dateFilterLabel(filters),
        });
    }

    if (filters.status !== 'all') {
        const choice = statusChoices.find(
            (option) => option.value === filters.status,
        );

        chips.push({
            kind: 'status',
            key: 'status',
            label: choice?.label ?? 'Finished',
        });
    }

    return chips;
}

/** Remove one chip's choice, leaving every other filter untouched. */
export function removeFilterChip(
    filters: LibraryFilters,
    chip: LibraryFilterChip,
): LibraryFilters {
    const next = copyLibraryFilters(filters);

    if (chip.kind === 'topic') {
        next.topics = next.topics.filter((id) => id !== chip.value);
    }

    if (chip.kind === 'type') {
        next.types = next.types.filter((type) => type !== chip.value);
    }

    if (chip.kind === 'date') {
        next.date = 'any';
        next.from = null;
        next.to = null;
    }

    if (chip.kind === 'status') {
        next.status = 'all';
    }

    return next;
}

/** The short wording on the date control and its chip. */
export function dateFilterLabel(filters: LibraryFilters): string {
    if (filters.date === 'custom') {
        return dateRangeLabel(filters.from, filters.to);
    }

    return (
        dateChoices.find((choice) => choice.value === filters.date)?.label ??
        'Any time'
    );
}

export function dateRangeLabel(from: string | null, to: string | null): string {
    if (from !== null && to !== null) {
        return `${formatDay(from)} – ${formatDay(to)}`;
    }

    if (from !== null) {
        return `From ${formatDay(from)}`;
    }

    if (to !== null) {
        return `Until ${formatDay(to)}`;
    }

    return 'Custom range';
}

/** The count shown in the header and announced after the list changes. */
export function sessionCountLabel(
    total: number,
    allTotal: number,
    filtered: boolean,
): string {
    if (filtered) {
        return `${total} of ${allTotal} ${allTotal === 1 ? 'session' : 'sessions'}`;
    }

    return `${allTotal} ${allTotal === 1 ? 'session' : 'sessions'}`;
}

export function sortLabel(sort: LibrarySort): string {
    return (
        sortChoices
            .find((choice) => choice.value === sort)
            ?.label.toLowerCase() ?? 'newest first'
    );
}

/** The learner's own zone, so "today" means their today. */
export function browserTimezone(): string {
    if (typeof Intl === 'undefined') {
        return 'UTC';
    }

    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
}

function formatDay(value: string): string {
    const date = new Date(`${value}T00:00:00Z`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(date);
}

function isTopicId(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value > 0;
}

function isIsoDay(value: unknown): value is string {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/u.test(value);
}
