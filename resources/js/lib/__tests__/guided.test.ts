import { describe, expect, it } from 'vite-plus/test';
import { emptyFeedbackValues, feedbackBoxFor, newNote } from '@/lib/feedback';
import {
    editorUrl,
    feedbackTypeFor,
    guidedCountLine,
    guidedEntryCount,
    GUIDED_STEPS,
    GUIDED_TOPIC,
    guidedUrl,
    guidedReviewHeading,
    isGuidedView,
    readGuidedLocation,
} from '@/lib/guided';
import type { MistakeEntry, VocabularyEntry } from '@/types';

function mistake(text: string): MistakeEntry {
    return { id: `m-${text}`, text, correction_html: '', reason: '' };
}

function word(text: string): VocabularyEntry {
    return { id: `v-${text}`, text, meaning: '', example_html: '' };
}

describe('the list of steps', () => {
    it('holds the seven steps in the order a chat is pasted in', () => {
        expect(GUIDED_STEPS.map((step) => step.id)).toEqual([
            'question',
            'answer',
            'mistake',
            'vocabulary',
            'phrase',
            'grammar_note',
            'natural_version',
        ]);
    });

    it('names every box step after the box itself', () => {
        expect(GUIDED_STEPS[2].label).toBe(feedbackBoxFor('mistake').label);
        expect(GUIDED_STEPS[6].label).toBe(
            feedbackBoxFor('natural_version').label,
        );
    });

    it('sends every step off with a heading and a hint', () => {
        for (const step of GUIDED_STEPS) {
            expect(step.heading.length).toBeGreaterThan(0);
            expect(step.hint.length).toBeGreaterThan(0);
        }
    });

    it('keeps the topic step apart from the seven', () => {
        expect(GUIDED_TOPIC.heading).toBe('Choose a topic');
        expect(GUIDED_TOPIC.label).toBe('Topic');
        // The seven steps themselves are pinned exactly above; the topic has
        // its own screen so it can be shown before them.
        expect(GUIDED_STEPS.length).toBe(7);
    });
});

describe('reading an address', () => {
    it('says so when the address is the plain editor', () => {
        expect(readGuidedLocation('/sessions/5/edit')).toBeNull();
        expect(readGuidedLocation('/sessions/create?foo=bar')).toBeNull();
    });

    it('reads the question and the step out of a guided address', () => {
        expect(
            readGuidedLocation(
                '/sessions/5/edit?guided=1&question=3&step=phrase',
            ),
        ).toEqual({ question: 3, view: 'phrase' });
    });

    it('falls back to the first question when the address says nothing useful', () => {
        expect(
            readGuidedLocation('/sessions/create?guided=1&step=nonsense'),
        ).toEqual({ question: 1, view: 'question' });
        expect(
            readGuidedLocation(
                '/sessions/create?guided=1&question=0&step=topic',
            ),
        ).toEqual({ question: 1, view: 'topic' });
    });

    it('accepts the topic step and the review screen', () => {
        expect(isGuidedView('topic')).toBe(true);
        expect(isGuidedView('review')).toBe(true);
        expect(isGuidedView('done')).toBe(false);
    });
});

describe('building an address', () => {
    it('puts the question and step on the editor path', () => {
        expect(
            guidedUrl('/sessions/5/edit', { question: 2, view: 'answer' }),
        ).toBe('/sessions/5/edit?guided=1&question=2&step=answer');
    });

    it('drops any query the path was carrying', () => {
        expect(
            guidedUrl('/sessions/create?guided=1', {
                question: 1,
                view: 'topic',
            }),
        ).toBe('/sessions/create?guided=1&question=1&step=topic');
    });

    it('reads back what it wrote', () => {
        const url = guidedUrl('/sessions/9/edit', {
            question: 4,
            view: 'review',
        });

        expect(readGuidedLocation(url)).toEqual({
            question: 4,
            view: 'review',
        });
    });

    it('strips guided mode off an address', () => {
        expect(
            editorUrl('/sessions/5/edit?guided=1&question=1&step=topic'),
        ).toBe('/sessions/5/edit');
        expect(editorUrl('/sessions/create')).toBe('/sessions/create');
    });
});

describe('what a view shows', () => {
    it('maps the box steps onto their feedback box', () => {
        expect(feedbackTypeFor('mistake')).toBe('mistake');
        expect(feedbackTypeFor('natural_version')).toBe('natural_version');
    });

    it('has no box for the prose steps and the screens around them', () => {
        expect(feedbackTypeFor('question')).toBeNull();
        expect(feedbackTypeFor('answer')).toBeNull();
        expect(feedbackTypeFor('topic')).toBeNull();
        expect(feedbackTypeFor('review')).toBeNull();
    });
});

describe('the count under a box', () => {
    const question = {
        feedback: {
            ...emptyFeedbackValues(),
            mistake: [mistake('I go → I went')],
            grammar_note: [newNote(''), newNote('<p>Use the past tense</p>')],
        },
    };

    it('counts the rows a table box holds', () => {
        expect(guidedEntryCount(question, 'mistake')).toBe(1);
        expect(guidedEntryCount(question, 'vocabulary')).toBe(0);
    });

    it('counts only the notes that hold something', () => {
        expect(guidedEntryCount(question, 'grammar_note')).toBe(1);
    });

    it('has nothing to count for prose or for the screens around it', () => {
        expect(guidedEntryCount(question, 'natural_version')).toBeNull();
        expect(guidedEntryCount(question, 'question')).toBeNull();
        expect(guidedEntryCount(question, 'review')).toBeNull();
    });
});

describe('the line under the heading', () => {
    const question = {
        feedback: {
            ...emptyFeedbackValues(),
            mistake: [mistake('I go → I went')],
            vocabulary: [word('book'), word('stay')],
            grammar_note: [newNote('<p>Use the past tense</p>')],
        },
    };

    it('names what the box holds the way the box counts it', () => {
        expect(guidedCountLine(question, 'mistake')).toBe('1 row so far');
        expect(guidedCountLine(question, 'vocabulary')).toBe('2 rows so far');
        expect(guidedCountLine(question, 'grammar_note')).toBe('1 note so far');
    });

    it('has no line for prose or for the screens around it', () => {
        expect(guidedCountLine(question, 'question')).toBeNull();
        expect(guidedCountLine(question, 'natural_version')).toBeNull();
        expect(guidedCountLine(question, 'review')).toBeNull();
    });
});

describe('the review heading', () => {
    it('names the question being reviewed', () => {
        expect(guidedReviewHeading(2)).toBe('Review question 2');
    });
});
