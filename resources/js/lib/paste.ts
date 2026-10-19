/**
 * Reads what the learner pasted, whatever the source put on the clipboard.
 *
 * Most applications provide plain text directly. Some only provide markup
 * (for example a chat app or a web page), so the markup is reduced to its
 * words here — the list boxes always show plain text, and the rich boxes let
 * the editor reduce pasted markup to the small formatting set it supports.
 */

/** The plain text of a paste, with line breaks kept. */
export function plainTextFromPaste(event: ClipboardEvent): string {
    const data = event.clipboardData;

    if (!data) {
        return '';
    }

    const plain = data.getData('text/plain');

    if (plain.trim() !== '') {
        return plain;
    }

    return textFromHtml(data.getData('text/html'));
}

/**
 * The words of some markup, with one line break per block. Supported
 * formatting is dropped: the list boxes are plain text by design.
 */
export function textFromHtml(html: string): string {
    if (typeof DOMParser === 'undefined' || html === '') {
        return '';
    }

    const document = new DOMParser().parseFromString(html, 'text/html');

    for (const element of document.querySelectorAll(
        'script, style, head, meta, link, title',
    )) {
        element.remove();
    }

    // A block is a line of its own, so pasted paragraphs stay separate lines
    // and the list boxes can split them into entries.
    for (const element of document.querySelectorAll(
        'p, div, li, h1, h2, h3, h4, h5, h6, blockquote, pre, tr, section, article',
    )) {
        element.append(document.createTextNode('\n'));
    }

    for (const element of document.querySelectorAll('br')) {
        element.replaceWith(document.createTextNode('\n'));
    }

    return document.body?.textContent ?? '';
}
