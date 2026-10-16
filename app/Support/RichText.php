<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Reduces rich text to the small formatting set the app supports
 * (bold, italic, bulleted and numbered lists, and line breaks) and derives the
 * plain-text version used for search, counting and review.
 *
 * The editor already cleans pasted content in the browser; this runs again on
 * the server so unsafe markup can never be stored, whatever the client sends.
 */
final class RichText
{
    /** @var list<string> */
    private const array ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li'];

    /** @var array<string, string> */
    private const array TAG_ALIASES = ['b' => 'strong', 'i' => 'em'];

    /** Elements removed together with everything inside them. */
    /** @var list<string> */
    private const array DROPPED_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed',
        'applet', 'noscript', 'svg', 'math', 'canvas', 'audio', 'video',
        'source', 'track', 'picture', 'img', 'input', 'button', 'textarea',
        'select', 'option', 'form', 'link', 'meta', 'base', 'title', 'head',
    ];

    /** Block-level elements that are turned into paragraphs. */
    /** @var list<string> */
    private const array BLOCK_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'blockquote', 'pre',
        'section', 'article', 'aside', 'header', 'footer', 'main', 'nav',
        'figure', 'figcaption', 'address', 'details', 'summary', 'fieldset',
        'legend', 'dl', 'dt', 'dd', 'caption', 'td', 'th',
    ];

    /** Structural elements whose children carry the text on their own. */
    /** @var list<string> */
    private const array UNWRAPPED_TAGS = [
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'colgroup', 'col', 'hr',
    ];

    /**
     * Clean the markup and return it with its plain-text version.
     *
     * @return array{html: string, text: string}
     */
    public static function cleanWithText(string $html): array
    {
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$html.'</body>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return ['html' => '', 'text' => ''];
        }

        $content = self::renderChildren($body, true);

        // A field holding only blank space or empty formatting counts as empty.
        $text = self::normalizeText($content['text']);

        return [
            'html' => $text === '' ? '' : $content['html'],
            'text' => $text,
        ];
    }

    public static function clean(string $html): string
    {
        return self::cleanWithText($html)['html'];
    }

    public static function toPlainText(string $html): string
    {
        return self::cleanWithText($html)['text'];
    }

    /**
     * @return array{html: string, text: string}
     */
    private static function renderChildren(DOMNode $node, bool $blockContainer): array
    {
        $html = '';
        $text = '';

        foreach ($node->childNodes as $child) {
            // Pretty-printing whitespace between blocks is not content.
            if ($blockContainer && $child instanceof DOMText && trim($child->data) === '') {
                continue;
            }

            $rendered = self::render($child);

            $html .= $rendered['html'];
            $text .= $rendered['text'];
        }

        return ['html' => $html, 'text' => $text];
    }

    /**
     * @return array{html: string, text: string}
     */
    private static function render(DOMNode $node): array
    {
        if ($node instanceof DOMText) {
            return [
                'html' => htmlspecialchars($node->data, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'text' => $node->data,
            ];
        }

        if (! $node instanceof DOMElement) {
            return ['html' => '', 'text' => ''];
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::DROPPED_TAGS, true)) {
            return ['html' => '', 'text' => ''];
        }

        $tag = self::TAG_ALIASES[$tag] ?? $tag;

        if ($tag === 'br') {
            return ['html' => '<br>', 'text' => "\n"];
        }

        // Table structure adds nothing once the text is flattened.
        if (in_array($tag, self::UNWRAPPED_TAGS, true)) {
            return self::renderChildren($node, true);
        }

        $content = self::renderChildren($node, in_array($tag, ['ul', 'ol'], true));

        if (in_array($tag, self::ALLOWED_TAGS, true)) {
            return [
                'html' => $content['html'] === '' ? '' : "<{$tag}>{$content['html']}</{$tag}>",
                'text' => $content['text'].self::lineBreakAfter($tag),
            ];
        }

        if (in_array($tag, self::BLOCK_TAGS, true)) {
            return [
                'html' => $content['html'] === '' ? '' : "<p>{$content['html']}</p>",
                'text' => $content['text']."\n",
            ];
        }

        // Inline markup outside the supported set keeps only its text.
        return $content;
    }

    /**
     * Paragraphs and list items are written one per line in the plain text.
     */
    private static function lineBreakAfter(string $tag): string
    {
        return in_array($tag, ['p', 'li'], true) ? "\n" : '';
    }

    private static function normalizeText(string $text): string
    {
        $text = str_replace("\r", '', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Normalise a plain text string (stripping extra spaces and line endings,
     * keeping a single leading/trailing trim and collapsing empty lines).
     */
    public static function normalizePlainText(string $text): string
    {
        $text = str_replace("\r", '', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
