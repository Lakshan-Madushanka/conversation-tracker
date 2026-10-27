<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Queries\LibrarySessions;
use App\Support\LearnerTimezone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The sessions a bulk delete warning covers, plus the time zone practice days
 * and the streak are counted in.
 */
final class BulkSessionDeletionPreviewRequest extends FormRequest
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

    public function timezone(): string
    {
        return LearnerTimezone::normalise($this->input('tz'));
    }
}
