<?php

declare(strict_types=1);

use App\Support\ListEntries;

test('pasted text becomes one entry per line', function (): void {
    $lines = implode("\n", [
        'reluctant — unwilling, not eager',
        'affordable — not too expensive',
        'catch up — talk after a long time',
        'reservation — a booking',
        'relish — to enjoy very much',
        'ahead of time — early',
        'on second thought — after reconsidering',
        'to be into something — to like something',
        'a waste of time — something useless',
        'get the hang of it — learn how to do it',
    ]);

    expect(ListEntries::split($lines))->toHaveCount(10)
        ->and(ListEntries::split($lines)[0])->toBe('reluctant — unwilling, not eager');
});

test('blank lines are ignored', function (): void {
    expect(ListEntries::split("one\n\n\n   \ntwo\n"))->toBe(['one', 'two']);
});

test('bullets and numbering at the start of a line are removed', function (string $line, string $expected): void {
    expect(ListEntries::cleanLine($line))->toBe($expected);
})->with([
    ['• reservation', 'reservation'],
    ['•reservation', 'reservation'],
    ['- reservation', 'reservation'],
    ['* reservation', 'reservation'],
    ['1. reservation', 'reservation'],
    ['2) reservation', 'reservation'],
    ['(3) reservation', 'reservation'],
    ['(a) reservation', 'reservation'],
    ['A. reservation', 'reservation'],
    ['- - nested reservation', 'nested reservation'],
    ['  12)   reservation  ', 'reservation'],
]);

test('text that only contains a number or a dash is left alone', function (string $line): void {
    expect(ListEntries::cleanLine($line))->toBe($line);
})->with([
    '3 days',
    'reluctant — unwilling',
    'e.g. reservation',
    '2.5 grams',
    '-5 degrees',
    'I go → I went',
    '**bold** stays',
    '*italic* stays',
]);

test('spacing and line endings are tidied up', function (): void {
    expect(ListEntries::split("  extra   spaces \t here  \r\nsecond\tline\rthird\r\n"))
        ->toBe(['extra spaces here', 'second line', 'third']);
});

test('a very long line stays one whole entry', function (): void {
    $line = str_repeat('a long entry that the learner pasted by mistake ', 30);

    expect(ListEntries::split($line))->toBe([trim($line)])
        ->and(ListEntries::split($line)[0])->toHaveLength(strlen(trim($line)));
});
