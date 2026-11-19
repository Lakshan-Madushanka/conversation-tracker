<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\SearchText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The notebook listing's address: an optional search, an optional topic, and
 * the cursor for the next page. The search is kept only when it can actually
 * find something; a topic no notebook uses is quietly ignored.
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
            'topic' => ['nullable', 'integer', 'min:1'],
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function search(): string
    {
        return SearchText::keptSearch($this->query('q'));
    }

    /**
     * The chosen topic, kept only when a notebook still uses it.
     *
     * @param  list<int>  $allowed
     */
    public function topicId(array $allowed): ?int
    {
        $topic = $this->validated('topic');

        if (! is_numeric($topic)) {
            return null;
        }

        return in_array((int) $topic, $allowed, true) ? (int) $topic : null;
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
