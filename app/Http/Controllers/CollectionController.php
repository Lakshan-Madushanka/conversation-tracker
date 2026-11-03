<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CollectionTab;
use App\Enums\FeedbackType;
use App\Http\Requests\CollectionRequest;
use App\Http\Resources\CollectionItemResource;
use App\Http\Resources\CollectionMistakeResource;
use App\Http\Resources\CollectionNoteResource;
use App\Models\CollectionItemMark;
use App\Queries\CollectionMarks;
use App\Queries\TableCollection;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function __construct(private readonly CollectionMarks $marks) {}

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
        $type = $tab->feedbackType();
        $filters = $request->filters();
        $topicOptions = $collection->topicOptions($type);
        $filters = $filters->restrictTopics(array_column($topicOptions, 'id'));

        $page = $collection->page(
            type: $type,
            cursor: $request->cursor(),
            perPage: $request->wantsJson()
                ? $request->perPage()
                : TableCollection::PAGE_SIZE,
            filters: $filters,
        );

        $items = $this->items($tab, $page['items'], $filters->terms());

        if ($request->wantsJson()) {
            $payload = [
                'items' => $items,
                'next_cursor' => $page['next_cursor'],
                'has_more' => $page['has_more'],
                'total' => $page['total'],
            ];

            // A refresh asks for the surrounding counts too, so the tabs, the
            // topic picker and the attention marker follow an archive, restore,
            // edit or deletion made in a session.
            if ($request->wantsMeta()) {
                $counts = $collection->counts();

                $payload += [
                    'tabs' => $this->tabs($counts),
                    'all_total' => $counts[$tab->value],
                    'attention_count' => $collection->attentionCount($type),
                    'topic_options' => $topicOptions,
                    'filters' => $filters->toArray(),
                    'has_finished_sessions' => $collection->hasFinishedSessions(),
                ];
            }

            return response()->json($payload);
        }

        $counts = $collection->counts();

        $payload = [
            'tab' => $tab->value,
            'tabs' => $this->tabs($counts),
            'items' => $items,
            'next_cursor' => $page['next_cursor'],
            'has_more' => $page['has_more'],
            'total' => $page['total'],
            'all_total' => $counts[$tab->value],
            'attention_count' => $collection->attentionCount($type),
            'topic_options' => $topicOptions,
            'filters' => $filters->toArray(),
            'has_finished_sessions' => $collection->hasFinishedSessions(),
        ];

        $item = $request->item();
        $openItem = null;
        $missing = false;

        if ($item !== null) {
            $found = $collection->find($type, $item);

            if ($found === null) {
                $missing = true;
            } else {
                $openItem = $this->shapeItem(
                    $tab,
                    $found,
                    $this->marks->forItem($type, $item),
                    true,
                    $filters->terms(),
                );
            }
        }

        return Inertia::render('collections/Index', [
            ...$payload,
            'open_item' => $openItem,
            'open_item_missing' => $missing,
        ]);
    }

    /**
     * The four tabs, with the count of every collection gathered live.
     *
     * @param  array<string, int>  $counts
     * @return list<array{key: string, label: string, count: int}>
     */
    private function tabs(array $counts): array
    {
        return array_map(
            fn (CollectionTab $candidate): array => [
                'key' => $candidate->value,
                'label' => $candidate->label(),
                'count' => $counts[$candidate->value],
            ],
            CollectionTab::ordered(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $terms
     * @return list<array<string, mixed>>
     */
    private function items(CollectionTab $tab, array $items, array $terms = []): array
    {
        // Every mark on the page is looked up in one request, so the number
        // of database queries never grows with the number of items.
        $pageMarks = $this->marks->forItems(
            $tab->feedbackType(),
            array_column($items, 'key'),
        );

        return array_map(
            fn (array $item): array => $this->shapeItem(
                $tab,
                $item,
                $pageMarks[$item['key']] ?? null,
                false,
                $terms,
            ),
            $items,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $terms
     * @return array<string, mixed>
     */
    private function shapeItem(
        CollectionTab $tab,
        array $item,
        ?CollectionItemMark $mark,
        bool $withNotes,
        array $terms = [],
    ): array {
        return match ($tab->feedbackType()) {
            FeedbackType::GrammarNote => CollectionNoteResource::make($item, $mark, $withNotes, $terms)->resolve(),
            FeedbackType::Mistake => CollectionMistakeResource::make($item, $mark, $withNotes, $terms)->resolve(),
            default => CollectionItemResource::make(
                $item,
                $mark,
                $withNotes,
                $terms,
                $tab->feedbackType(),
            )->resolve(),
        };
    }
}
