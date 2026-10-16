/**
 * Checks whether rich text holds nothing but blank space or empty formatting,
 * so an empty editor (`<p></p>`, `<p><br></p>`, a few spaces, …) is treated as
 * an empty field rather than content.
 */
export function isRichTextBlank(html: string): boolean {
    const text = html
        .replace(/<(?:br|hr)\s*\/?>/giu, '')
        .replace(/<[^>]*>/gu, '')
        .replace(/&nbsp;|&#160;|&#xa0;/giu, '\u00a0')
        .replace(/&amp;/giu, '&')
        .replace(/&lt;/giu, '<')
        .replace(/&gt;/giu, '>')
        .replace(/&quot;/giu, '"')
        .replace(/&#39;|&#x27;/giu, "'");

    return text.trim().length === 0;
}
