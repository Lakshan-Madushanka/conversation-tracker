<?php

declare(strict_types=1);

use App\Support\MistakeEntries;

test('a line splits at a single arrow', function (string $arrow): void {
    $parsed = MistakeEntries::parseLine("I want a pasta {$arrow} I'd like some pasta");

    expect($parsed['text'])->toBe('I want a pasta')
        ->and($parsed['correction_text'])->toBe("I'd like some pasta")
        ->and($parsed['reason'])->toBe('');
})->with(['→', '->', '=>']);

test('a line splits at a spaced dash', function (string $dash): void {
    $parsed = MistakeEntries::parseLine("She don't like {$dash} She doesn't like");

    expect($parsed['text'])->toBe("She don't like")
        ->and($parsed['correction_text'])->toBe("She doesn't like");
})->with(['-', '--', '–', '—']);

test('a hyphen inside a word is left alone', function (): void {
    $parsed = MistakeEntries::parseLine('I have a 3-day holiday');

    expect($parsed['text'])->toBe('I have a 3-day holiday')
        ->and($parsed['correction_html'])->toBe('');
});

test('an arrow splits even when the correction holds a hyphenated word', function (): void {
    $parsed = MistakeEntries::parseLine('I have 3 days holiday → I have a 3-day holiday');

    expect($parsed['text'])->toBe('I have 3 days holiday')
        ->and($parsed['correction_text'])->toBe('I have a 3-day holiday');
});

test('a leading bullet is removed before the separator is looked for', function (): void {
    $parsed = MistakeEntries::parseLine("- I want a pasta - I'd like some pasta");

    expect($parsed['text'])->toBe('I want a pasta')
        ->and($parsed['correction_text'])->toBe("I'd like some pasta");
});

test('a line with more than one separator is kept whole', function (string $line): void {
    $parsed = MistakeEntries::parseLine($line);

    expect($parsed['text'])->toBe($line)
        ->and($parsed['correction_html'])->toBe('')
        ->and(MistakeEntries::attention($parsed['text'], $parsed['correction_text']))
        ->toBe(MistakeEntries::ATTENTION_MORE_THAN_ONE);
})->with([
    "She don't like - She doesn't like - third person needs doesn't",
    "I want a pasta → I'd like some pasta - polite request",
    "I want pasta → I'd like pasta → please",
]);

test('a line with no separator asks for a correction', function (): void {
    $parsed = MistakeEntries::parseLine('I have a 3-day holiday');

    expect(MistakeEntries::attention($parsed['text'], $parsed['correction_text']))
        ->toBe(MistakeEntries::ATTENTION_ADD_CORRECTION);
});

test('a correction clears the attention', function (): void {
    $parsed = MistakeEntries::parseLine("I want a pasta → I'd like some pasta");

    expect(MistakeEntries::attention($parsed['text'], $parsed['correction_text']))->toBeNull();
});

test('a separator that does not count leaves the line whole', function (string $line): void {
    expect(MistakeEntries::countSeparators($line))->toBe(0)
        ->and(MistakeEntries::parseLine($line)['correction_html'])->toBe('');
})->with([
    'I like: pasta / rice, bread | butter',
    'well-known',
    'twenty-one',
    '3-day',
]);

test('the correction is stored as escaped markup', function (): void {
    $parsed = MistakeEntries::parseLine("I want pasta → I'd like <b>some</b> pasta");

    expect($parsed['correction_html'])->toBe('I&#039;d like &lt;b&gt;some&lt;/b&gt; pasta')
        ->and($parsed['correction_text'])->toBe("I'd like <b>some</b> pasta");
});
