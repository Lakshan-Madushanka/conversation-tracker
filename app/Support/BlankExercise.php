<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Builds one fill-in-the-blank exercise from a saved entry.
 *
 * The blank is chosen on the server, deterministically, from the entry's
 * current columns: a vocabulary example blanks the word, a phrase or its
 * example blanks a key word, a mistake correction blanks the words that
 * changed, and a grammar note uses the learner's bold part or a quoted
 * example. Nothing is saved, and writing the answer is never graded.
 *
 * The returned HTML is already safe: saved text is escaped and reduced to the
 * small formatting set the app supports before the blank span or the answer
 * mark is added. The class never writes, never queries and never touches a
 * request, so it stays a pure projection of one entry.
 */
final class BlankExercise
{
    /** A correction is only usable when it keeps at least this much visible. */
    private const int MAX_MISTAKE_BLANKS = 4;

    /** A bold part longer than this is too much of the note to blank. */
    private const int MAX_NOTE_BOLD_WORDS = 4;

    /** A quoted example needs at least this many words to be worth using. */
    private const int MIN_QUOTE_WORDS = 3;

    /** @var list<string> */
    private const array STOPWORDS = [
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'also', 'am',
        'an', 'and', 'any', 'are', 'as', 'at', 'be', 'because', 'been', 'before',
        'being', 'below', 'between', 'both', 'but', 'by', 'can', 'could', 'did',
        'do', 'does', 'doing', 'down', 'during', 'each', 'few', 'for', 'from',
        'further', 'had', 'has', 'have', 'having', 'he', 'her', 'here', 'hers',
        'herself', 'him', 'himself', 'his', 'how', 'i', 'if', 'in', 'into', 'is',
        'it', 'its', 'itself', 'just', 'me', 'more', 'most', 'much', 'my',
        'myself', 'no', 'nor', 'not', 'now', 'of', 'off', 'on', 'once', 'only',
        'or', 'other', 'our', 'ours', 'ourselves', 'out', 'over', 'own', 'same',
        'she', 'should', 'so', 'some', 'such', 'than', 'that', 'the', 'their',
        'theirs', 'them', 'themselves', 'then', 'there', 'these', 'they', 'this',
        'those', 'through', 'to', 'too', 'under', 'until', 'up', 'very', 'was',
        'we', 'were', 'what', 'when', 'where', 'which', 'while', 'who', 'whom',
        'why', 'will', 'with', 'would', 'yet', 'you', 'your', 'yours',
        'yourself', 'yourselves',
    ];

    /**
     * @return array{
     *     prompt_html: string,
     *     answer_html: string,
     *     answer_text: string,
     *     hint: string|null,
     *     hint_label: string|null
     * }|null
     */
    public static function build(
        PracticeFeedbackEntry $entry,
        string $topic = '',
        ?string $reason = null,
    ): ?array {
        return match ($entry->type) {
            FeedbackType::Vocabulary => self::vocabulary($entry),
            FeedbackType::Phrase => self::phrase($entry),
            FeedbackType::Mistake => self::mistake($entry, $reason),
            FeedbackType::GrammarNote => self::grammarNote($entry, $topic),
            default => null,
        };
    }

    /**
     * @return array{
     *     prompt_html: string,
     *     answer_html: string,
     *     answer_text: string,
     *     hint: string|null,
     *     hint_label: string|null
     * }|null
     */
    private static function vocabulary(PracticeFeedbackEntry $entry): ?array
    {
        $word = trim((string) $entry->content_text);
        $html = self::exampleHtml($entry);

        if ($word === '' || $html === null) {
            return null;
        }

        $plain = RichText::cleanInlineWithText($html)['text'];

        if (! self::hasOtherVisibleWord($plain)) {
            return null;
        }

        $range = self::findWordRange($plain, $word);

        if ($range === null) {
            return null;
        }

        $prompt = self::replaceRanges($html, [$range], 'blank');
        $answer = self::replaceRanges($html, [$range], 'mark');

        if ($prompt === null || $answer === null) {
            return null;
        }

        $example = trim(EntrySearch::exampleText($entry));

        return [
            'prompt_html' => '<p>'.$prompt.'</p>',
            'answer_html' => '<p>'.$answer.'</p>',
            'answer_text' => $example !== '' ? $example : $plain,
            'hint' => null,
            'hint_label' => null,
        ];
    }

    /**
     * @return array{
     *     prompt_html: string,
     *     answer_html: string,
     *     answer_text: string,
     *     hint: string|null,
     *     hint_label: string|null
     * }|null
     */
    private static function phrase(PracticeFeedbackEntry $entry): ?array
    {
        $phrase = trim((string) $entry->content_text);

        if ($phrase === '') {
            return null;
        }

        $example = self::exampleHtml($entry);
        $source = $example ?? htmlspecialchars($phrase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $clean = RichText::cleanInlineWithText($source);
        $html = $clean['html'];
        $plain = $clean['text'];

        if (! self::hasOtherVisibleWord($plain)) {
            return null;
        }

        $phraseWords = array_map(
            fn (array $token): string => self::normalizeToken($token['word']),
            self::tokens($phrase),
        );
        $target = self::chooseKeyToken(self::tokens($plain), $phraseWords);

        if ($target === null) {
            return null;
        }

        $range = ['start' => $target['start'], 'end' => $target['end']];
        $prompt = self::replaceRanges($html, [$range], 'blank');
        $answer = self::replaceRanges($html, [$range], 'mark');

        if ($prompt === null || $answer === null) {
            return null;
        }

        return [
            'prompt_html' => '<p>'.$prompt.'</p>',
            'answer_html' => '<p>'.$answer.'</p>',
            'answer_text' => $example === null ? $phrase : $plain,
            'hint' => null,
            'hint_label' => null,
        ];
    }

    /**
     * @return array{
     *     prompt_html: string,
     *     answer_html: string,
     *     answer_text: string,
     *     hint: string|null,
     *     hint_label: string|null
     * }|null
     */
    private static function mistake(PracticeFeedbackEntry $entry, ?string $reason): ?array
    {
        $clean = RichText::cleanInlineWithText((string) $entry->correction_html);
        $html = $clean['html'];
        $plain = $clean['text'];

        if ($plain === '') {
            $plain = trim((string) $entry->correction_text);
            $html = htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $tokens = self::tokens($plain);

        if ($plain === '' || $html === '' || $tokens === []) {
            return null;
        }

        $said = array_map(
            fn (array $token): string => self::normalizeToken($token['word']),
            self::tokens((string) $entry->content_text),
        );
        $correctionWords = array_map(
            fn (array $token): string => self::normalizeToken($token['word']),
            $tokens,
        );
        $kept = self::longestCommonMatch($said, $correctionWords);

        $ranges = [];

        foreach ($tokens as $index => $token) {
            if (isset($kept[$index])) {
                continue;
            }

            $ranges[] = ['start' => $token['start'], 'end' => $token['end']];
        }

        if ($ranges === []
            || count($ranges) > self::MAX_MISTAKE_BLANKS
            || count($ranges) === count($tokens)) {
            return null;
        }

        $prompt = self::replaceRanges($html, $ranges, 'blank');
        $answer = self::replaceRanges($html, $ranges, 'mark');

        if ($prompt === null || $answer === null) {
            return null;
        }

        $hint = trim((string) ($reason ?? $entry->reason));

        return [
            'prompt_html' => '<p>'.$prompt.'</p>',
            'answer_html' => '<p>'.$answer.'</p>',
            'answer_text' => trim((string) $entry->correction_text) !== ''
                ? trim((string) $entry->correction_text)
                : $plain,
            'hint' => $hint === '' ? null : $hint,
            'hint_label' => $hint === '' ? null : 'Why',
        ];
    }

    /**
     * @return array{
     *     prompt_html: string,
     *     answer_html: string,
     *     answer_text: string,
     *     hint: string|null,
     *     hint_label: string|null
     * }|null
     */
    private static function grammarNote(PracticeFeedbackEntry $entry, string $topic): ?array
    {
        $clean = RichText::clean((string) $entry->content_html);
        $plain = RichText::toPlainText($clean);

        if ($clean === '' || $plain === '') {
            return null;
        }

        [$document, $root] = self::load($clean);
        $strongs = self::elementsByTag($root, ['strong']);
        $hasBold = false;
        $qualifyingIndex = null;

        foreach ($strongs as $index => $strong) {
            $text = trim((string) $strong->textContent);

            if ($text === '') {
                continue;
            }

            $hasBold = true;

            if ($qualifyingIndex === null) {
                $count = self::countWords($text);

                if ($count >= 1 && $count <= self::MAX_NOTE_BOLD_WORDS) {
                    $qualifyingIndex = $index;
                }
            }
        }

        $hint = trim($topic);

        if ($hasBold) {
            if ($qualifyingIndex === null || ! self::hasVisibleOutsideBold($root)) {
                return null;
            }

            return self::boldNote($clean, $qualifyingIndex, $plain, $hint);
        }

        return self::quotedNote($clean, $plain, $hint);
    }

    /**
     * @return array{
     *     prompt_html: string,
     *     answer_html: string,
     *     answer_text: string,
     *     hint: string|null,
     *     hint_label: string|null
     * }|null
     */
    private static function boldNote(string $clean, int $strongIndex, string $plain, string $hint): ?array
    {
        [$promptDocument, $promptRoot] = self::load($clean);
        $promptStrong = self::elementsByTag($promptRoot, ['strong'])[$strongIndex] ?? null;

        if (! $promptStrong instanceof DOMElement) {
            return null;
        }

        while ($promptStrong->firstChild instanceof DOMNode) {
            $promptStrong->removeChild($promptStrong->firstChild);
        }

        $promptStrong->appendChild(self::blankSpan($promptDocument));
        $block = self::nearestBlock($promptStrong) ?? $promptStrong;
        $prompt = self::serializeBlock($promptDocument, $block);

        [$answerDocument, $answerRoot] = self::load($clean);
        $answerStrong = self::elementsByTag($answerRoot, ['strong'])[$strongIndex] ?? null;

        if (! $answerStrong instanceof DOMElement) {
            return null;
        }

        $parent = $answerStrong->parentNode;

        if (! $parent instanceof DOMElement) {
            return null;
        }

        $mark = $answerDocument->createElement('mark');
        $parent->insertBefore($mark, $answerStrong);
        $mark->appendChild($answerStrong);
        $answer = self::serializeChildren($answerDocument, $answerRoot);

        return [
            'prompt_html' => $prompt,
            'answer_html' => $answer,
            'answer_text' => $plain,
            'hint' => $hint === '' ? null : $hint,
            'hint_label' => $hint === '' ? null : 'Topic',
        ];
    }

    /**
     * @return array{
     *     prompt_html: string,
     *     answer_html: string,
     *     answer_text: string,
     *     hint: string|null,
     *     hint_label: string|null
     * }|null
     */
    private static function quotedNote(string $clean, string $plain, string $hint): ?array
    {
        [$document, $root] = self::load($clean);
        $blocks = self::elementsByTag($root, ['p', 'li']);
        $blocks = $blocks === [] ? [$root] : $blocks;

        foreach ($blocks as $index => $block) {
            $quote = self::findQuote(self::nodeText($block));

            if ($quote === null) {
                continue;
            }

            $inner = substr(
                self::nodeText($block),
                $quote['inner_start'],
                $quote['inner_end'] - $quote['inner_start'],
            );
            $token = self::chooseKeyToken(self::tokens($inner), []);

            if ($token === null) {
                continue;
            }

            $range = [
                'start' => $quote['inner_start'] + $token['start'],
                'end' => $quote['inner_start'] + $token['end'],
            ];

            [$promptDocument, $promptRoot] = self::load($clean);
            $promptBlocks = self::elementsByTag($promptRoot, ['p', 'li']);
            $promptBlocks = $promptBlocks === [] ? [$promptRoot] : $promptBlocks;
            $promptBlock = $promptBlocks[$index] ?? null;

            if (! $promptBlock instanceof DOMElement
                || ! self::applyRange($promptDocument, $promptBlock, $range['start'], $range['end'], 'blank')) {
                continue;
            }

            $prompt = self::serializeBlock($promptDocument, $promptBlock);

            [$answerDocument, $answerRoot] = self::load($clean);
            $answerBlocks = self::elementsByTag($answerRoot, ['p', 'li']);
            $answerBlocks = $answerBlocks === [] ? [$answerRoot] : $answerBlocks;
            $answerBlock = $answerBlocks[$index] ?? null;

            if (! $answerBlock instanceof DOMElement
                || ! self::applyRange($answerDocument, $answerBlock, $range['start'], $range['end'], 'mark')) {
                continue;
            }

            return [
                'prompt_html' => $prompt,
                'answer_html' => self::serializeChildren($answerDocument, $answerRoot),
                'answer_text' => $plain,
                'hint' => $hint === '' ? null : $hint,
                'hint_label' => $hint === '' ? null : 'Topic',
            ];
        }

        return null;
    }

    /** The safe inline example, or null when the entry holds none. */
    private static function exampleHtml(PracticeFeedbackEntry $entry): ?string
    {
        $raw = $entry->getAttribute('example_html');
        $html = '';

        if (is_array($raw)) {
            $html = implode(' ', array_map(
                fn (mixed $part): string => is_array($part) && isset($part['html'])
                    ? (string) $part['html']
                    : '',
                $raw,
            ));
        } elseif (is_string($raw)) {
            $html = $raw;
        }

        if (trim($html) === '') {
            $text = trim((string) $entry->example);

            if ($text === '') {
                return null;
            }

            $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $clean = RichText::cleanInlineWithText($html);

        return $clean['text'] === '' ? null : $clean['html'];
    }

    /** Whether the text holds at least two visible words. */
    private static function hasOtherVisibleWord(string $text): bool
    {
        return count(self::tokens($text)) >= 2;
    }

    /**
     * The first whole-word occurrence of a target in the plain text.
     *
     * @return array{start: int, end: int}|null
     */
    private static function findWordRange(string $text, string $word): ?array
    {
        $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote($word, '/').'(?![\p{L}\p{N}_])/iu';

        if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return ['start' => $match[0][1], 'end' => $match[0][1] + strlen($match[0][0])];
    }

    /**
     * The words of a text, with byte offsets so a range can be cut from the
     * matching markup without touching the surrounding tags.
     *
     * @return list<array{word: string, start: int, end: int}>
     */
    private static function tokens(string $text): array
    {
        preg_match_all(
            '/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u',
            $text,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        $tokens = [];

        /** @var array{0: string, 1: int} $match */
        foreach ($matches[0] as $match) {
            $tokens[] = [
                'word' => $match[0],
                'start' => $match[1],
                'end' => $match[1] + strlen($match[0]),
            ];
        }

        return $tokens;
    }

    private static function normalizeToken(string $word): string
    {
        return mb_strtolower($word);
    }

    private static function isStopword(string $word): bool
    {
        return in_array(self::normalizeToken($word), self::STOPWORDS, true);
    }

    /**
     * The longest non-stopword, preferring one already used in the phrase.
     * Ties go to the first occurrence, so the same text always loses the same
     * word.
     *
     * @param  list<array{word: string, start: int, end: int}>  $tokens
     * @param  list<string>  $preferred  normalised words to prefer
     * @return array{word: string, start: int, end: int}|null
     */
    private static function chooseKeyToken(array $tokens, array $preferred): ?array
    {
        $candidates = array_values(array_filter(
            $tokens,
            fn (array $token): bool => ! self::isStopword($token['word']),
        ));

        if ($candidates === []) {
            return null;
        }

        if ($preferred !== []) {
            $inPhrase = array_values(array_filter(
                $candidates,
                fn (array $token): bool => in_array(
                    self::normalizeToken($token['word']),
                    $preferred,
                    true,
                ),
            ));

            if ($inPhrase !== []) {
                $candidates = $inPhrase;
            }
        }

        $best = $candidates[0];

        foreach ($candidates as $candidate) {
            if (mb_strlen($candidate['word']) > mb_strlen($best['word'])) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /** How many words a bold part or a quote holds, ignoring outer punctuation. */
    private static function countWords(string $text): int
    {
        $text = trim($text);
        $text = preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $text) ?? '';

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
    }

    /**
     * The indices of the correction words the said version also holds, in
     * order, using the longest common subsequence of the normalised words.
     *
     * @param  list<string>  $said
     * @param  list<string>  $correction
     * @return array<int, true>
     */
    private static function longestCommonMatch(array $said, array $correction): array
    {
        $saidCount = count($said);
        $correctionCount = count($correction);
        $lengths = array_fill(0, $saidCount + 1, array_fill(0, $correctionCount + 1, 0));

        for ($i = 1; $i <= $saidCount; $i += 1) {
            for ($j = 1; $j <= $correctionCount; $j += 1) {
                $lengths[$i][$j] = $said[$i - 1] === $correction[$j - 1]
                    ? $lengths[$i - 1][$j - 1] + 1
                    : max($lengths[$i - 1][$j], $lengths[$i][$j - 1]);
            }
        }

        $matched = [];
        $i = $saidCount;
        $j = $correctionCount;

        while ($i > 0 && $j > 0) {
            if ($said[$i - 1] === $correction[$j - 1]) {
                $matched[$j - 1] = true;
                $i -= 1;
                $j -= 1;
            } elseif ($lengths[$i - 1][$j] >= $lengths[$i][$j - 1]) {
                $i -= 1;
            } else {
                $j -= 1;
            }
        }

        return $matched;
    }

    /**
     * The first quoted example in a block, with byte offsets for its words.
     *
     * @return array{start: int, end: int, inner_start: int, inner_end: int}|null
     */
    private static function findQuote(string $text): ?array
    {
        preg_match_all(
            '/“([^”]+)”|"([^"]+)"|‘([^’]+)’/u',
            $text,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER,
        );

        /** @var list<array<int, array{0: string, 1: int}>> $matches */
        foreach ($matches as $match) {
            foreach ([1, 2, 3] as $group) {
                if (! isset($match[$group]) || $match[$group][1] < 0) {
                    continue;
                }

                $inner = $match[$group][0];

                if (self::countWords($inner) < self::MIN_QUOTE_WORDS) {
                    continue;
                }

                return [
                    'start' => $match[0][1],
                    'end' => $match[0][1] + strlen($match[0][0]),
                    'inner_start' => $match[$group][1],
                    'inner_end' => $match[$group][1] + strlen($inner),
                ];
            }
        }

        return null;
    }

    /**
     * Replace byte ranges in the markup's text nodes, keeping all surrounding
     * tags. A blank deletes the target and leaves one blank span; a mark
     * wraps the target so the full text stays readable.
     *
     * @param  list<array{start: int, end: int}>  $ranges  byte offsets in the plain text
     */
    private static function replaceRanges(string $html, array $ranges, string $mode): ?string
    {
        if ($ranges === []) {
            return null;
        }

        [$document, $root] = self::load($html);

        usort($ranges, fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($ranges as $range) {
            if (! self::applyRange($document, $root, $range['start'], $range['end'], $mode)) {
                return null;
            }
        }

        self::removeEmptyTextNodes($root);

        return self::serializeChildren($document, $root);
    }

    private static function applyRange(
        DOMDocument $document,
        DOMElement $root,
        int $start,
        int $end,
        string $mode,
    ): bool {
        $applied = false;

        foreach (self::textNodes($root) as $info) {
            if ($info['end'] <= $start || $info['start'] >= $end) {
                continue;
            }

            $node = $info['node'];
            $localStart = max(0, $start - $info['start']);
            $localEnd = min($info['end'] - $info['start'], $end - $info['start']);
            $left = substr($node->data, 0, $localStart);

            if ($mode === 'blank') {
                $rest = substr($node->data, $localEnd);
                $node->data = $left;
                $parent = $node->parentNode;

                if (! $parent instanceof DOMElement) {
                    return false;
                }

                $blank = self::blankSpan($document);
                $parent->insertBefore($blank, $node->nextSibling);

                if ($rest !== '') {
                    $parent->insertBefore($document->createTextNode($rest), $blank->nextSibling);
                }

                $applied = true;

                continue;
            }

            $target = substr($node->data, $localStart, $localEnd - $localStart);
            $rest = substr($node->data, $localEnd);
            $node->data = $left;
            $parent = $node->parentNode;

            if (! $parent instanceof DOMElement) {
                return false;
            }

            $mark = $document->createElement('mark');
            $mark->appendChild($document->createTextNode($target));
            $parent->insertBefore($mark, $node->nextSibling);

            if ($rest !== '') {
                $parent->insertBefore($document->createTextNode($rest), $mark->nextSibling);
            }

            $applied = true;
        }

        return $applied;
    }

    /**
     * The slot the prompt shows where the missing word was. The underscore is
     * the visible marker; role="img" keeps the announcement as "missing word"
     * instead of reading the character out.
     */
    private static function blankSpan(DOMDocument $document): DOMElement
    {
        $span = $document->createElement('span');
        $span->setAttribute('data-blank', '');
        $span->setAttribute('role', 'img');
        $span->setAttribute('aria-label', 'missing word');
        $span->appendChild($document->createTextNode('_'));

        return $span;
    }

    /**
     * @return array{0: DOMDocument, 1: DOMElement}
     */
    private static function load(string $html): array
    {
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><body><div>'.$html.'</div></body>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body instanceof DOMElement) {
            foreach ($body->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->nodeName) === 'div') {
                    return [$document, $child];
                }
            }

            return [$document, $body];
        }

        $root = $document->createElement('div');
        $document->appendChild($root);

        return [$document, $root];
    }

    /**
     * Elements with any of these tags, in document order.
     *
     * @param  list<string>  $tags
     * @return list<DOMElement>
     */
    private static function elementsByTag(DOMNode $root, array $tags): array
    {
        $found = [];

        foreach ($root->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if (in_array(strtolower($child->nodeName), $tags, true)) {
                $found[] = $child;
            }

            $found = [...$found, ...self::elementsByTag($child, $tags)];
        }

        return $found;
    }

    private static function nearestBlock(DOMElement $element): ?DOMElement
    {
        for ($node = $element->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if (in_array(strtolower($node->nodeName), ['p', 'li'], true)) {
                return $node;
            }
        }

        return null;
    }

    private static function serializeBlock(DOMDocument $document, DOMElement $element): string
    {
        $inner = self::serializeChildren($document, $element);

        if (strtolower($element->nodeName) === 'li') {
            return '<ul><li>'.$inner.'</li></ul>';
        }

        return '<p>'.$inner.'</p>';
    }

    private static function serializeChildren(DOMDocument $document, DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }

    /** The visible text of a node, concatenated in document order. */
    private static function nodeText(DOMNode $node): string
    {
        $text = '';

        foreach (self::textNodes($node) as $info) {
            $text .= $info['node']->data;
        }

        return $text;
    }

    /**
     * @return list<array{node: DOMText, start: int, end: int}>
     */
    private static function textNodes(DOMNode $node): array
    {
        $nodes = [];
        $offset = 0;
        self::collectTextNodes($node, $nodes, $offset);

        return $nodes;
    }

    /**
     * @param  list<array{node: DOMText, start: int, end: int}>  $nodes
     */
    private static function collectTextNodes(DOMNode $node, array &$nodes, int &$offset): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $length = strlen($child->data);
                $nodes[] = ['node' => $child, 'start' => $offset, 'end' => $offset + $length];
                $offset += $length;

                continue;
            }

            if ($child instanceof DOMElement) {
                self::collectTextNodes($child, $nodes, $offset);
            }
        }
    }

    private static function removeEmptyTextNodes(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                if ($child->data === '') {
                    $node->removeChild($child);
                }

                continue;
            }

            if ($child instanceof DOMElement) {
                self::removeEmptyTextNodes($child);
            }
        }
    }

    /** Whether any visible text sits outside a bold part, so the note is not all bold. */
    private static function hasVisibleOutsideBold(DOMNode $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                if (trim($child->data) !== '' && ! self::hasBoldAncestor($child)) {
                    return true;
                }

                continue;
            }

            if ($child instanceof DOMElement && self::hasVisibleOutsideBold($child)) {
                return true;
            }
        }

        return false;
    }

    private static function hasBoldAncestor(DOMNode $node): bool
    {
        for ($parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
            if (strtolower($parent->nodeName) === 'strong') {
                return true;
            }
        }

        return false;
    }
}
