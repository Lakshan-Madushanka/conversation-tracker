<?php

declare(strict_types=1);

use App\Support\Ordinal;

it('words a number the way a reader does', function (int $number, string $expected): void {
    expect(Ordinal::of($number))->toBe($expected);
})->with([
    [1, '1st'],
    [2, '2nd'],
    [3, '3rd'],
    [4, '4th'],
    [10, '10th'],
    [11, '11th'],
    [12, '12th'],
    [13, '13th'],
    [21, '21st'],
    [22, '22nd'],
    [23, '23rd'],
    [101, '101st'],
    [111, '111th'],
]);
