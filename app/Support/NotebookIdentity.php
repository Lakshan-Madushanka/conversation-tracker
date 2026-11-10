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
}
