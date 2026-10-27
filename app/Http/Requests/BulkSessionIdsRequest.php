<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Queries\LibrarySessions;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The ids one bulk action acts on. The list is bounded by the same selection
 * limit the Library uses, so a hand-made request can never ask for more than
 * the page could select.
 */
final class BulkSessionIdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.LibrarySessions::SELECTION_LIMIT],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(
            intval(...),
            (array) $this->validated('ids'),
        ));
    }
}
