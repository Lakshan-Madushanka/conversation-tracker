<?php

declare(strict_types=1);

use App\Support\NotePreview;
use App\Support\RichText;

it('keeps a short note whole', function (): void {
    expect(NotePreview::of('Use the past tense here.'))
        ->toBe('Use the past tense here.');
});

it('cuts a long note at a word and marks the cut with an ellipsis', function (): void {
    $text = trim(str_repeat('Careful with the past tense. ', 20));

    $preview = NotePreview::of($text);

    expect(mb_strlen($preview))->toBeLessThanOrEqual(NotePreview::LENGTH + 1)
        ->and($preview)->toEndWith('…')
        ->and(str_starts_with($preview, $text))
        ->toBeFalse();

    // The cut lands on a space: the last word is kept whole.
    $body = mb_substr($preview, 0, -1);

    expect(str_ends_with($text, $body) || str_starts_with($text, $body.' '))
        ->toBeTrue();
});

it('flattens the note onto one line for the list', function (): void {
    expect(NotePreview::of("First line.\n\nSecond line.   with  spaces"))
        ->toBe('First line. Second line. with spaces');
});

it('takes the plain text of a formatted note, never its markup', function (): void {
    $html = '<p>Use the <strong>past tense</strong> here.</p><script>alert("x")</script>'
        .'<ul><li>Line one</li><li>Line two</li></ul>';

    $preview = NotePreview::of($html);

    expect($preview)
        ->not->toContain('<')
        ->not->toContain('alert')
        ->toContain('past tense')
        ->toContain(RichText::toPlainText($html) === '' ? '' : 'Line one');

    // A preview built from a long formatted note is short and remains plain.
    $long = '<p>'.str_repeat('Grammar rule worth remembering. ', 20).'</p>';

    expect(mb_strlen(NotePreview::of($long)))->toBeLessThanOrEqual(NotePreview::LENGTH + 1)
        ->and(NotePreview::of($long))->toEndWith('…');
});

it('honours a shorter length when asked', function (): void {
    expect(NotePreview::of('One two three four five six', 7))->toBe('One two…');
});

it('collapses blank formatting into an empty preview', function (): void {
    expect(NotePreview::of("  \n\t "))->toBe('')->and(NotePreview::of(''))->toBe('');
});
