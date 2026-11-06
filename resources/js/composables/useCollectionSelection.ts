import { computed, onBeforeUnmount, ref } from 'vue';

/** Anything a selection can name a row by. */
type Selectable = { key: string };

/**
 * The items the learner has chosen to add to a review.
 *
 * Selection is a fixed set of keys, kept only while the page is open: changing
 * the tab or the filters drops it, so the learner never adds items they can no
 * longer see. "Select all" may bring in items that are not loaded yet, which is
 * why the keys are kept rather than a list of items.
 */
export function useCollectionSelection() {
    const active = ref(false);
    const selectedKeys = ref<Set<string>>(new Set());
    const announcement = ref('');

    /** The item a Shift range starts from. */
    let anchorKey: string | null = null;
    let announceTimer: ReturnType<typeof setTimeout> | null = null;

    const keys = computed(() => [...selectedKeys.value]);

    const count = computed(() => selectedKeys.value.size);

    function isSelected(key: string): boolean {
        return selectedKeys.value.has(key);
    }

    /** Says the new state after a short pause, never on every key press. */
    function announce(message: string, delay = 250): void {
        if (announceTimer !== null) {
            clearTimeout(announceTimer);
        }

        announceTimer = setTimeout(() => {
            announcement.value = message;
            announceTimer = null;
        }, delay);
    }

    function begin(): void {
        active.value = true;
        clear();
        announce('Selection mode. Choose items to add to a review.');
    }

    function end(): void {
        active.value = false;
        clear();
        announce('Selection mode ended.');
    }

    function clear(): void {
        selectedKeys.value = new Set();
        anchorKey = null;
    }

    /** Chooses one item, or with Shift the run from the previous one. */
    function toggle(
        item: Selectable,
        items: Selectable[],
        shiftKey = false,
    ): void {
        const next = new Set(selectedKeys.value);

        if (shiftKey && anchorKey !== null && anchorKey !== item.key) {
            const start = items.findIndex(
                (candidate) => candidate.key === anchorKey,
            );
            const finish = items.findIndex(
                (candidate) => candidate.key === item.key,
            );

            if (start !== -1 && finish !== -1) {
                const [from, to] =
                    start < finish ? [start, finish] : [finish, start];

                for (const candidate of items.slice(from, to + 1)) {
                    next.add(candidate.key);
                }

                selectedKeys.value = next;
                anchorKey = item.key;
                announce(`${next.size} selected.`);

                return;
            }
        }

        if (next.has(item.key)) {
            next.delete(item.key);
        } else {
            next.add(item.key);
        }

        selectedKeys.value = next;
        anchorKey = item.key;

        announce(
            next.size === 0 ? 'No items selected.' : `${next.size} selected.`,
        );
    }

    /** Every item matching the current view, chosen in one go. */
    function selectAll(allKeys: string[]): void {
        selectedKeys.value = new Set(allKeys);
        anchorKey = null;

        announce(
            allKeys.length === 0
                ? 'No items selected.'
                : `All ${allKeys.length} matching items selected.`,
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
        keys,
        count,
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
