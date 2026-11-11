<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Notebooks\CreateNotebook;
use App\Actions\Notebooks\UpdateNotebook;
use App\Exceptions\NotebookConflictException;
use App\Http\Requests\AutosaveNotebookRequest;
use App\Http\Requests\NotebooksListingRequest;
use App\Http\Requests\StoreNotebookRequest;
use App\Http\Resources\NotebookCardResource;
use App\Http\Resources\NotebookEditorResource;
use App\Models\Notebook;
use App\Models\Topic;
use App\Queries\NotebooksListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotebooksController extends Controller
{
    /**
     * The notebook listing. A normal visit renders the page; the same address
     * asked for JSON returns the next page for "load more".
     */
    public function index(NotebooksListingRequest $request, NotebooksListing $listing): Response|JsonResponse
    {
        $search = $request->search();

        $paginator = $listing->paginate(
            $search,
            $request->cursor(),
            $request->wantsJson() ? $request->perPage() : NotebooksListing::PAGE_SIZE,
        );

        $page = [
            'notebooks' => array_map(
                fn (Notebook $notebook): array => NotebookCardResource::make($notebook)->resolve(),
                $paginator->items(),
            ),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
            'total' => $listing->total($search),
            'topics' => Topic::pickerOptions(),
            'search' => $search,
        ];

        if ($request->wantsJson()) {
            return response()->json($page);
        }

        return Inertia::render('notebooks/Index', $page);
    }

    public function store(StoreNotebookRequest $request, CreateNotebook $createNotebook): RedirectResponse
    {
        $notebook = $createNotebook->handle($request->validated());

        return redirect()->route('notebooks.show', $notebook);
    }

    /** The editable board. A notebook has no finished or archived state. */
    public function show(Notebook $notebook): Response
    {
        return Inertia::render('notebooks/Board', [
            'notebook' => NotebookEditorResource::make($notebook)->resolve(),
            'topics' => Topic::pickerOptions(),
        ]);
    }

    /**
     * Saves an automatic update. A stale version returns 409 with the latest
     * saved notebook so the client can offer to reload it.
     */
    public function autosaveUpdate(
        Notebook $notebook,
        AutosaveNotebookRequest $request,
        UpdateNotebook $updateNotebook,
    ): JsonResponse {
        try {
            $notebook = $updateNotebook->handle($notebook, $request->validated());
        } catch (NotebookConflictException $exception) {
            return response()->json([
                'conflict' => true,
                'message' => 'This notebook was changed somewhere else. Load the latest version or keep your changes.',
                'notebook' => NotebookEditorResource::make($exception->notebook)->resolve(),
            ], 409);
        }

        return response()->json([
            'notebook' => NotebookEditorResource::make($notebook)->resolve(),
            'saved_at' => now()->toIso8601String(),
        ]);
    }
}
