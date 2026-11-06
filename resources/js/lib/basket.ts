/**
 * The custom-review basket, as the pages share it: the count shown in the bar,
 * and the small requests that add, remove or clear items.
 *
 * The server keeps the basket, so every request carries the whole set the
 * learner chose rather than a toggle. Repeating a request can never add the
 * same item twice, which lets a double press, a retry or a second tab stay
 * safe.
 */

import { ref } from 'vue';
import { jsonRequest } from '@/lib/http';
import { clear as basketClear } from '@/routes/basket';
import {
    destroy as basketDestroy,
    store as basketStore,
} from '@/routes/basket/items';
import type { AddBasketResult, BasketKind, RemoveBasketResult } from '@/types';

/** The basket's size, shared by every page that shows the bar. */
export const basketCount = ref(0);

/** Takes the count the server sent with a page, so the bar starts in step. */
export function hydrateBasketCount(count: number | undefined): void {
    if (typeof count === 'number') {
        basketCount.value = count;
    }
}

/** A basket item's accessible name for the controls that act on it. */
export function basketItemName(kind: BasketKind, title: string): string {
    const fallback: Record<BasketKind, string> = {
        vocabulary: 'word',
        phrase: 'phrase',
        grammar_note: 'grammar note',
        mistake: 'mistake',
        question: 'question',
    };

    return title.trim() === '' ? fallback[kind] : title;
}

/** The count in words: "1 item", "5 items". */
export function basketCountLabel(count: number): string {
    return `${count} ${count === 1 ? 'item' : 'items'}`;
}

/** The line the bar shows above the bottom navigation. */
export function basketBarLabel(count: number): string {
    return `Building a custom review · ${basketCountLabel(count)}`;
}

/**
 * Where the basket page returns to. Only the two pages that show the bar are
 * worth remembering; anything else falls back to Collections server-side.
 */
export function currentBasketReturn(): string {
    if (typeof window === 'undefined') {
        return '/collections';
    }

    return `${window.location.pathname}${window.location.search}`;
}

/** Adds the chosen identities of one kind, and returns what changed. */
export async function addToBasket(
    kind: BasketKind,
    identities: string[],
): Promise<AddBasketResult> {
    const response = await jsonRequest(basketStore.url(), {
        method: 'POST',
        body: JSON.stringify({ kind, identities }),
    });

    if (!response.ok) {
        throw new Error(`Adding to the basket failed (${response.status}).`);
    }

    const result = (await response.json()) as AddBasketResult;
    basketCount.value = result.total;

    return result;
}

/** Removes the chosen identities of one kind, and returns what changed. */
export async function removeFromBasket(
    kind: BasketKind,
    identities: string[],
): Promise<RemoveBasketResult> {
    const response = await jsonRequest(basketDestroy.url(), {
        method: 'DELETE',
        body: JSON.stringify({ kind, identities }),
    });

    if (!response.ok) {
        throw new Error(
            `Removing from the basket failed (${response.status}).`,
        );
    }

    const result = (await response.json()) as RemoveBasketResult;
    basketCount.value = result.total;

    return result;
}

/** Empties the basket. The page asks for confirmation first. */
export async function clearBasket(): Promise<number> {
    const response = await jsonRequest(basketClear.url(), {
        method: 'DELETE',
    });

    if (!response.ok) {
        throw new Error(`Clearing the basket failed (${response.status}).`);
    }

    const result = (await response.json()) as { cleared_count: number };
    basketCount.value = 0;

    return result.cleared_count;
}
