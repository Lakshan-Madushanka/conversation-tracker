/**
 * The words rich text holds, with markup and empty tags removed. Used to decide
 * whether an editor is really empty, and to summarise a collapsed box.
 */
export function richTextToText(html: string): string {
    if (html === null || html === undefined) {
        return '';
    }

    return html
        .replace(/<(?:br|hr)\s*\/?>/giu, ' ')
        .replace(/<[^>]*>/gu, '')
        .replace(/&nbsp;|&#160;|&#xa0;/giu, ' ')
        .replace(/&amp;/giu, '&')
        .replace(/&lt;/giu, '<')
        .replace(/&gt;/giu, '>')
        .replace(/&quot;/giu, '"')
        .replace(/&#39;|&#x27;/giu, "'")
        .replace(/\s+/gu, ' ')
        .trim();
}

/**
 * Checks whether rich text holds nothing but blank space or empty formatting,
 * so an empty editor (`<p></p>`, `<p><br></p>`, a few spaces, …) is treated as
 * an empty field rather than content.
 */
export function isRichTextBlank(html: string): boolean {
    return richTextToText(html).length === 0;
}

/** Escapes the characters that would otherwise be read as markup. */
export function escapeHtml(text: string): string {
    return text
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

/** The small formatting the correction column keeps: bold and italic only. */
const INLINE_MARKS: Record<string, string> = {
    strong: 'strong',
    b: 'strong',
    em: 'em',
    i: 'em',
};

/** Block elements whose boundaries read as a space once they are flattened. */
const INLINE_BLOCKS = new Set([
    'p',
    'div',
    'li',
    'ul',
    'ol',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'blockquote',
    'pre',
    'tr',
    'section',
    'article',
]);

/** Elements whose content is thrown away entirely. */
const INLINE_DROPPED = new Set([
    'script',
    'style',
    'iframe',
    'object',
    'embed',
    'noscript',
    'svg',
    'math',
    'canvas',
    'img',
    'input',
    'button',
    'textarea',
    'select',
    'option',
    'form',
    'link',
    'meta',
    'head',
    'title',
]);

/**
 * Reduces markup to a single inline line holding only bold and italic. Used
 * for the correction column, which is a short sentence, not a document: line
 * breaks and lists become spaces, and any other markup is dropped while
 * keeping its words.
 */
export function normalizeInlineHtml(html: string): string {
    const document = parseHtmlFragment(html);

    const rendered =
        document === null
            ? stripTagsToText(html)
            : `${renderInlineChildren(document.body)} `;

    return collapseInlineWhitespace(rendered).trim();
}

function parseHtmlFragment(html: string): Document | null {
    if (typeof DOMParser === 'undefined') {
        return null;
    }

    return new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html');
}

/** A last-resort reduction for places without a DOM, such as during SSR. */
function stripTagsToText(html: string): string {
    return html
        .replaceAll(/<\s*br\s*\/?\s*>/giu, ' ')
        .replaceAll(/<[^>]*>/gu, '');
}

/** Walks a parsed fragment, keeping text and the two supported marks. */
function renderInlineChildren(node: Node): string {
    let html = '';

    for (const child of Array.from(node.childNodes)) {
        html += renderInlineNode(child);
    }

    return html;
}

function renderInlineNode(node: Node): string {
    if (node.nodeType === 3) {
        return escapeHtml(node.textContent ?? '');
    }

    if (node.nodeType !== 1) {
        return '';
    }

    const element = node as Element;
    const tag = element.tagName.toLowerCase();

    if (INLINE_DROPPED.has(tag)) {
        return '';
    }

    if (tag === 'br') {
        return ' ';
    }

    const mark = INLINE_MARKS[tag];

    if (mark !== undefined) {
        const inner = renderInlineChildren(element);

        return inner.trim() === '' ? '' : `<${mark}>${inner}</${mark}>`;
    }

    if (INLINE_BLOCKS.has(tag)) {
        return `${renderInlineChildren(element)} `;
    }

    // Inline markup outside the supported set keeps only its words.
    return renderInlineChildren(element);
}

/** Collapses runs of whitespace so flattened blocks do not leave gaps. */
function collapseInlineWhitespace(html: string): string {
    return html.replaceAll(/\s+/gu, ' ');
}
