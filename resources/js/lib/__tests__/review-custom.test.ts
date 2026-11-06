import { describe, expect, it } from 'vite-plus/test';
import {
    clearCustomChoices,
    copyCustomChoices,
    customChoiceCount,
    customChoicesToQuery,
    customHintSentence,
    customMatchLabel,
    customSizeLabel,
    customWideningHints,
    type CustomReviewChoiceOptions,
    defaultCustomChoices,
    isCustomReviewRefresh,
    normalizeCustomChoices,
    toggleCustomTopic,
    withCustomSize,
} from '@/lib/review-custom';

import type { CustomReviewChoices } from '@/types';

const options: CustomReviewChoiceOptions = {
    sizes: [5, 8, 10, 15, 20],
    dailySize: 8,
    collections: ['words', 'phrases', 'grammar_notes', 'mistakes', 'questions'],
};

function choices(
    overrides: Partial<CustomReviewChoices> = {},
): CustomReviewChoices {
    return { ...defaultCustomChoices(options), ...overrides };
}

function preview(overrides: Record<string, unknown> = {}) {
    return {
        count: 12,
        size: 8,
        minutes: 6,
        mix: '5 flashcards, 3 mistakes to fix',
        comfortable: true,
        kinds: 3,
        ...overrides,
    };
}

describe('the starting choices', () => {
    it('opens with nothing restricted and the daily length', () => {
        expect(defaultCustomChoices(options)).toEqual({
            topics: [],
            collections: [],
            starred: false,
            learned: false,
            size: 8,
        });
    });

    it('names the daily length it was given', () => {
        expect(defaultCustomChoices({ ...options, dailySize: 15 }).size).toBe(
            15,
        );
    });
});

describe('keeping the choices safe', () => {
    it('leaves a page with nothing to normalize at the defaults', () => {
        expect(normalizeCustomChoices(null, options)).toEqual(
            defaultCustomChoices(options),
        );
        expect(normalizeCustomChoices(undefined, options)).toEqual(
            defaultCustomChoices(options),
        );
    });

    it('keeps whole positive topics once each, in a stable order', () => {
        expect(
            normalizeCustomChoices(
                {
                    topics: [5, 2, 2, -1, 0, 1.5, 'travel', null] as never,
                },
                options,
            ).topics,
        ).toEqual([2, 5]);
    });

    it('drops collections the page does not offer and follows its order', () => {
        expect(
            normalizeCustomChoices(
                { collections: ['mistakes', 'words', 'banana', 'words'] },
                options,
            ).collections,
        ).toEqual(['words', 'mistakes']);
    });

    it('reads the switches as booleans', () => {
        const normalized = normalizeCustomChoices(
            { starred: 'yes' as never, learned: true },
            options,
        );

        expect(normalized.starred).toBe(false);
        expect(normalized.learned).toBe(true);
    });

    it('keeps a length the page offers and falls back to the daily one', () => {
        expect(normalizeCustomChoices({ size: 20 }, options).size).toBe(20);
        expect(normalizeCustomChoices({ size: 7 }, options).size).toBe(8);
        expect(
            normalizeCustomChoices({ size: '10' as never }, options).size,
        ).toBe(8);
    });
});

describe('changing one choice', () => {
    it('replaces the length and keeps every other choice', () => {
        const next = withCustomSize(
            choices({ starred: true, topics: [2] }),
            15,
        );

        expect(next).toEqual({
            topics: [2],
            collections: [],
            starred: true,
            learned: false,
            size: 15,
        });
    });

    it('adds a topic and takes the same one back off', () => {
        const added = toggleCustomTopic(choices(), 2);
        const removed = toggleCustomTopic(added, 2);

        expect(added.topics).toEqual([2]);
        expect(choices().topics).toEqual([]);
        expect(removed.topics).toEqual([]);
    });

    it('hands out a copy, not the same lists', () => {
        const original = choices({ topics: [2], collections: ['words'] });
        const copy = copyCustomChoices(original);

        copy.topics.push(5);
        copy.collections.push('mistakes');

        expect(original.topics).toEqual([2]);
        expect(original.collections).toEqual(['words']);
    });

    it('clears every choice but keeps the length asked for', () => {
        expect(
            clearCustomChoices(
                choices({ topics: [2], starred: true, size: 20 }),
                options,
            ),
        ).toEqual({
            topics: [],
            collections: [],
            starred: false,
            learned: false,
            size: 20,
        });
    });
});

describe('the address the choices travel in', () => {
    it('leaves every default out', () => {
        expect(customChoicesToQuery(choices(), options)).toEqual({});
    });

    it('carries the sets the learner chose', () => {
        expect(
            customChoicesToQuery(
                choices({
                    topics: [2, 5],
                    collections: ['words', 'mistakes'],
                    starred: true,
                    learned: true,
                    size: 15,
                }),
                options,
            ),
        ).toEqual({
            topics: '2,5',
            collections: 'words,mistakes',
            starred: '1',
            learned: '1',
            size: '15',
        });
    });

    it('keeps the daily length out of the address', () => {
        expect(
            customChoicesToQuery(choices({ size: 8 }), options).size,
        ).toBeUndefined();
        expect(customChoicesToQuery(choices({ size: 5 }), options).size).toBe(
            '5',
        );
    });

    it('round-trips through the address', () => {
        const chosen = choices({
            topics: [5, 2],
            collections: ['mistakes', 'words'],
            starred: true,
        });
        const query = customChoicesToQuery(chosen, options);
        const restored = normalizeCustomChoices(
            {
                topics: query.topics?.split(',').map(Number) ?? [],
                collections: query.collections?.split(',') ?? [],
                starred: query.starred === '1',
                learned: query.learned === '1',
                size: query.size ? Number(query.size) : options.dailySize,
            },
            options,
        );

        expect(restored.topics).toEqual([2, 5]);
        expect(restored.collections).toEqual(['words', 'mistakes']);
        expect(restored.starred).toBe(true);
    });

    it('counts only the choices that narrow the sitting', () => {
        expect(customChoiceCount(choices())).toBe(0);
        expect(
            customChoiceCount(
                choices({
                    topics: [2, 5],
                    collections: ['words'],
                    starred: true,
                    size: 20,
                }),
            ),
        ).toBe(4);
    });
});

describe('the words on the page', () => {
    it('names the daily length among the options', () => {
        expect(customSizeLabel(8, 8)).toBe('8 items — daily');
        expect(customSizeLabel(20, 8)).toBe('20 items');
    });

    it('agrees with itself on how many match', () => {
        expect(customMatchLabel(0)).toBe('0 items match');
        expect(customMatchLabel(1)).toBe('1 item matches');
        expect(customMatchLabel(12)).toBe('12 items match');
    });
});

describe('what the learner could widen', () => {
    it('names the choices that could still let more items in', () => {
        expect(
            customWideningHints(choices(), { topics: 3, collections: 5 }),
        ).toEqual(['adding a topic', 'including Learned items']);
    });

    it('says nothing about a choice that already lets everything in', () => {
        expect(
            customWideningHints(
                choices({
                    topics: [1, 2, 3],
                    collections: [
                        'words',
                        'phrases',
                        'grammar_notes',
                        'mistakes',
                        'questions',
                    ],
                    learned: true,
                }),
                { topics: 3, collections: 5 },
            ),
        ).toEqual([]);
    });

    it('only offers more collections once some are chosen', () => {
        expect(
            customWideningHints(choices({ collections: ['words'] }), {
                topics: 0,
                collections: 5,
            }),
        ).toEqual(['choosing more collections', 'including Learned items']);
    });

    it('offers turning the star filter off while it is on', () => {
        expect(
            customWideningHints(
                choices({ starred: true, learned: true, topics: [1] }),
                {
                    topics: 1,
                    collections: 5,
                },
            ),
        ).toEqual(['turning off Starred only']);
    });

    it('joins the hints in words', () => {
        expect(customHintSentence([])).toBe('');
        expect(customHintSentence(['adding a topic'])).toBe('adding a topic');
        expect(
            customHintSentence(['adding a topic', 'including Learned items']),
        ).toBe('adding a topic or including Learned items');
        expect(
            customHintSentence([
                'adding a topic',
                'choosing more collections',
                'including Learned items',
            ]),
        ).toBe(
            'adding a topic, choosing more collections or including Learned items',
        );
    });
});

describe('a count the page can trust', () => {
    it('accepts a response that carries both halves', () => {
        expect(isCustomReviewRefresh({ preview: preview(), topics: [] })).toBe(
            true,
        );
    });

    it('refuses a response missing either half', () => {
        expect(isCustomReviewRefresh({ preview: preview() })).toBe(false);
        expect(isCustomReviewRefresh({ topics: [] })).toBe(false);
        expect(isCustomReviewRefresh({})).toBe(false);
    });

    it('refuses a count that is not a number or is missing a field', () => {
        expect(
            isCustomReviewRefresh({
                preview: preview({ count: '12' }),
                topics: [],
            }),
        ).toBe(false);
        expect(
            isCustomReviewRefresh({
                preview: preview({ comfortable: undefined }),
                topics: [],
            }),
        ).toBe(false);
        expect(
            isCustomReviewRefresh({
                preview: preview({ kinds: null }),
                topics: [],
            }),
        ).toBe(false);
        expect(isCustomReviewRefresh({ preview: null, topics: [] })).toBe(
            false,
        );
    });

    it('refuses topics that are not a list', () => {
        expect(
            isCustomReviewRefresh({ preview: preview(), topics: 'not-a-list' }),
        ).toBe(false);
    });
});
