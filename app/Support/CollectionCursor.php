<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Where the next page of a collection starts.
 *
 * A collection is grouped from many rows, so it cannot use an offset: a word
 * saved while the list is open would push every later item down and make a
 * page repeat. The cursor names the last item shown, both by its latest
 * session moment and by its key, and the next page begins after it.
 *
 * Items whose order inside the same moment is worked out from their place in
 * the session — grammar notes, asked for by question order and then note
 * order — carry that place in the cursor too, so a page never repeats or
 * skips one.
 *
 * The value travels in the address, so it is encoded as URL-safe base64 JSON.
 * A cursor this app did not make is ignored and the list starts again at the
 * top, which keeps a stale bookmark calm.
 */
final readonly class CollectionCursor
{
    public function __construct(
        public string $key,
        public CarbonImmutable $latestAt,
        public ?int $position = null,
    ) {}

    /**
     * Read a cursor from the address, or null when it cannot be trusted.
     */
    public static function decode(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        $base64 = strtr($value, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $json = base64_decode($base64, true);

        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return null;
        }

        $timestamp = $decoded['t'] ?? null;
        $key = $decoded['k'] ?? null;
        $position = $decoded['p'] ?? null;

        if (! is_int($timestamp) || ! is_string($key) || $key === '') {
            return null;
        }

        return new self(
            $key,
            CarbonImmutable::createFromTimestamp($timestamp, date_default_timezone_get()),
            is_int($position) ? $position : null,
        );
    }

    /** The value the next request sends back, exactly as it was received. */
    public function encode(): string
    {
        $json = json_encode([
            't' => $this->latestAt->getTimestamp(),
            'k' => $this->key,
            'p' => $this->position,
        ]);

        return rtrim(strtr(base64_encode($json === false ? '' : $json), '+/', '-_'), '=');
    }
}
