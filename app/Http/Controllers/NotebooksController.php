<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CustomReviews\CreateReviewFromNotebook;
use App\Actions\Notebooks\CreateNotebook;
use App\Actions\Notebooks\UpdateNotebook;
use App\Exceptions\NotebookConflictException;
use App\Http\Requests\AutosaveNotebookRequest;
use App\Http\Requests\CreateNotebookReviewRequest;
use App\Http\Requests\NotebookReviewCandidatesRequest;
use App\Http\Requests\NotebooksListingRequest;
use App\Http\Requests\StoreNotebookRequest;
use App\Http\Resources\NotebookCardResource;
use App\Http\Resources\NotebookEditorResource;
use App\Http\Resources\NotebookReviewCandidateResource;
use App\Models\Notebook;
use App\Models\Topic;
use App\Queries\NotebookReviewCandidates;
use App\Queries\NotebooksListing;
use App\Support\CustomReviewRules;
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
     * The items Create review and practise can offer from this notebook, with
     * the number that can actually be practised. Read-only: nothing is saved.
     */
    public function reviewCandidates(
        Notebook $notebook,
        NotebookReviewCandidatesRequest $request,
        NotebookReviewCandidates $candidates,
    ): JsonResponse {
        $list = $candidates->for($notebook, $request->kinds());

        $eligible = count(array_filter(
            $list,
            fn (array $candidate): bool => $candidate['eligible'],
        ));

        return response()->json([
            'candidates' => array_map(
                fn (array $candidate): array => NotebookReviewCandidateResource::make($candidate)->resolve(),
                $list,
            ),
            'count' => count($list),
            'eligible_count' => $eligible,
            'recommended' => CustomReviewRules::RECOMMENDED_ITEMS,
            'maximum' => CustomReviewRules::MAX_ITEMS,
        ]);
    }

    /**
     * Create a new saved review from this notebook's chosen items, mark that
     * new review Finished as the learner's readiness choice, and start its
     * first attempt. Every answer is decided from the notebook's current
     * content, and a repeated creation key returns the review already made.
     */
    public function storeReview(
        Notebook $notebook,
        CreateNotebookReviewRequest $request,
        CreateReviewFromNotebook $createReviewFromNotebook,
    ): JsonResponse {
        $result = $createReviewFromNotebook->handle(
            $notebook,
            $request->creationKey(),
            $request->identities(),
            $request->kinds(),
            $request->confirmed(),
        );

        $review = $result['review'];
        $attempt = $result['attempt'];

        return response()->json([
            'outcome' => $result['outcome']->value,
            'review_id' => $review?->id,
            'review_url' => $review === null
                ? null
                : route('customReviews.show', ['customReview' => $review->id]),
            'attempt_url' => $review !== null && $attempt !== null
                ? route('customReviews.attempts.show', [
                    'customReview' => $review->id,
                    'attempt' => $attempt->id,
                ])
                : null,
            'count' => $result['count'],
            'maximum' => $result['maximum'],
            'message' => $result['message'],
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
