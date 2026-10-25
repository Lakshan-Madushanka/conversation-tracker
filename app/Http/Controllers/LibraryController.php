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
     * The Library: every session, finished or unfinished, newest session date
     * first. A normal visit renders the page; the same address asked for JSON
     * returns the next page for "load more".
     */
    public function __invoke(LibrarySessionsRequest $request): Response|JsonResponse
    {
        $page = PracticeSession::libraryPage(
            cursor: $request->cursor(),
            perPage: $request->wantsJson()
                ? $request->perPage()
                : PracticeSession::LIBRARY_PAGE_SIZE,
        );

        if ($request->wantsJson()) {
            return response()->json($page);
        }

        return Inertia::render('sessions/Index', $page);
    }
}
