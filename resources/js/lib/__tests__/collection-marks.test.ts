import { describe, expect, it } from 'vite-plus/test';
import {
    markItemName,
    markKindForTab,
    starAriaLabel,
    statusAriaLabel,
} from '@/lib/collection-marks';
import type {
    CollectionItem,
    CollectionMistakeItem,
    CollectionNoteItem,
    ItemMarkData,
} from '@/types';

const mark: ItemMarkData = {
    starred: false,
    status: null,
    has_notes: false,
    notes_version: 0,
    notes_html: '',
};

describe('mapping the collections onto the marks endpoints', () => {
    it("names each tab's kind by the backend's word", () => {
        expect(markKindForTab('vocabulary')).toBe('vocabulary');
        expect(markKindForTab('phrases')).toBe('phrase');
        expect(markKindForTab('grammar')).toBe('grammar_note');
        expect(markKindForTab('mistakes')).toBe('mistake');
    });

    it('calls a word, a phrase and a mistake by what they say', () => {
        const word = {
            key: 'soup',
            title: 'soup',
            starred: true,
            status: 'learned',
            has_notes: false,
        } as CollectionItem;
        const said = {
            key: 'mistake-agree',
            title: 'I am agree',
            correction: 'I agree',
            starred: false,
            status: null,
            has_notes: false,
        } as CollectionMistakeItem;

        expect(markItemName(word)).toBe('soup');
        expect(markItemName(said)).toBe('I am agree');
    });

    it('calls a note by its words', () => {
        const note = {
            key: 'note-7',
            text: 'Use the past tense here.',
            starred: false,
            status: null,
            has_notes: false,
        } as CollectionNoteItem;

        expect(markItemName(note)).toBe('Use the past tense here.');
    });

    it("reads the star and status names with the collection's own word", () => {
        expect(starAriaLabel('word', 'reluctant', false)).toBe(
            'Star the word reluctant',
        );
        expect(starAriaLabel('word', 'reluctant', true)).toBe(
            'Unstar the word reluctant',
        );
        expect(statusAriaLabel('mistake', 'I am agree')).toBe(
            'Change the status of the mistake I am agree',
        );
    });

    it('keeps the mark shape the server answers with', () => {
        expect(mark).toMatchObject({
            starred: false,
            notes_version: 0,
        });
    });
});
