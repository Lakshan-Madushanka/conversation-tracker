<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PracticeSession;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The cursor for the next page of the Library. The cursor is opaque: the
 * client sends back exactly what the server gave it.
 */
final class LibrarySessionsRequest extends FormRequest
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
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function cursor(): ?string
    {
        $cursor = $this->validated('cursor');

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    public function perPage(): int
    {
        $perPage = (int) $this->validated('per_page', PracticeSession::LIBRARY_PAGE_SIZE);

        return $perPage >= 1 && $perPage <= 100
            ? $perPage
            : PracticeSession::LIBRARY_PAGE_SIZE;
    }
}
