<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The learner's own Draft-or-Finished choice. Empty is never sent: the app
 * decides that from the item count, so a control can only move between the
 * two statuses the learner owns.
 */
final class UpdateCustomReviewStatusRequest extends FormRequest
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
            'status' => [
                'required',
                'string',
                Rule::in([
                    CustomReviewStatus::Draft->value,
                    CustomReviewStatus::Finished->value,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Choose Draft or Finished.',
        ];
    }

    public function status(): CustomReviewStatus
    {
        /** @var CustomReviewStatus */
        return CustomReviewStatus::from((string) $this->string('status'));
    }
}
