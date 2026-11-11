<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CollectionTab;
use App\Queries\TableCollection;
use App\Support\CollectionFilters;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What the Collections page reads from its address: the open tab, the opened
 * item, and the cursor for the next page.
 *
 * An unknown tab or item is not an error. A stale bookmark still opens the
 * page calmly, on vocabulary and with a note instead of an item panel.
 */
final class CollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string', 'max:2048'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function tab(): CollectionTab
    {
        $tab = $this->query('tab');

        return CollectionTab::fromQuery(is_string($tab) ? $tab : null);
    }

    /** The key of the item whose panel the address asks to open. */
    public function item(): ?string
    {
        $item = $this->query('item');

        return is_string($item) && $item !== '' ? $item : null;
    }

    public function cursor(): ?string
    {
        $cursor = $this->validated('cursor');

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    /**
     * Whether a JSON page should carry the counts shown around the list. A
     * quiet refresh after returning from a session asks for them, so the tabs,
     * topic picker and attention count never lag behind the items.
     */
    public function wantsMeta(): bool
    {
        return $this->query('meta') === '1';
    }

    /**
     * The review the page is topping up, when the address names one. Only a
     * positive whole number is accepted, so a stale or hand-edited address
     * quietly falls back to the ordinary Collections view.
     */
    public function reviewId(): ?int
    {
        $review = $this->query('review');

        if (! is_string($review) || preg_match('/^\d+$/', $review) !== 1) {
            return null;
        }

        $id = (int) $review;

        return $id > 0 ? $id : null;
    }

    /**
     * Whether the list should show only items a review can use. The caller
     * still needs a resolved review before this narrows anything, so `?eligible=1`
     * on its own never hides the whole page.
     */
    public function eligibleOnly(): bool
    {
        return in_array($this->query('eligible'), ['1', 'true', 'on'], true);
    }

    public function perPage(): int
    {
        $perPage = (int) $this->validated('per_page', TableCollection::PAGE_SIZE);

        return $perPage >= 1 && $perPage <= 100
            ? $perPage
            : TableCollection::PAGE_SIZE;
    }

    /**
     * Everything that searches, narrows or orders the collection on screen.
     * An unknown value falls back to the tab's quiet default, so a stale
     * bookmark still opens a useful list.
     */
    public function filters(): CollectionFilters
    {
        return CollectionFilters::fromArray([
            'q' => $this->query('q'),
            'starred' => $this->query('starred'),
            'status' => $this->query('status'),
            'attention' => $this->query('attention'),
            'topics' => $this->query('topics'),
            'sort' => $this->query('sort'),
            'source' => $this->query('source'),
            'notebook' => $this->query('notebook'),
        ], $this->tab()->feedbackType());
    }
}
