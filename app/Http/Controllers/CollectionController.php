<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CollectionTab;
use App\Http\Requests\CollectionRequest;
use App\Http\Resources\CollectionItemResource;
use App\Queries\TableCollection;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * The four personal collections, gathered live from finished sessions.
     *
     * A normal visit renders the page; the same address asked for JSON returns
     * the next page for "Load more". The tab and the opened word travel in the
     * address, so a refresh, a bookmark or the Back button return to the same
     * view.
     */
    public function index(
        CollectionRequest $request,
        TableCollection $collection,
    ): Response|JsonResponse {
        $tab = $request->tab();
        $page = $collection->page(
            type: $tab->feedbackType(),
            cursor: $request->cursor(),
            perPage: $request->wantsJson()
                ? $request->perPage()
                : TableCollection::PAGE_SIZE,
        );

        $payload = [
            'tab' => $tab->value,
            'tabs' => array_map(
                fn (CollectionTab $candidate): array => [
                    'key' => $candidate->value,
                    'label' => $candidate->label(),
                    'built' => $candidate->isBuilt(),
                ],
                CollectionTab::ordered(),
            ),
            'items' => $this->items($page['items']),
            'next_cursor' => $page['next_cursor'],
            'has_more' => $page['has_more'],
            'total' => $page['total'],
            'has_finished_sessions' => $collection->hasFinishedSessions(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'items' => $payload['items'],
                'next_cursor' => $payload['next_cursor'],
                'has_more' => $payload['has_more'],
                'total' => $payload['total'],
            ]);
        }

        $word = $request->word();
        $openWord = null;
        $missing = false;

        if ($word !== null) {
            $found = $collection->find($tab->feedbackType(), $word);

            if ($found === null) {
                $missing = true;
            } else {
                $openWord = CollectionItemResource::make($found)->resolve();
            }
        }

        return Inertia::render('collections/Index', [
            ...$payload,
            'open_word' => $openWord,
            'open_word_missing' => $missing,
        ]);
    }

    /**
     * @param  list<array{key: string, word: string, meaning: string, example: string, latest_at: CarbonImmutable, seen_count: int, appearances: list<array{session_id: int, session_date: CarbonImmutable|null, topic: string, question_position: int, entry_id: string|null, entry_position: int, meaning: string, example: string}>}>  $items
     * @return list<array<string, mixed>>
     */
    private function items(array $items): array
    {
        return array_map(
            fn (array $item): array => CollectionItemResource::make($item)->resolve(),
            $items,
        );
    }
}
