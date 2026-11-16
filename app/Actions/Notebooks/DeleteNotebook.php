<?php

declare(strict_types=1);

namespace App\Actions\Notebooks;

use App\Actions\CustomReviews\PruneCustomReviewItems;
use App\Enums\FeedbackType;
use App\Exceptions\NotebookDeletionStaleException;
use App\Models\CollectionItemMark;
use App\Models\Notebook;
use App\Models\PracticeFeedbackEntry;
use App\Models\ReviewSchedule;
use App\Models\ReviewSuggestionDecline;
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
 * other notebooks stay as they are. The custom-review references to the items
 * that stop existing leave every review in the same transaction, the attempts
 * that recorded them gain their Deleted marker, and the learner's marks (star,
 * status and notes), their review schedules and their suggestion declines go
 * with them: a notebook's identity never returns, so these rows could never be
 * reached again. A review left with nothing becomes Empty.
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
            $this->deleteScopedRecords($effect->items);

            return new NotebookDeletionResult(
                notebookId: (int) $notebook->id,
                name: $notebook->name,
                counts: $effect->counts,
                alreadyDeleted: false,
            );
        });
    }

    /**
     * The records kept for items that stop existing go with them: marks,
     * review schedules and suggestion declines. All three are keyed by the
     * item's identity, and no other scope can hold a notebook's identities, so
     * nothing else matches. Rating history stays, because it belongs to the
     * review it was given in, not to the item.
     *
     * @param  array<string, list<string>>  $items
     */
    private function deleteScopedRecords(array $items): void
    {
        foreach ($items as $kind => $identities) {
            $type = FeedbackType::from($kind);

            CollectionItemMark::query()
                ->where('kind', $kind)
                ->whereIn('item_key', array_map(
                    fn (string $identity): string => CollectionItemMark::keyFor($type, $identity),
                    $identities,
                ))
                ->delete();

            ReviewSchedule::query()
                ->where('kind', $kind)
                ->whereIn('item_identity', $identities)
                ->delete();

            ReviewSuggestionDecline::query()
                ->where('kind', $kind)
                ->whereIn('item_identity', $identities)
                ->delete();
        }
    }
}
