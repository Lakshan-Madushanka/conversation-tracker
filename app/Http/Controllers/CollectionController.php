<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('collections/Index', [
            'vocabulary' => Inertia::scroll(
                fn (): LengthAwarePaginator => PracticeFeedbackEntry::collection(
                    FeedbackType::Vocabulary,
                    $this->getSearchTerm($request, 'vocabulary')
                )
            ),
            'phrases' => Inertia::scroll(
                fn (): LengthAwarePaginator => PracticeFeedbackEntry::collection(
                    FeedbackType::Phrase,
                    $this->getSearchTerm($request, 'phrases')
                )
            ),
            'grammar' => Inertia::scroll(
                fn (): LengthAwarePaginator => PracticeFeedbackEntry::collection(
                    FeedbackType::GrammarNote,
                    $this->getSearchTerm($request, 'grammar')
                )
            ),
            'mistakes' => Inertia::scroll(
                fn (): LengthAwarePaginator => PracticeFeedbackEntry::collection(
                    FeedbackType::Mistake,
                    $this->getSearchTerm($request, 'mistakes')
                )
            ),
        ]);
    }

    /**
     * The search term for one tab: only the tab the learner is searching is
     * filtered, the rest keep their full list.
     */
    protected function getSearchTerm(Request $request, string $tab): ?string
    {
        if ($tab !== $request->string('forPage')->toString()) {
            return null;
        }

        return $request->string('search')->toString() ?: null;
    }
}
