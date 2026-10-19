<?php

declare(strict_types=1);

use App\Support\RichText;

it('keeps the supported formatting untouched', function (): void {
    $html = '<p>Hello <strong>world</strong> and <em>friends</em>.</p>'
        .'<ul><li>one</li><li>two</li></ul>'
        .'<ol><li>first</li><li>second</li></ol>'
        .'<p>a<br>b</p>';

    expect(RichText::clean($html))->toBe($html);
});

it('normalises equivalent tags to the supported ones', function (): void {
    expect(RichText::clean('<p><b>bold</b> and <i>italic</i></p>'))
        ->toBe('<p><strong>bold</strong> and <em>italic</em></p>');
});

it('removes unsupported formatting but keeps the text', function (): void {
    $html = '<h1>Title</h1>'
        .'<p style="color: red; font-size: 20px" class="fancy">Text <u>under</u>lined and <font size="4">big</font></p>'
        .'<table><tr><td>cell</td></tr></table>'
        .'<img src="photo.png" alt="pic">';

    expect(RichText::clean($html))
        ->toBe('<p>Title</p><p>Text underlined and big</p><p>cell</p>');
});

it('removes unsafe elements together with their content', function (): void {
    $html = '<p>Hello <script>alert(1)</script><iframe src="https://evil.example"></iframe>'
        .'<style>.x { color: red }</style><svg onload="alert(1)"></svg>'
        .'<strong>world</strong></p>';

    expect(RichText::clean($html))->toBe('<p>Hello <strong>world</strong></p>');
});

it('removes every attribute, including event handlers', function (): void {
    expect(RichText::clean('<p onclick="alert(1)" data-x="1">Hi</p>'))
        ->toBe('<p>Hi</p>');
});

it('ignores whitespace between blocks from pretty-printed html', function (): void {
    $html = "<p>a</p>\n  <p>b</p>\n<ul>\n  <li>x</li>\n</ul>";

    expect(RichText::clean($html))->toBe('<p>a</p><p>b</p><ul><li>x</li></ul>');
});

it('keeps meaningful spaces around inline formatting', function (): void {
    expect(RichText::clean('<p><strong>a</strong> <em>b</em></p>'))
        ->toBe('<p><strong>a</strong> <em>b</em></p>');
});

it('converts html to plain text with line breaks for blocks', function (): void {
    $html = '<p>First</p><p>Second<br>line</p>'
        .'<ul><li>bullet</li><li>another</li></ul>'
        .'<ol><li>numbered</li></ol>'
        .'<p>Fish &amp; chips</p>';

    expect(RichText::toPlainText($html))
        ->toBe("First\nSecond\nline\nbullet\nanother\nnumbered\nFish & chips");
});

it('treats blank paragraphs as blank lines in plain text', function (): void {
    expect(RichText::toPlainText('<p>a</p><p></p><p>b</p>'))->toBe("a\n\nb");
});

it('treats empty formatting as empty content', function (string $html): void {
    expect(RichText::clean($html))->toBe('')
        ->and(RichText::toPlainText($html))->toBe('');
})->with([
    'empty string' => [''],
    'empty paragraph' => ['<p></p>'],
    'only a line break' => ['<p><br></p>'],
    'only spaces' => ['<p>   </p>'],
    'only attributes' => ['<p class="x" style="color:red"></p>'],
]);

it('handles a very long passage', function (): void {
    $html = '<p>'.str_repeat('word ', 20_000).'</p>';

    expect(RichText::clean($html))->toBe($html)
        ->and(RichText::toPlainText($html))->toBe(trim(str_repeat('word ', 20_000)));
});

it('keeps only bold and italic in a single inline line', function (): void {
    $clean = RichText::cleanInlineWithText(
        '<p>I want <strong>some</strong> <b>pasta</b> and <em>rice</em>, <i>please</i>.</p>',
    );

    expect($clean['html'])->toBe('I want <strong>some</strong> <strong>pasta</strong> and <em>rice</em>, <em>please</em>.')
        ->and($clean['text'])->toBe('I want some pasta and rice, please.');
});

it('flattens blocks and line breaks into spaces when inline', function (): void {
    $clean = RichText::cleanInlineWithText('<p>first</p><p>second<br>line</p><ul><li>bullet</li></ul>');

    expect($clean['html'])->toBe('first second line bullet')
        ->and($clean['text'])->toBe('first second line bullet');
});

it('drops other formatting but keeps the words when inline', function (): void {
    $clean = RichText::cleanInlineWithText('<span style="color:red">wrong</span> <code>x</code> <a href="#">link</a>');

    expect($clean['html'])->toBe('wrong x link');
});

it('removes unsafe elements with their content when inline', function (): void {
    $clean = RichText::cleanInlineWithText('Hi <script>alert(1)</script><strong>there</strong>');

    expect($clean['html'])->toBe('Hi <strong>there</strong>')
        ->and($clean['text'])->toBe('Hi there');
});

it('treats empty inline formatting as empty content', function (string $html): void {
    expect(RichText::cleanInlineWithText($html)['html'])->toBe('')
        ->and(RichText::cleanInlineWithText($html)['text'])->toBe('');
})->with([
    'empty string' => [''],
    'empty paragraph' => ['<p></p>'],
    'only a line break' => ['<p><br></p>'],
    'only spaces' => ['<p>   </p>'],
    'empty bold' => ['<p><strong></strong></p>'],
]);
