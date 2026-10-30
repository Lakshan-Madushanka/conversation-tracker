<?php

declare(strict_types=1);

use App\Support\MistakeIdentity;

/**
 * The pair key alone decides which mistake a row belongs to, so two rows
 * whose pair differs only in capitals, accents, extra spaces or formatting
 * share one key — and a pair that holds nothing at all is never gathered.
 */
it('gives the same key to the same pair, however it was written', function (): void {
    $key = MistakeIdentity::pairKey('Ayer I went al mercado', 'Yesterday I went to the market.');

    expect(MistakeIdentity::pairKey('  AYÉR I WENT  AL MERCADO  ', 'yesterday i went to the market.'))
        ->toBe($key)
        ->and(MistakeIdentity::pairKey("Ayer I went\nal mercado", "Yesterday I went\tto the market."))
        ->toBe($key);
});

it('gives different keys to mistakes whose what-I-said column differs', function (): void {
    expect(MistakeIdentity::pairKey('I am agree', 'I agree'))
        ->not->toBe(MistakeIdentity::pairKey('I are agree', 'I agree'));
});

it('gives different keys to the same words with and without a correction', function (): void {
    expect(MistakeIdentity::pairKey('I am agree', ''))
        ->not->toBe(MistakeIdentity::pairKey('I am agree', 'I agree'));
});

it('begins every key with the mistake prefix and keeps keys opaque', function (): void {
    $key = MistakeIdentity::pairKey('I am agree', 'I agree');

    expect($key)->toStartWith('mistake-')
        ->and(mb_strlen($key))->toBe(mb_strlen('mistake-') + 24)
        ->and($key)->not->toContain(' ');
});

it('keeps the separator invisible, so two pairs never collide into one key', function (): void {
    // Without a separator, `cafe` + `tomato` could read the same as
    // `cafe to` + `mato`. The NUL separator inside the raw pair stops that.
    expect(MistakeIdentity::pairKey('cafe', 'tomato'))
        ->not->toBe(MistakeIdentity::pairKey('cafe to', 'mato'));
});

it('says a pair is blank only when both columns hold nothing', function (): void {
    expect(MistakeIdentity::isBlank('', ''))
        ->toBeTrue()
        ->and(MistakeIdentity::isBlank('   ', '   '))
        ->toBeTrue()
        ->and(MistakeIdentity::isBlank('I am agree', ''))
        ->toBeFalse()
        ->and(MistakeIdentity::isBlank('', 'I agree'))
        ->toBeFalse()
        ->and(MistakeIdentity::isBlank('  Café  ', '  '))
        ->toBeFalse();
});
