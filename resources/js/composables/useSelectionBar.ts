import { inject, onBeforeUnmount, watch, type Ref } from 'vue';
import { bottomNavHiddenKey } from '@/lib/layout';

/**
 * Takes the bottom navigation away while a selection bar needs that space on
 * a phone, and always gives it back when the page closes.
 */
export function useSelectionBar(active: Ref<boolean>): void {
    const hidden = inject(bottomNavHiddenKey, null);

    if (hidden === null) {
        return;
    }

    watch(
        active,
        (value) => {
            hidden.value = value;
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        hidden.value = false;
    });
}
