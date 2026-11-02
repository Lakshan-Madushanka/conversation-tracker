<?php

declare(strict_types=1);

namespace App\Actions\Collections;

use App\Enums\FeedbackType;
use App\Exceptions\CollectionNotesConflictException;
use App\Models\CollectionItemMark;
use App\Support\RichText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the learner's personal notes for one collection item.
 *
 * The server cleans whatever the screen sent down to the formatting the app
 * supports, so unsafe content is never saved or shown, and keeps a plain-text
 * copy alongside it. A note of only blank space or empty formatting is stored
 * as no note at all.
 *
 * Saving is safe to repeat: the same note and version already stored change
 * nothing. A note changed in another tab raises a conflict carrying the
 * latest saved version, so nothing is silently overwritten.
 */
final class SaveItemNotes
{
    /** How many characters of note the learner may write. */
    public const int MAX_LENGTH = 10_000;

    /**
     * @throws ValidationException when the note is too long to save
     * @throws CollectionNotesConflictException when a newer note exists
     */
    public function handle(
        FeedbackType $kind,
        string $itemKey,
        string $html,
        int $version,
    ): CollectionItemMark {
        $clean = RichText::cleanWithText($html);

        if (mb_strlen($clean['text']) > self::MAX_LENGTH) {
            throw ValidationException::withMessages([
                'html' => 'A note can hold up to '.number_format(self::MAX_LENGTH)
                    .' characters. Remove a little to save it.',
            ]);
        }

        $storageKey = CollectionItemMark::keyFor($kind, $itemKey);

        return DB::transaction(function () use ($kind, $itemKey, $storageKey, $clean, $version): CollectionItemMark {
            /** @var CollectionItemMark|null $mark */
            $mark = CollectionItemMark::query()
                ->where('kind', $kind->value)
                ->where('item_key', $storageKey)
                ->lockForUpdate()
                ->first();

            if ($mark !== null && $mark->notes_version !== $version) {
                throw new CollectionNotesConflictException($mark);
            }

            // A note of only blank space or empty formatting holds no words.
            // When the item carries no mark at all, nothing is stored; when it
            // does, the stored note is cleared.
            if ($clean['html'] === '' && $mark === null) {
                return new CollectionItemMark([
                    'kind' => $kind,
                    'item_key' => $storageKey,
                    'group_key' => $itemKey,
                    'starred' => false,
                    'status' => null,
                    'notes_html' => '',
                    'notes_text' => '',
                    'notes_version' => 0,
                ]);
            }

            // The exact same note is already saved; a retried request makes
            // no change and does not bump the version.
            if (
                $mark !== null
                && $mark->notes_html === $clean['html']
                && $mark->notes_text === $clean['text']
            ) {
                if ($mark->group_key !== $itemKey) {
                    $mark->group_key = $itemKey;
                    $mark->save();
                }

                return $mark;
            }

            $mark ??= new CollectionItemMark([
                'kind' => $kind,
                'item_key' => $storageKey,
                'group_key' => $itemKey,
                'starred' => false,
                'notes_version' => 0,
            ]);

            $mark->group_key = $itemKey;
            $mark->notes_html = $clean['html'];
            $mark->notes_text = $clean['text'];
            $mark->notes_version += 1;
            $mark->markChanged();
            $mark->save();

            return $mark;
        });
    }
}
