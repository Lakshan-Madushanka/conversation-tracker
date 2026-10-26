<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Signs the state a delete warning showed, so a confirmation can only remove
 * the session the learner actually saw. Tokens cannot be guessed or reused for
 * another session, and comparing them is constant-time.
 */
final class DeletionToken
{
    public static function sign(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }

    public static function matches(string $token, string $value): bool
    {
        return hash_equals(self::sign($value), $token);
    }

    private static function key(): string
    {
        $key = config('app.key');

        return is_string($key) && $key !== ''
            ? $key
            : 'conversation-tracker-deletion-token';
    }
}
