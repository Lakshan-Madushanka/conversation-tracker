<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new notebook: a required name, an optional topic that defaults to General
 * in the write action, and an optional rich description. The client key lets a
 * repeated creation return the same notebook instead of starting another.
 */
class StoreNotebookRequest extends FormRequest
{
    /** Room for a long formatted description, the same as session content. */
    public const int MAX_CONTENT_LENGTH = 100_000;

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
            'name' => ['required', 'string', 'max:120'],
            'topic' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:'.self::MAX_CONTENT_LENGTH],
            'client_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give this notebook a name.',
            'name.max' => 'Keep the name to 120 characters or fewer.',
            'topic.max' => 'Keep the topic to 120 characters or fewer.',
            'description.max' => 'This description is too long to save at once. Try shortening it.',
        ];
    }

    /**
     * Trim the name before the rules run, so surrounding spaces never count as
     * a name of their own.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
