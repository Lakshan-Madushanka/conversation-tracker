import type {
    CollectionTopicOption,
    CustomReviewChoices,
    CustomReviewPreview,
} from '@/types';

/**
 * The choices the custom review page carries, shared by the controls, the
 * address and the page so they can never disagree.
 *
 * The address is the only place they live: a refresh keeps them and nothing is
 * saved as a preference. Defaults are left out of the address, so an untouched
 * page keeps a short one.
 */

/** How the choices are bounded by what the page offers. */
export interface CustomReviewChoiceOptions {
    /** The lengths a sitting can be asked for. */
    sizes: number[];
    /** The length the daily review uses, offered among them. */
    dailySize: number;
    /** The collections the page lists, in the order it lists them. */
    collections: string[];
}

/** A fresh set of choices: nothing restricted, the daily length. */
export function defaultCustomChoices(
    options: CustomReviewChoiceOptions,
): CustomReviewChoices {
    return {
        topics: [],
        collections: [],
        starred: false,
        learned: false,
        size: options.dailySize,
    };
}

/**
 * Keep a set of choices safe to show and to send. Values the page does not
 * offer fall back to their default, topics are whole positive numbers in a
 * stable order, and collections follow the order the page lists them.
 */
export function normalizeCustomChoices(
    choices: Partial<CustomReviewChoices> | null | undefined,
    options: CustomReviewChoiceOptions,
): CustomReviewChoices {
    const defaults = defaultCustomChoices(options);

    if (!choices) {
        return defaults;
    }

    const topics = Array.isArray(choices.topics)
        ? [...new Set(choices.topics.filter(isTopicId))].sort((a, b) => a - b)
        : [];

    const chosen = Array.isArray(choices.collections)
        ? [...new Set(choices.collections.filter(isCollectionValue(options)))]
        : [];

    return {
        topics,
        collections: options.collections.filter((value) =>
            chosen.includes(value),
        ),
        starred: choices.starred === true,
        learned: choices.learned === true,
        size: isSize(choices.size, options)
            ? (choices.size as number)
            : defaults.size,
    };
}

/** Replace the size, keeping every other choice. */
export function withCustomSize(
    choices: CustomReviewChoices,
    size: number,
): CustomReviewChoices {
    return { ...copyCustomChoices(choices), size };
}

/** Add or remove one topic, keeping every other choice. */
export function toggleCustomTopic(
    choices: CustomReviewChoices,
    topicId: number,
): CustomReviewChoices {
    const topics = choices.topics.includes(topicId)
        ? choices.topics.filter((id) => id !== topicId)
        : [...choices.topics, topicId];

    return { ...copyCustomChoices(choices), topics };
}

/** A stable copy, so a component can pass the choices around without sharing them. */
export function copyCustomChoices(
    choices: CustomReviewChoices,
): CustomReviewChoices {
    return {
        ...choices,
        topics: [...choices.topics],
        collections: [...choices.collections],
    };
}

/**
 * The address for one set of choices. Defaults are left out, so an untouched
 * page keeps a short address, and lists are joined with commas the way the
 * Collections page carries its own.
 */
export function customChoicesToQuery(
    choices: CustomReviewChoices,
    options: CustomReviewChoiceOptions,
): Record<string, string> {
    const query: Record<string, string> = {};

    if (choices.topics.length > 0) {
        query.topics = choices.topics.join(',');
    }

    if (choices.collections.length > 0) {
        query.collections = choices.collections.join(',');
    }

    if (choices.starred) {
        query.starred = '1';
    }

    if (choices.learned) {
        query.learned = '1';
    }

    if (choices.size !== options.dailySize) {
        query.size = String(choices.size);
    }

    return query;
}

/** How many choices are set, for the phone button that opens the panel. */
export function customChoiceCount(choices: CustomReviewChoices): number {
    return (
        choices.topics.length +
        choices.collections.length +
        (choices.starred ? 1 : 0) +
        (choices.learned ? 1 : 0)
    );
}

/** Clear every choice, leaving the length the learner asked for. */
export function clearCustomChoices(
    choices: CustomReviewChoices,
    options: CustomReviewChoiceOptions,
): CustomReviewChoices {
    return { ...defaultCustomChoices(options), size: choices.size };
}

/** The length a select shows, with the daily default named. */
export function customSizeLabel(size: number, dailySize: number): string {
    return size === dailySize ? `${size} items — daily` : `${size} items`;
}

/** The live count in words, for example "12 items match". */
export function customMatchLabel(count: number): string {
    return count === 1 ? '1 item matches' : `${count} items match`;
}

/**
 * What the learner could widen, for the friendly note when too few match.
 * Only the choices that could still let more items in are named.
 */
export function customWideningHints(
    choices: CustomReviewChoices,
    available: { topics: number; collections: number },
): string[] {
    const hints: string[] = [];

    if (choices.topics.length < available.topics) {
        hints.push('adding a topic');
    }

    if (
        choices.collections.length > 0 &&
        choices.collections.length < available.collections
    ) {
        hints.push('choosing more collections');
    }

    if (choices.starred) {
        hints.push('turning off Starred only');
    }

    if (!choices.learned) {
        hints.push('including Learned items');
    }

    return hints;
}

/** "a, b and c" for the widening hints, in words. */
export function customHintSentence(hints: string[]): string {
    if (hints.length === 0) {
        return '';
    }

    if (hints.length === 1) {
        return hints[0] ?? '';
    }

    if (hints.length === 2) {
        return `${hints[0]} or ${hints[1]}`;
    }

    return `${hints.slice(0, -1).join(', ')} or ${hints.at(-1)}`;
}

/**
 * Whether a partial response carries what the count needs. The page only ever
 * reads a count it can trust, so a response missing either half is refused.
 */
export function isCustomReviewRefresh(
    value: unknown,
): value is { preview: CustomReviewPreview; topics: CollectionTopicOption[] } {
    const props = value as { preview?: unknown; topics?: unknown };

    return isCustomReviewPreview(props.preview) && Array.isArray(props.topics);
}

function isCustomReviewPreview(value: unknown): value is CustomReviewPreview {
    const preview = value as Partial<CustomReviewPreview> | null;

    return (
        !!preview &&
        typeof preview.count === 'number' &&
        typeof preview.size === 'number' &&
        typeof preview.minutes === 'number' &&
        typeof preview.mix === 'string' &&
        typeof preview.comfortable === 'boolean' &&
        typeof preview.kinds === 'number'
    );
}

function isTopicId(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value > 0;
}

function isCollectionValue(
    options: CustomReviewChoiceOptions,
): (value: unknown) => value is string {
    return (value: unknown): value is string =>
        typeof value === 'string' && options.collections.includes(value);
}

function isSize(
    value: unknown,
    options: CustomReviewChoiceOptions,
): value is number {
    return typeof value === 'number' && options.sizes.includes(value);
}
