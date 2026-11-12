<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The identity of a word or phrase saved in a notebook.
 *
 * A session word is its normalised text, shared across sessions. A notebook
 * word belongs to its notebook alone, so its key is derived from the notebook
 * and the normalised text together: the same spelling in two notebooks, or in
 * a session, can never become one item.
 *
 * The capitalised prefix is deliberate. Session keys are always lower-cased by
 * normalisation, so a key starting with a capital can never equal one, whatever
 * the learner typed. The hashed tail keeps the key fixed-length, the same way
 * a mistake's pair key does, so long text cannot overflow the identity columns.
 */
final class NotebookIdentity
{
    /**
     * The one key a notebook's word or phrase has. Comma-separated meanings
     * stay inside the text, so they never split the item.
     */
    public static function wordKey(int $notebookId, string $text): string
    {
        $pair = $notebookId."\u{0}".SearchText::normalize($text);

        return 'Notebook-'.substr(sha1($pair), 0, 24);
    }

    /**
     * Whether an identity is one of these keys rather than a session word.
     * Normalising text would lower-case the key, so every caller that reduces
     * an identity must ask this first.
     */
    public static function isWordKey(string $identity): bool
    {
        return preg_match('/^Notebook-[0-9a-f]{24}$/', $identity) === 1;
    }
}
