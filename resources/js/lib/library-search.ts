import type { FeedbackTone } from '@/components/SectionLabel.vue';
import { feedbackBoxFor } from '@/lib/feedback';
import { edit as sessionsEdit, show as sessionsShow } from '@/routes/sessions';
import type { SearchMatch, SessionCardData } from '@/types';

/**
 * Where a matching item sends the learner: the reading page for a finished
 * session, the editor for an unfinished one, with the item named in the
 * address so that page can take them straight to it.
 */
export function searchMatchHref(
    session: Pick<SessionCardData, 'id' | 'status'>,
    match: SearchMatch,
): string {
    const base =
        session.status === 'finished'
            ? sessionsShow.url(session.id)
            : sessionsEdit.url(session.id);

    if (match.kind === 'topic') {
        return base;
    }

    const query = new URLSearchParams();

    if (match.question_position !== null) {
        query.set('question', String(match.question_position));
    }

    if (match.kind === 'question') {
        query.set('field', 'question');
    }

    if (match.kind === 'answer') {
        query.set('field', 'answer');
    }

    if (match.kind === 'entry') {
        query.set('field', 'entry');

        if (match.type !== null) {
            query.set('type', match.type);
        }

        if (match.entry_id !== null) {
            query.set('entry', match.entry_id);
        }

        if (match.entry_position !== null) {
            query.set('position', String(match.entry_position));
        }
    }

    const suffix = query.toString();

    return suffix === '' ? base : `${base}?${suffix}`;
}

/** The section label shown beside a matching item. */
export function matchKindLabel(match: SearchMatch): string {
    if (match.kind === 'topic') {
        return 'Topic';
    }

    if (match.kind === 'question') {
        return 'Tutor’s question';
    }

    if (match.kind === 'answer') {
        return 'My answer';
    }

    return match.type === null ? 'Feedback' : feedbackBoxFor(match.type).label;
}

/**
 * The colour tag a matching item wears: the same one its box wears in the
 * editor and on the reading page, so a section is recognisable at a glance.
 */
export function matchTone(match: SearchMatch): FeedbackTone {
    if (match.kind === 'entry' && match.type !== null) {
        return feedbackBoxFor(match.type).tone;
    }

    return 'neutral';
}
