<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CustomReviews\AddItemsToReview;
use App\Actions\CustomReviews\ClearCustomReview;
use App\Actions\CustomReviews\CreateCustomReview;
use App\Actions\CustomReviews\PruneCustomReviewItems;
use App\Actions\CustomReviews\RemoveCustomReviewItems;
use App\Actions\CustomReviews\SetCustomReviewStatus;
use App\Actions\CustomReviews\UpdateCustomReview;
use App\Enums\CustomReviewAddOutcome;
use App\Http\Requests\AddCustomReviewItemsRequest;
use App\Http\Requests\RemoveCustomReviewItemsRequest;
use App\Http\Requests\UpdateCustomReviewRequest;
use App\Http\Requests\UpdateCustomReviewStatusRequest;
use App\Http\Resources\CustomReviewGroupResource;
use App\Http\Resources\CustomReviewSummaryResource;
use App\Models\CustomReview;
use App\Queries\CustomReviewContents;
use App\Queries\CustomReviewItems;
use App\Support\CustomReviewReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The custom reviews the learner builds and keeps: starting one, gathering
 * items into it, and the page that lists what it holds.
 *
 * These hold references only. Nothing here reads or writes a mark, a status,
 * a rating, a schedule or a session, and a review's page is the one place
 * whose read repairs itself: references whose item no longer exists are
 * removed before the list is built.
 *
 * Not to be mistaken for `CustomReviewController`, which is the Review
 * feature's Quick review chooser — a one-off sitting that saves nothing.
 */
final class CustomReviewsController extends Controller
{
    /**
     * Start a review, which is saved and named at once, and open it.
     *
     * The button that calls this arrives with the listing in Step 3; "Add to
     * review" reaches the same action when nothing is being built yet.
     */
    public function store(CreateCustomReview $createCustomReview): RedirectResponse
    {
        return to_route('customReviews.show', $createCustomReview->handle());
    }

    public function show(
        Request $request,
        CustomReview $customReview,
        PruneCustomReviewItems $pruneCustomReviewItems,
        CustomReviewItems $items,
        CustomReviewContents $contents,
    ): Response {
        // A deleted item leaves no trace; an archived one stays but is marked
        // not available by the query. The repair can empty the review, so the
        // page reads its name and status again rather than trusting what it
        // arrived with.
        $pruneCustomReviewItems->handle();
        $customReview->refresh();

        $back = $request->query('from');
        $count = $items->count($customReview);

        return Inertia::render('custom-reviews/Show', [
            'review' => CustomReviewSummaryResource::make($customReview, $count)->resolve(),
            'groups' => array_map(
                fn (array $group): array => CustomReviewGroupResource::make($group)->resolve(),
                $contents->groups($customReview),
            ),
            'back_url' => CustomReviewReturn::url(is_string($back) ? $back : null),
            // + Add items opens Collections narrowed to the items this review
            // can use, with the review named in the address so every add lands
            // in it without asking.
            'add_items_url' => route('collections.index', [
                'review' => $customReview->id,
                'eligible' => 1,
            ]),
        ]);
    }

    /**
     * Save a name or description the learner changed. Each field arrives on
     * its own from the page's autosave, so a partial request changes only the
     * field it names.
     */
    public function update(
        UpdateCustomReviewRequest $request,
        CustomReview $customReview,
        UpdateCustomReview $updateCustomReview,
        CustomReviewItems $items,
    ): JsonResponse {
        $review = $updateCustomReview->handle($customReview, $request->changes());

        return response()->json($this->savedAnswer($review, $items));
    }

    /** The learner's own Draft-or-Finished move, with no other field touched. */
    public function updateStatus(
        UpdateCustomReviewStatusRequest $request,
        CustomReview $customReview,
        SetCustomReviewStatus $setCustomReviewStatus,
        CustomReviewItems $items,
    ): JsonResponse {
        $review = $setCustomReviewStatus->handle($customReview, $request->status());

        return response()->json($this->savedAnswer($review, $items));
    }

    /**
     * Add the chosen items, repeating safely.
     *
     * The server decides where they go: one review being built takes them
     * without a word, several ask which one, none creates one and names it.
     * An add past the recommended size answers with the count it would reach
     * and waits for the learner to go ahead on the same request.
     */
    public function storeItems(
        AddCustomReviewItemsRequest $request,
        AddItemsToReview $addItemsToReview,
    ): JsonResponse {
        $result = $addItemsToReview->handle(
            $request->kind(),
            $request->identities(),
            $request->reviewId(),
            $request->confirmed(),
        );

        return match ($result['outcome']) {
            CustomReviewAddOutcome::NeedsReviewChoice => response()->json([
                'outcome' => CustomReviewAddOutcome::NeedsReviewChoice->value,
                'reviews' => $this->summaries($result['choices']),
            ]),
            CustomReviewAddOutcome::NeedsConfirmation => response()->json([
                'outcome' => CustomReviewAddOutcome::NeedsConfirmation->value,
                'review' => $this->summary($result['review'], $result['resulting_count']),
                'resulting_count' => $result['resulting_count'],
            ]),
            CustomReviewAddOutcome::Added => response()->json([
                'outcome' => CustomReviewAddOutcome::Added->value,
                'review' => $this->summary($result['review'], $result['resulting_count']),
                'added_count' => count($result['added']),
                'already_count' => count($result['already']),
                'added_identities' => $result['added'],
                'already_identities' => $result['already'],
                'total' => $result['resulting_count'],
            ]),
        };
    }

    /** Take the chosen items out again. Removing something already gone is calm. */
    public function destroyItems(
        RemoveCustomReviewItemsRequest $request,
        CustomReview $customReview,
        RemoveCustomReviewItems $removeCustomReviewItems,
    ): JsonResponse {
        $result = $removeCustomReviewItems->handle(
            $customReview,
            $request->kind(),
            $request->identities(),
        );

        return response()->json([
            'removed_count' => count($result['removed']),
            'removed_identities' => $result['removed'],
            'total' => $result['total'],
        ]);
    }

    /** Empty one review. The page asks for confirmation before calling this. */
    public function clear(
        CustomReview $customReview,
        ClearCustomReview $clearCustomReview,
    ): JsonResponse {
        return response()->json([
            'cleared_count' => $clearCustomReview->handle($customReview),
            'total' => 0,
        ]);
    }

    /**
     * The shape the autosave rules read: the row, its version, and the review
     * as it now stands. A custom review has no version column, so the marker
     * stays at zero.
     *
     * @return array{id: int, version: int, review: array<string, mixed>}
     */
    private function savedAnswer(CustomReview $review, CustomReviewItems $items): array
    {
        return [
            'id' => (int) $review->id,
            'version' => 0,
            'review' => CustomReviewSummaryResource::make(
                $review,
                $items->count($review),
            )->resolve(),
        ];
    }

    /**
     * @param  list<array{review: CustomReview, item_count: int}>  $choices
     * @return list<array<string, mixed>>
     */
    private function summaries(array $choices): array
    {
        return array_map(
            fn (array $choice): array => CustomReviewSummaryResource::make(
                $choice['review'],
                $choice['item_count'],
            )->resolve(),
            $choices,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summary(?CustomReview $review, int $itemCount): ?array
    {
        return $review instanceof CustomReview
            ? CustomReviewSummaryResource::make($review, $itemCount)->resolve()
            : null;
    }
}
