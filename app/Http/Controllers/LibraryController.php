<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LibrarySessionsRequest;
use App\Http\Resources\SessionCardResource;
use App\Models\PracticeSession;
use App\Queries\LibrarySessions;
use App\Support\LibraryFilters;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    /**
     * The Library: every session, finished or unfinished, in the learner's
     * chosen order. A normal visit renders the page; the same address asked
     * for JSON returns the next page for "load more".
     *
     * The filters and sort travel in the address, so a refresh, a bookmark or
     * the Back button return to exactly the list the learner was reading.
     */
    public function __invoke(LibrarySessionsRequest $request, LibrarySessions $sessions): Response|JsonResponse
    {
        $filters = $request->filters();
        $topics = $sessions->topics($filters->archivedView());
        $filters->restrictTopics(array_column($topics, 'id'));

        $paginator = $sessions->paginate(
            filters: $filters,
            cursor: $request->cursor(),
            perPage: $request->wantsJson()
                ? $request->perPage()
                : LibrarySessions::PAGE_SIZE,
        );

        $page = [
            'sessions' => array_map(
                fn (PracticeSession $session): array => SessionCardResource::make($session, $filters)->resolve(),
                $paginator->items(),
            ),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
            'total' => $sessions->total($filters),
            'all_total' => $sessions->total(LibraryFilters::forView($filters->view())),
            'archived_total' => $sessions->total(LibraryFilters::forView(LibraryFilters::VIEW_ARCHIVED)),
            'filters' => $filters->toArray(),
            'topics' => $topics,
            'timezone' => $filters->timezone(),
        ];

        if ($request->wantsJson()) {
            return response()->json($page);
        }

        return Inertia::render('sessions/Index', $page);
    }
}
