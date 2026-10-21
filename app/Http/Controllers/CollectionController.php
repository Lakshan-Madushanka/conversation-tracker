<?php

namespace App\Http\Controllers;

use App\Models\PracticeFeedbackEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('collections/Index', [
            'vocabulary' => Inertia::scroll(
                fn() => PracticeFeedbackEntry::collection(
                    PracticeFeedbackEntry::TYPE_VOCABULARY,
                    $this->getSearchTerm($request, 'vocabulary')
                )
            ),
            'phrases' => Inertia::scroll(
                fn() => PracticeFeedbackEntry::collection(
                    PracticeFeedbackEntry::TYPE_PHRASE,
                    $this->getSearchTerm($request, 'phrases')
                )
            ),
            'grammar' => Inertia::scroll(
                fn() => PracticeFeedbackEntry::collection(
                    PracticeFeedbackEntry::TYPE_GRAMMAR_NOTE,
                    $this->getSearchTerm($request, 'grammar')

                )
            ),
            'mistakes' => Inertia::scroll(
                fn() => PracticeFeedbackEntry::collection(
                    PracticeFeedbackEntry::TYPE_MISTAKE,
                    $this->getSearchTerm($request, 'mistakes')

                )
            ),
        ]);
    }

    protected function getSearchTerm(Request $request, string $type)
    {
         if($type === $request->string('forPage')->toString()) {
             return $request->string('search');
         }

         return null;
    }
}
