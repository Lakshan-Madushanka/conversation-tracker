/**
 * The words rich text holds, with markup and empty tags removed. Used to decide
 * whether an editor is really empty, and to summarise a collapsed box.
 */
export function richTextToText(html: string): string {
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
