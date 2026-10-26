<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\LearnerTimezone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The delete warning only needs the learner's time zone, because practice days
 * and the streak are counted on their calendar.
 */
final class SessionDeletionPreviewRequest extends FormRequest
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
            'tz' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function timezone(): string
    {
        return LearnerTimezone::normalise($this->input('tz'));
    }
}
