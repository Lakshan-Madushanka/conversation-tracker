<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FeedbackType;
use App\Support\NotebookDeletionEffect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The notebook delete warning, as the page needs it: what the notebook is,
 * the saved rows and distinct study items it holds by kind, the saved custom
 * reviews that lose items, and the signed token to confirm.
 *
 * @mixin NotebookDeletionEffect
 */
final class NotebookDeletionPreviewResource extends JsonResource
{
    public function __construct(NotebookDeletionEffect $effect)
    {
        parent::__construct($effect);
    }

    /**
     * @return array{
     *     notebook: array{id: int, name: string, topic: string},
     *     counts: array{words: int, phrases: int, grammar_notes: int},
     *     items: array{words: int, phrases: int, grammar_notes: int},
     *     reviews: list<array{id: int, name: string, removed: int, remaining: int, becomes_empty: bool}>,
     *     token: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var NotebookDeletionEffect $effect */
        $effect = $this->resource;
        $notebook = $effect->notebook;
        $items = $effect->items;

        return [
            'notebook' => [
                'id' => $notebook->id,
                'name' => $notebook->name,
                'topic' => $notebook->topic->name,
            ],
            'counts' => $effect->counts,
            'items' => [
                'words' => count($items[FeedbackType::Vocabulary->value] ?? []),
                'phrases' => count($items[FeedbackType::Phrase->value] ?? []),
                'grammar_notes' => count($items[FeedbackType::GrammarNote->value] ?? []),
            ],
            'reviews' => $effect->reviews,
            'token' => $effect->token(),
        ];
    }
}
