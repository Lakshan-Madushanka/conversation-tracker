<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\CustomReviewRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A partial change to a review's name or description.
 *
 * Either field may be left out, because the page saves each one on its own:
 * a description can be saved while a name is being corrected, and the other
 * way around. A name that is present must hold something after trimming, so a
 * blank name is refused and the previous one stays.
 */
final class UpdateCustomReviewRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:'.CustomReviewRules::NAME_MAX_LENGTH,
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:'.CustomReviewRules::DESCRIPTION_MAX_LENGTH,
            ],
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
            'description.max' => 'Keep the description to '.CustomReviewRules::DESCRIPTION_MAX_LENGTH.' characters or fewer.',
        ];
    }

    /**
     * Trim the name before the rules run, so surrounding spaces never count as
     * a name of their own. A description of only spaces becomes no description.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }

        if ($this->has('description') && is_string($this->input('description'))) {
            $description = trim($this->input('description'));

            $this->merge([
                'description' => $description === '' ? null : $description,
            ]);
        }
    }

    /**
     * Only the fields the learner actually sent, so a partial save never
     * clears the other one.
     *
     * @return array{name?: string, description?: string|null}
     */
    public function changes(): array
    {
        $changes = [];

        if ($this->has('name')) {
            /** @var string $name */
            $name = $this->validated('name');
            $changes['name'] = $name;
        }

        if ($this->has('description')) {
            /** @var string|null $description */
            $description = $this->validated('description');
            $changes['description'] = $description;
        }

        return $changes;
    }
}
