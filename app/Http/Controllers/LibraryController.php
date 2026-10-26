<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LibrarySessionsRequest;
use App\Models\PracticeSession;
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
    public function __invoke(LibrarySessionsRequest $request): Response|JsonResponse
    {
        $topics = PracticeSession::libraryTopics();
        $filters = $request->filters()->restrictTopics(
            array_column($topics, 'id'),
        );

        $page = PracticeSession::libraryPage(
            filters: $filters,
            cursor: $request->cursor(),
            perPage: $request->wantsJson()
                ? $request->perPage()
                : PracticeSession::LIBRARY_PAGE_SIZE,
        );

        $page['filters'] = $filters->toArray();
        $page['topics'] = $topics;
        $page['timezone'] = $filters->timezone();

        if ($request->wantsJson()) {
            return response()->json($page);
        }

        return Inertia::render('sessions/Index', $page);
    }
}
