<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use App\Support\CustomReviewRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new review as the creation dialog sends it: the name it offered, perhaps
 * corrected, and the complete kinds choice.
 *
 * The dialog always sends both. A request that leaves either out creates the
 * review the app starts on its own — the next free name and every kind — the
 * way adding items starts a review. A value that is sent must hold: a blank
 * name is refused, and so is a choice of no kinds at all.
 */
final class StoreCustomReviewRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:'.CustomReviewRules::NAME_MAX_LENGTH,
            ],
            'kinds' => ['sometimes', 'required', 'array', 'min:1'],
            'kinds.*' => ['required', 'string', 'distinct', Rule::in(FeedbackType::customReviewValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give this review a name.',
            'name.max' => 'Keep the name to '.CustomReviewRules::NAME_MAX_LENGTH.' characters or fewer.',
            'kinds.required' => 'Choose at least one kind.',
            'kinds.min' => 'Choose at least one kind.',
            'kinds.*.in' => 'Choose from the kinds a review can hold.',
        ];
    }

    /**
     * Trim the name before the rules run, so surrounding spaces never count as
     * a name of their own.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /** The name the learner kept, or null to let the app name the review. */
    public function name(): ?string
    {
        if (! $this->has('name')) {
            return null;
        }

        /** @var string $name */
        $name = $this->validated('name');

        return $name;
    }

    /**
     * The kinds the dialog left chosen, or null to allow every kind.
     *
     * @return list<FeedbackType>|null
     */
    public function kinds(): ?array
    {
        if (! $this->has('kinds')) {
            return null;
        }

        /** @var list<string> $values */
        $values = $this->validated('kinds');

        return array_map(
            FeedbackType::from(...),
            $values,
        );
    }
}
