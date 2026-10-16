import { nextTick } from 'vue';

/**
 * Moves focus to an element by id once it exists. Editors that are mounted on
 * demand may not be in the DOM on the next tick, so this retries for a few
 * frames before giving up.
 */
export function focusElementById(id: string, attempts = 10): void {
    void nextTick(() => {
        requestAnimationFrame(() => {
            const element = document.getElementById(id);

            if (element) {
                element.focus();

                return;
            }

            if (attempts > 0) {
                focusElementById(id, attempts - 1);
            }
        });
    });
}
