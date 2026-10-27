<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PracticeSessions\ArchivePracticeSessions;
use App\Actions\PracticeSessions\DeletePracticeSessions;
use App\Actions\PracticeSessions\RestorePracticeSessions;
use App\Exceptions\SessionDeletionBatchStaleException;
use App\Http\Requests\BulkSessionDeletionPreviewRequest;
use App\Http\Requests\BulkSessionIdsRequest;
use App\Http\Requests\DeleteBulkSessionsRequest;
use App\Http\Requests\LibrarySessionsRequest;
use App\Http\Resources\SessionDeletionBatchPreviewResource;
use App\Queries\LibrarySessions;
use App\Queries\SessionDeletionBatchPreview;
use Illuminate\Http\JsonResponse;

/**
 * What happens when the learner selects several sessions: the exact ids the
 * current filters and search match, and the bulk Archive, Restore and Delete
 * actions.
 *
 * Every write is delegated to an action. The controller only reads the
 * validated input and shapes the answer, including how many sessions were
 * skipped and why.
 */
final class LibraryBulkController extends Controller
{
    /**
     * Every session matching the current view, filters and search, so "Select
     * all" covers the whole result rather than only the loaded cards.
     */
    public function selection(
        LibrarySessionsRequest $request,
        LibrarySessions $sessions,
    ): JsonResponse {
        $filters = $request->filters();
        $topics = $sessions->topics($filters->archivedView());
        $filters->restrictTopics(array_column($topics, 'id'));

        return response()->json($sessions->selection($filters));
    }

    /** Archive every finished session in the selection, skipping the rest. */
    public function archive(
        BulkSessionIdsRequest $request,
        ArchivePracticeSessions $archivePracticeSessions,
    ): JsonResponse {
        $result = $archivePracticeSessions->handle($request->ids());

        return response()->json([
            'archived' => true,
            'archived_ids' => $result->archivedIds,
            'archived_count' => $result->archivedCount(),
            'skipped_unfinished' => $result->skippedUnfinished,
            'skipped_archived' => $result->skippedAlreadyArchived,
            'skipped_missing' => $result->skippedMissing,
        ]);
    }

    /** Bring every archived session in the selection back to the Library. */
    public function restore(
        BulkSessionIdsRequest $request,
        RestorePracticeSessions $restorePracticeSessions,
    ): JsonResponse {
        $result = $restorePracticeSessions->handle($request->ids());

        return response()->json([
            'restored' => true,
            'restored_ids' => $result->restoredIds,
            'restored_count' => $result->restoredCount(),
            'skipped_not_archived' => $result->skippedNotArchived,
            'skipped_missing' => $result->skippedMissing,
        ]);
    }

    /**
     * What deleting this selection would remove and change, worked out without
     * altering anything. The warning opens with these exact numbers.
     */
    public function deletePreview(
        BulkSessionDeletionPreviewRequest $request,
        SessionDeletionBatchPreview $preview,
    ): JsonResponse {
        $effect = $preview->forIds($request->ids(), $request->timezone());

        return response()->json(
            SessionDeletionBatchPreviewResource::make($effect)->resolve(),
        );
    }

    /**
     * Deletes the selection and everything in it for good. The signed token
     * says which state the learner confirmed; a changed selection is refused
     * with the fresh numbers and nothing is deleted. A selection that is
     * already gone is reported as deleted, so repeating stays safe.
     */
    public function delete(
        DeleteBulkSessionsRequest $request,
        DeletePracticeSessions $deletePracticeSessions,
    ): JsonResponse {
        try {
            $result = $deletePracticeSessions->handle(
                $request->ids(),
                $request->token(),
                $request->timezone(),
            );
        } catch (SessionDeletionBatchStaleException $exception) {
            return response()->json([
                'stale' => true,
                'message' => 'These sessions changed since you opened the warning. Check the numbers and confirm again.',
                'preview' => SessionDeletionBatchPreviewResource::make($exception->effect)->resolve(),
            ], 409);
        }

        return response()->json([
            'deleted' => true,
            'already_deleted' => $result->alreadyDeleted,
            'deleted_ids' => $result->deletedIds,
            'deleted_count' => $result->deletedCount(),
            'skipped_missing' => $result->skippedMissing,
        ]);
    }
}
