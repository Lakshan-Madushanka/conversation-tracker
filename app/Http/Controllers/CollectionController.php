<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CollectionTab;
use App\Enums\FeedbackType;
use App\Http\Requests\CollectionRequest;
use App\Http\Resources\CollectionItemResource;
use App\Http\Resources\CollectionNoteResource;
use App\Queries\TableCollection;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    /**
     * The four personal collections, gathered live from finished sessions.
     *
     * A normal visit renders the page; the same address asked for JSON returns
     * the next page for "Load more". The tab and the opened item travel in the
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
            'tabs' => $this->tabs($collection),
            'items' => $this->items($tab, $page['items']),
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

        $item = $request->item();
        $openItem = null;
        $missing = false;

        if ($item !== null) {
            $found = $collection->find($tab->feedbackType(), $item);

            if ($found === null) {
                $missing = true;
            } else {
                $openItem = $this->shapeItem($tab, $found);
            }
        }

        return Inertia::render('collections/Index', [
            ...$payload,
            'open_item' => $openItem,
            'open_item_missing' => $missing,
        ]);
    }

    /**
     * The four tabs, with the count of every collection that has been built.
     * My mistakes stays calm and count-free until its step.
     *
     * @return list<array{key: string, label: string, count: int|null, built: bool}>
     */
    private function tabs(TableCollection $collection): array
    {
        $counts = $collection->counts();

        return array_map(
            fn (CollectionTab $candidate): array => [
                'key' => $candidate->value,
                'label' => $candidate->label(),
                'count' => $candidate->isBuilt()
                    ? $counts[$candidate->value]
                    : null,
                'built' => $candidate->isBuilt(),
            ],
            CollectionTab::ordered(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function items(CollectionTab $tab, array $items): array
    {
        return array_map(
            fn (array $item): array => $this->shapeItem($tab, $item),
            $items,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function shapeItem(CollectionTab $tab, array $item): array
    {
        return $tab->feedbackType() === FeedbackType::GrammarNote
            ? CollectionNoteResource::make($item)->resolve()
            : CollectionItemResource::make($item)->resolve();
    }
}
