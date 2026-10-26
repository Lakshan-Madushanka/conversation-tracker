import { nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    editorEntryId,
    readSearchTarget,
    revealSearchTarget,
} from '@/lib/search-target';

afterEach(() => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe('reading a search target from the address', () => {
    it('finds nothing when the address names no item', () => {
        expect(readSearchTarget('http://localhost/sessions/7')).toBeNull();
        expect(
            readSearchTarget('http://localhost/sessions/7?guided=1&question=1'),
        ).toBeNull();
    });

    it('reads every part of a target, including a zero-based entry position', () => {
        const target = readSearchTarget(
            'http://localhost/sessions/7?question=2&field=entry&type=mistake&entry=m1&position=0',
        );

        expect(target).toEqual({
            question: 2,
            field: 'entry',
            type: 'mistake',
            entry: 'm1',
            position: 0,
        });
    });

    it('ignores parts it cannot understand', () => {
        const target = readSearchTarget(
            'http://localhost/sessions/7?question=2&field=nowhere&type=nonsense&entry=&position=-1',
        );

        expect(target).toEqual({
            question: 2,
            field: null,
            type: null,
            entry: null,
            position: null,
        });
    });
});

describe('the id an editor item is known by', () => {
    it('builds the same id the editor builds', () => {
        expect(editorEntryId('q1', 'mistake', 'm1')).toBe(
            'feedback-q1-mistake-row-m1',
        );
        expect(editorEntryId('q1', 'vocabulary', 'v1')).toBe(
            'feedback-q1-vocabulary-row-v1',
        );
        expect(editorEntryId('q1', 'grammar_note', 'g1')).toBe(
            'feedback-q1-grammar_note-g1',
        );
        expect(editorEntryId('q1', 'natural_version', 'n1')).toBe(
            'feedback-q1-natural_version',
        );
    });
});

describe('showing a search target', () => {
    it('scrolls to it, focuses it and marks it briefly', async () => {
        vi.useFakeTimers();
        vi.stubGlobal('requestAnimationFrame', (callback: () => void) => {
            callback();

            return 0;
        });

        const element = document.createElement('div');
        element.id = 'the-item';
        document.body.append(element);

        const found = vi.fn();

        revealSearchTarget('the-item', found);
        await nextTick();

        expect(found).toHaveBeenCalledWith(true);
        expect(element.classList.contains('search-target')).toBe(true);
        expect(document.activeElement).toBe(element);

        vi.advanceTimersByTime(2500);

        expect(element.classList.contains('search-target')).toBe(false);
    });

    it('says calmly when the item is no longer there', async () => {
        vi.stubGlobal('requestAnimationFrame', (callback: () => void) => {
            callback();

            return 0;
        });

        const found = vi.fn();

        revealSearchTarget('missing', found, 0);
        await nextTick();

        expect(found).toHaveBeenCalledWith(false);
    });
});
