/**
 * The cleaning rules for the plain list boxes (mistakes, vocabulary and
 * phrases): pasted text is split into lines, and every line becomes one clean
 * entry.
 *
 * This mirrors `App\Support/ListEntries`, so the browser and the server always
 * agree on what a paste becomes. The server applies the same rules on save, so
 * what is stored always matches what the learner saw.
 */

/** The most entries one box can hold. */
export const MAX_ENTRIES = 200;

/** The longest single entry, in characters. */
export const MAX_LENGTH = 1000;

/**
 * A bullet, dash or numbering marker at the very start of a line, such as
 * "• ", "- ", "1. ", "2) " or "(3) ". Only markers at the start are removed,
 * so text that merely contains a number or a dash — "3 days" or "reluctant —
 * unwilling" — is left alone.
 */
const MARKER =
    /^\s*(?:(?:[•‣⁃·◦▪●∙]\s*|[-+–—*]\s+|\d{1,4}[.)](?!\d)\s*|\(\d{1,4}\)\s*|\([a-zA-Z]\)\s*|[a-zA-Z][.)](?![a-zA-Z])\s*){1,4})/u;

/**
 * Split pasted text into cleaned entries: one per line, blank lines ignored.
 */
export function splitIntoEntries(text: string): string[] {
    const entries: string[] = [];

    for (const line of lines(text)) {
        const entry = cleanLine(line);

        if (entry !== '') {
            entries.push(entry);
        }
    }

    return entries;
}

/**
 * Reduce one line to a clean entry: a leading marker removed, tabs and
 * repeated spaces turned into a single space, and the ends trimmed. A line
 * that holds several lines of pasted text is joined into one.
 */
export function cleanLine(line: string): string {
    const withoutTabs = line.replaceAll('\t', ' ').replaceAll('\u00a0', ' ');

    return withoutTabs.replace(MARKER, '').replaceAll(/\s+/gu, ' ').trim();
}

/**
 * Split text on any line ending computers produce, keeping empty lines so the
 * caller can tell blank lines apart from missing ones.
 */
function lines(text: string): string[] {
    const normalised = text.replaceAll(/\r\n|[\r\u2028\u2029]/gu, '\n');
    const parts = normalised.split('\n');

    // One line break at the end finishes the last line; it does not start a
    // line of its own.
    if (parts.at(-1) === '') {
        parts.pop();
    }

    return parts;
}

/**
 * How an entry is compared with the others in the same box, so a pasted word
 * that is already there can be flagged as a duplicate.
 */
export function normalizeEntryText(text: string): string {
    return cleanLine(text).toLowerCase();
}
