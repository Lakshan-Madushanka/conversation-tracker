/**
 * What each collection calls its things: the tab's columns, its quiet
 * wording, the friendly empty message, and the names of its parts. One
 * configuration per tab, so the shared list, panel and page stay the same
 * code and each collection gets its own words.
 *
 * The wording here follows the wording used elsewhere in the app: friendly,
 * calm, and never making the learner feel judged.
 */

import type { CollectionTabKey } from '@/types';

export interface CollectionTabConfig {
    /** One column headline per table column, in order. */
    columns: [string, string, string];
    /** The quiet text shown when the meaning column has nothing in it. */
    missingMeaning: string | null;
    /** The quiet text shown when a mistake's correction has nothing in it. */
    missingCorrection: string | null;
    /** The quiet text shown when a mistake has no reason written down. */
    missingReason: string;
    /** A short, friendly line the tab introduces itself with, when it has one. */
    intro: string | null;
    /** What one item of this collection is called, one at a time. */
    unit: string;
    /** What many items of this collection are called. */
    unitPlural: string;
    /** What the list says when the collection holds nothing yet. */
    emptyTitle: string;
    emptyDescription: string;
    /** What a stale address hears when its named item has moved on. */
    missingTitle: string;
    missingDescription: string;
    /** What the opened item says about itself. */
    panelDescription: string;
}

const configs: Record<CollectionTabKey, CollectionTabConfig> = {
    vocabulary: {
        columns: ['Word', 'Meaning', 'Example'],
        missingMeaning: 'Add the meaning',
        missingCorrection: null,
        missingReason: 'No reason added',
        intro: null,
        unit: 'word',
        unitPlural: 'words',
        emptyTitle: 'No words yet',
        emptyDescription:
            'Words you save while practicing will appear here, with their meanings and examples.',
        missingTitle: 'That word is no longer here',
        missingDescription:
            'It may have been edited or removed from its session since you saved this link. Your other words are still below.',
        panelDescription:
            'Everything you saved for this word, gathered from your finished sessions.',
    },

    phrases: {
        columns: ['Phrase', 'Meaning or use', 'Example'],
        missingMeaning: 'No meaning added',
        missingCorrection: null,
        missingReason: 'No reason added',
        intro: null,
        unit: 'phrase',
        unitPlural: 'phrases',
        emptyTitle: 'No phrases yet',
        emptyDescription:
            'Phrases you save in finished sessions will appear here.',
        missingTitle: 'That phrase is no longer here',
        missingDescription:
            'It may have been edited or removed from its session since you saved this link. Your other phrases are still below.',
        panelDescription:
            'Everything you saved for this phrase, gathered from your finished sessions.',
    },

    grammar: {
        columns: ['Note', 'Topic', 'Date'],
        missingMeaning: null,
        missingCorrection: null,
        missingReason: 'No reason added',
        intro: null,
        unit: 'note',
        unitPlural: 'notes',
        emptyTitle: 'No grammar notes yet',
        emptyDescription:
            'Notes you save in finished sessions will appear here.',
        missingTitle: 'That note is no longer here',
        missingDescription:
            'It may have been edited or removed from its session since you saved this link. Your other notes are still below.',
        panelDescription:
            'Everything this note says, as you saved it in its session.',
    },

    mistakes: {
        columns: ['What I said', 'Correction', 'Why'],
        missingMeaning: null,
        missingCorrection: 'Add the correction',
        missingReason: 'No reason added',
        intro: 'These are the mistakes you see most often. They’re the best things to practice, and spotting them is how you improve.',
        unit: 'mistake',
        unitPlural: 'mistakes',
        emptyTitle: 'Nothing here yet',
        emptyDescription:
            'Mistakes you save in finished sessions will appear here, with the ones you see most often first. Spotting them is how you improve.',
        missingTitle: 'That mistake is no longer here',
        missingDescription:
            'It may have been edited or removed from its session since you saved this link. Your other mistakes are still below.',
        panelDescription:
            'Everything about this mistake, gathered from your finished sessions.',
    },
};

/** The configuration of one tab, so shared pieces speak its language. */
export function collectionTabConfig(
    tab: CollectionTabKey,
): CollectionTabConfig {
    return configs[tab];
}
