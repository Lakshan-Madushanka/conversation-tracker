<?php

declare(strict_types=1);

use App\Support\CollectionCursor;
use Carbon\CarbonImmutable;

it('round-trips a cursor through an address-safe value', function (): void {
    $moment = CarbonImmutable::parse('2026-10-27 09:15:00');

    $encoded = (new CollectionCursor('café au lait', $moment))->encode();

    expect($encoded)
        ->not->toContain('+')
        ->not->toContain('/')
        ->not->toContain('=');

    $decoded = CollectionCursor::decode($encoded);

    expect($decoded)->not->toBeNull()
        ->and($decoded?->key)->toBe('café au lait')
        ->and($decoded?->latestAt->getTimestamp())->toBe($moment->getTimestamp());
});

it('ignores a cursor it cannot read instead of failing the page', function (string $value): void {
    expect(CollectionCursor::decode($value))->toBeNull();
})->with([
    'empty' => '',
    'not base64' => 'not a cursor!',
    'not json' => base64_encode('hello'),
    'missing key' => rtrim(strtr(base64_encode('{"t":123}'), '+/', '-_'), '='),
    'missing time' => rtrim(strtr(base64_encode('{"k":"relish"}'), '+/', '-_'), '='),
    'time is text' => rtrim(strtr(base64_encode('{"t":"123","k":"relish"}'), '+/', '-_'), '='),
    'key is a number' => rtrim(strtr(base64_encode('{"t":123,"k":4}'), '+/', '-_'), '='),
    'empty key' => rtrim(strtr(base64_encode('{"t":123,"k":""}'), '+/', '-_'), '='),
]);

it('reads a cursor without padding', function (): void {
    $moment = CarbonImmutable::parse('2026-10-27 09:15:00');
    $encoded = (new CollectionCursor('relish', $moment))->encode();

    expect(CollectionCursor::decode($encoded)?->key)->toBe('relish');
});
