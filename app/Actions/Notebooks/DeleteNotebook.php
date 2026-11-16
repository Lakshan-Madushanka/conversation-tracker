<?php

declare(strict_types=1);

namespace App\Actions\Notebooks;

use App\Actions\CustomReviews\PruneCustomReviewItems;
use App\Exceptions\NotebookDeletionStaleException;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Queries\NotebookDeletionPreview;
use App\Support\DeletionToken;
use Illuminate\Support\Facades\DB;

/**
 * Removes a notebook and every entry inside it, permanently.
 *
 * The confirmation carries the signed state the warning showed. The notebook
 * is locked while that state is checked, so content changed in another tab is
 * refused instead of deleting rows the learner has not seen.
 *
 * Only this notebook's own rows go: its topic, matching words in sessions and
 * other notebooks, and the marks stored under their own scopes stay as they
 * are. The custom-review references to the items that stop existing leave
 * every review in the same transaction, and the attempts that recorded them
 * gain their Deleted marker; a review left with nothing becomes Empty. Marks
 * and daily schedules are not removed, exactly as a scoped item that disappears
 * is treated in Collections.
 *
 * A notebook that is already gone is treated as success, so a double click or
 * a retried request stays safe.
 */
final readonly class DeleteNotebook
{
    public function __construct(
        private NotebookDeletionPreview $preview,
        private PruneCustomReviewItems $pruneCustomReviewItems,
    ) {}

    public function handle(int $notebookId, string $token): NotebookDeletionResult
    {
        return DB::transaction(function () use ($notebookId, $token): NotebookDeletionResult {
            /** @var Notebook|null $notebook */
            $notebook = Notebook::query()
                ->whereKey($notebookId)
                ->lockForUpdate()
                ->first();

            if (! $notebook instanceof Notebook) {
                return new NotebookDeletionResult(
                    notebookId: $notebookId,
                    name: '',
                    counts: ['words' => 0, 'phrases' => 0, 'grammar_notes' => 0],
                    alreadyDeleted: true,
                );
            }

            $effect = $this->preview->for($notebook);

            if (! DeletionToken::matches($token, $effect->fingerprint())) {
                throw new NotebookDeletionStaleException($effect);
            }

            PracticeFeedbackEntry::query()
                ->where('notebook_id', $notebook->id)
                ->delete();

            $notebook->delete();

            $this->pruneCustomReviewItems->handleFor($effect->items);

            return new NotebookDeletionResult(
                notebookId: (int) $notebook->id,
                name: $notebook->name,
                counts: $effect->counts,
                alreadyDeleted: false,
            );
        });
    }
}
