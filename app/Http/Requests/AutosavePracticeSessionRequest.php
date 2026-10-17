<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Validates an automatic save of an existing session. It extends the editor
 * payload with the `version` the client last read, used to detect when another
 * tab has saved newer work, and a `save_key` that lets a retried save be
 * recognised as the one the server already applied.
 */
class AutosavePracticeSessionRequest extends StorePracticeSessionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'version' => ['required', 'integer', 'min:1'],
            'save_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'version.required' => 'Reload the page before saving this session again.',
            'version.integer' => 'Reload the page before saving this session again.',
        ];
    }
}
