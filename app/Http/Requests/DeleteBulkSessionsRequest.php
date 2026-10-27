<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Queries\LibrarySessions;
use App\Support\LearnerTimezone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A permanent bulk delete must carry the signed state the warning showed, the
 * same ids, and the time zone the numbers were worked out in.
 */
final class DeleteBulkSessionsRequest extends FormRequest
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
            'token' => ['required', 'string', 'size:64'],
            'tz' => ['nullable', 'string', 'max:64'],
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

    public function token(): string
    {
        return (string) $this->validated('token');
    }

    public function timezone(): string
    {
        return LearnerTimezone::normalise($this->input('tz'));
    }
}
