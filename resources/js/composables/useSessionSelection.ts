import { computed, onBeforeUnmount, ref } from 'vue';
import { rangeIds } from '@/lib/session-selection';
import type { SessionCardData } from '@/types';

/**
 * The sessions the learner has chosen in the Library.
 *
 * Selection lives only on this page and only as a fixed set of ids: changing
 * filters, ending selection mode or refreshing the browser drops it, so the
 * learner never acts on sessions they can no longer see. The finished ids are
 * kept beside the selection so the bar knows how many can be archived, even
 * when "Select all" picked up sessions that are not loaded yet.
 */
export function useSessionSelection() {
    const active = ref(false);
    const selectedIds = ref<Set<number>>(new Set());
    const finishedIds = ref<Set<number>>(new Set());
    const announcement = ref('');

    /** The card a Shift-click range starts from. */
    let anchorId: number | null = null;
    let announceTimer: ReturnType<typeof setTimeout> | null = null;

    const ids = computed(() => [...selectedIds.value]);

    const count = computed(() => selectedIds.value.size);

    const finishedCount = computed(() => {
        let finished = 0;

        for (const id of selectedIds.value) {
            if (finishedIds.value.has(id)) {
                finished += 1;
            }
        }

        return finished;
    });

    const hasSelection = computed(() => count.value > 0);

    /** Says the new state after a short pause, never on every keystroke. */
    function announce(message: string, delay = 250): void {
        if (announceTimer !== null) {
            clearTimeout(announceTimer);
        }

        announceTimer = setTimeout(() => {
            announcement.value = message;
            announceTimer = null;
        }, delay);
    }

    function isSelected(sessionId: number): boolean {
        return selectedIds.value.has(sessionId);
    }

    function begin(): void {
        active.value = true;
        clear();
        announce('Selection mode. Choose sessions to act on.');
    }

    function end(): void {
        active.value = false;
        clear();
        announce('Selection mode ended.');
    }

    function clear(): void {
        selectedIds.value = new Set();
        finishedIds.value = new Set();
        anchorId = null;
    }

    /**
     * Selects or unselects one card. With Shift, the range from the previous
     * card to this one is added instead, so a run can be chosen in one go.
     */
    function toggle(
        session: SessionCardData,
        sessions: SessionCardData[],
        shiftKey = false,
    ): void {
        if (shiftKey && anchorId !== null && anchorId !== session.id) {
            const nextIds = new Set(selectedIds.value);
            const nextFinished = new Set(finishedIds.value);

            for (const id of rangeIds(sessions, anchorId, session.id)) {
                const card = sessions.find((candidate) => candidate.id === id);

                nextIds.add(id);

                if (card?.status === 'finished') {
                    nextFinished.add(id);
                } else {
                    nextFinished.delete(id);
                }
            }

            selectedIds.value = nextIds;
            finishedIds.value = nextFinished;
            announce(`${nextIds.size} selected.`);

            return;
        }

        const nextIds = new Set(selectedIds.value);
        const nextFinished = new Set(finishedIds.value);

        if (nextIds.has(session.id)) {
            nextIds.delete(session.id);
            nextFinished.delete(session.id);
        } else {
            nextIds.add(session.id);

            if (session.status === 'finished') {
                nextFinished.add(session.id);
            }
        }

        selectedIds.value = nextIds;
        finishedIds.value = nextFinished;
        anchorId = session.id;

        announce(
            nextIds.size === 0
                ? 'No sessions selected.'
                : `${nextIds.size} selected.`,
        );
    }

    /** Every session matching the filters, chosen in one go. */
    function selectAll(idsToSelect: number[], finished: number[]): void {
        selectedIds.value = new Set(idsToSelect);
        finishedIds.value = new Set(finished);
        anchorId = null;

        announce(
            idsToSelect.length === 0
                ? 'No sessions selected.'
                : `All ${idsToSelect.length} matching sessions selected.`,
        );
    }

    /** Drops the selection after the list itself changed. */
    function clearBecauseListChanged(): void {
        const hadSelection = count.value > 0;

        clear();

        if (hadSelection) {
            announce('Selection cleared because the list changed.');
        }
    }

    onBeforeUnmount(() => {
        if (announceTimer !== null) {
            clearTimeout(announceTimer);
        }
    });

    return {
        active,
        ids,
        count,
        finishedCount,
        hasSelection,
        announcement,
        isSelected,
        begin,
        end,
        clear,
        toggle,
        selectAll,
        clearBecauseListChanged,
        announce,
    };
}
