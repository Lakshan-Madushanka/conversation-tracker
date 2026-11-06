/** Shapes shared between the basket pages and the backend props. */

import type { CollectionKindValue } from './collections';

/** Every kind a basket can hold, by the backend's names. */
export type BasketKind = CollectionKindValue | 'question';

/** One item in the basket, read live from the session it came from. */
export interface BasketItemData {
    id: string;
    kind: BasketKind;
    /** The collection identity the basket stores. */
    identity: string;
    title: string;
    correction: string;
    /** The correction's words, with only its safe bold and italic kept. */
    correction_html: string;
    /** False while every place it came from is archived. */
    available: boolean;
    topic: string;
    session_date: string | null;
    /** The reading page address that opens the item in a new tab. */
    href: string;
}

/** One collection's items, in the order the basket page shows them. */
export interface BasketGroupData {
    key: BasketKind;
    label: string;
    count: number;
    items: BasketItemData[];
}

/** The page props the basket page receives. */
export interface BasketPageData {
    groups: BasketGroupData[];
    count: number;
    /** Where "Back to collection" goes. */
    back_url: string;
}

/** What adding a set of items came to. */
export interface AddBasketResult {
    added_count: number;
    already_count: number;
    added_identities: string[];
    already_identities: string[];
    total: number;
}

/** What removing a set of items came to. */
export interface RemoveBasketResult {
    removed_count: number;
    removed_identities: string[];
    total: number;
}
