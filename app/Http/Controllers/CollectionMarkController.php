<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Collections\SaveItemMark;
use App\Actions\Collections\SaveItemNotes;
use App\Exceptions\CollectionNotesConflictException;
use App\Http\Requests\CollectionMarkRequest;
use App\Http\Requests\CollectionNotesRequest;
use App\Http\Resources\CollectionMarkResource;
use Illuminate\Http\JsonResponse;

/**
 * The learner's own marks on collection items: a star, a status and personal
 * notes. Every request carries the desired state and the item's identity, so
 * repeating a request, retrying or working from two tabs cannot flip anything
 * and changes no session, entry or count.
 */
class CollectionMarkController extends Controller
{
    public function store(CollectionMarkRequest $request, SaveItemMark $saveItemMark): JsonResponse
    {
        $mark = $saveItemMark->handle(
            $request->kind(),
            $request->item(),
            $request->starred(),
            $request->status(),
        );

        return response()->json(['mark' => (new CollectionMarkResource($mark))->resolve()]);
    }

    /**
     * The learner's personal notes. A note changed in another tab is not
     * silently overwritten: the save stops and returns the latest version, so
     * the learner chooses between the two and nothing is lost.
     */
    public function storeNotes(CollectionNotesRequest $request, SaveItemNotes $saveItemNotes): JsonResponse
    {
        try {
            $mark = $saveItemNotes->handle(
                $request->kind(),
                $request->item(),
                $request->html(),
                $request->version(),
            );
        } catch (CollectionNotesConflictException $exception) {
            return response()->json([
                'conflict' => true,
                'mark' => (new CollectionMarkResource($exception->mark))->resolve(),
            ], 409);
        }

        return response()->json(['mark' => (new CollectionMarkResource($mark))->resolve()]);
    }
}
