<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePracticeSessionRequest extends FormRequest
{
    /** Generous room for a long pasted answer. */
    public const MAX_CONTENT_LENGTH = 100_000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:120'],
            'question_html' => ['nullable', 'string', 'max:'.self::MAX_CONTENT_LENGTH],
            'answer_html' => ['nullable', 'string', 'max:'.self::MAX_CONTENT_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'topic.required' => 'A session needs a topic so you can find it later.',
            'topic.max' => 'Please keep the topic short — up to 120 characters.',
            'question_html.max' => 'This question is too long to save at once. Try splitting it into shorter parts.',
            'answer_html.max' => 'This answer is too long to save at once. Try splitting it into shorter parts.',
        ];
    }
}
