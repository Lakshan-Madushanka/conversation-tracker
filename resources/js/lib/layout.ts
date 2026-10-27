import type { InjectionKey, Ref } from 'vue';

/**
 * The layout's bottom navigation can step aside while a page needs the bottom
 * of a phone for something more important, such as the selection bar.
 */
export const bottomNavHiddenKey: InjectionKey<Ref<boolean>> =
    Symbol('bottomNavHidden');
