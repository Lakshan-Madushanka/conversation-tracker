<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\SearchText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The notebook listing's address: an optional search and the cursor for the
 * next page. The search is kept only when it can actually find something.
 */
final class NotebooksListingRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:'.SearchText::MAX_LENGTH],
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function search(): string
    {
        return SearchText::keptSearch($this->query('q'));
    }

    public function cursor(): ?string
    {
        $cursor = $this->validated('cursor');

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    public function perPage(): int
    {
        $perPage = (int) $this->validated('per_page', 20);

        return $perPage >= 1 && $perPage <= 100 ? $perPage : 20;
    }
}
