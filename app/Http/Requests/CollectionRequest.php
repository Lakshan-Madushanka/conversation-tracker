<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CollectionTab;
use App\Queries\TableCollection;
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

    public function perPage(): int
    {
        $perPage = (int) $this->validated('per_page', TableCollection::PAGE_SIZE);

        return $perPage >= 1 && $perPage <= 100
            ? $perPage
            : TableCollection::PAGE_SIZE;
    }
}
