import type {
    BulkArchiveResult,
    BulkDeletionResult,
    BulkRestoreResult,
    SessionCardData,
} from '@/types';

/** The count on the selection bar. */
export function selectionCountLabel(count: number): string {
    return `${count} selected`;
}

/** How many selected sessions can be archived, said in plain words. */
export function archiveAvailabilityLabel(
    selectedCount: number,
    finishedCount: number,
): string {
    if (selectedCount === 0) {
        return 'Choose sessions to act on.';
    }

    if (finishedCount === 0) {
        return 'Unfinished sessions cannot be archived.';
    }

    if (finishedCount === selectedCount) {
        return finishedCount === 1
            ? 'This finished session can be archived.'
            : 'All of these finished sessions can be archived.';
    }

    return `${finishedCount} of ${selectedCount} can be archived.`;
}

/**
 * The ids between two loaded cards, both ends included. Shift-clicking with an
 * anchor that is no longer loaded falls back to the one card clicked.
 */
export function rangeIds(
    sessions: SessionCardData[],
    fromId: number,
    toId: number,
): number[] {
    const from = sessions.findIndex((session) => session.id === fromId);
    const to = sessions.findIndex((session) => session.id === toId);

    if (from === -1 || to === -1) {
        return [toId];
    }

    const [start, end] = from <= to ? [from, to] : [to, from];

    return sessions.slice(start, end + 1).map((session) => session.id);
}

/** "1 session archived." or "3 sessions archived.", plus every skip reason. */
export function bulkArchiveMessage(result: BulkArchiveResult): string {
    const sentences: string[] = [];

    if (result.archived_count === 0) {
        sentences.push('No sessions were archived.');
    } else {
        sentences.push(
            `${countOf(result.archived_count, 'session')} archived.`,
        );
    }

    if (result.skipped_unfinished > 0) {
        sentences.push(
            `${countOf(result.skipped_unfinished, 'session')} ${
                result.skipped_unfinished === 1 ? 'was' : 'were'
            } skipped because unfinished sessions cannot be archived.`,
        );
    }

    if (result.skipped_archived > 0) {
        sentences.push(
            `${countOf(result.skipped_archived, 'session')} ${
                result.skipped_archived === 1 ? 'was' : 'were'
            } already archived.`,
        );
    }

    if (result.skipped_missing > 0) {
        sentences.push(
            `${countOf(result.skipped_missing, 'session')} ${
                result.skipped_missing === 1 ? 'was' : 'were'
            } no longer here.`,
        );
    }

    return sentences.join(' ');
}

/** "1 session restored." plus anything that could not come back. */
export function bulkRestoreMessage(result: BulkRestoreResult): string {
    const sentences: string[] = [];

    if (result.restored_count === 0) {
        sentences.push('No sessions were restored.');
    } else {
        sentences.push(
            `${countOf(result.restored_count, 'session')} restored.`,
        );
    }

    if (result.skipped_not_archived > 0) {
        sentences.push(
            `${countOf(result.skipped_not_archived, 'session')} ${
                result.skipped_not_archived === 1 ? 'was' : 'were'
            } already in the Library.`,
        );
    }

    if (result.skipped_missing > 0) {
        sentences.push(
            `${countOf(result.skipped_missing, 'session')} ${
                result.skipped_missing === 1 ? 'was' : 'were'
            } no longer here.`,
        );
    }

    return sentences.join(' ');
}

/** "3 sessions deleted." plus any session that was already gone. */
export function bulkDeletionMessage(result: BulkDeletionResult): string {
    if (result.deleted_count === 0 && result.already_deleted) {
        return 'These sessions were already deleted.';
    }

    const sentences = [`${countOf(result.deleted_count, 'session')} deleted.`];

    if (result.skipped_missing > 0) {
        sentences.push(
            `${countOf(result.skipped_missing, 'session')} ${
                result.skipped_missing === 1 ? 'was' : 'were'
            } already deleted.`,
        );
    }

    return sentences.join(' ');
}

/** The heading of the bulk delete warning. */
export function bulkDeleteTitle(count: number): string {
    return count === 1 ? 'Delete this session?' : `Delete ${count} sessions?`;
}

/** The Archive alternative offered inside the warning. */
export function archiveInsteadLabel(count: number): string {
    return count === 1
        ? 'Archive this finished session instead'
        : `Archive the ${count} finished sessions instead`;
}

function countOf(count: number, noun: string): string {
    return `${count} ${noun}${count === 1 ? '' : 's'}`;
}
