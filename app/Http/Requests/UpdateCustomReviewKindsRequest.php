<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The learner's kinds choice: the whole subset a review should allow.
 *
 * The request carries the complete choice rather than a toggle, so repeating
 * it never flips a kind back on. At least one kind is always chosen — a review
 * that allows nothing could hold nothing and mean nothing.
 */
final class UpdateCustomReviewKindsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kinds' => ['required', 'array', 'min:1'],
            'kinds.*' => ['required', 'string', 'distinct', Rule::in(FeedbackType::customReviewValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kinds.required' => 'Choose at least one kind.',
            'kinds.min' => 'Choose at least one kind.',
            'kinds.*.in' => 'Choose from the kinds a review can hold.',
        ];
    }

    /**
     * @return list<FeedbackType>
     */
    public function kinds(): array
    {
        /** @var list<string> $values */
        $values = $this->validated('kinds');

        return array_map(
            FeedbackType::from(...),
            $values,
        );
    }
}
