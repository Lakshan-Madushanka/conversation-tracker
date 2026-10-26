<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\LearnerTimezone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A permanent delete must carry the signed state the warning showed, plus the
 * time zone the numbers were worked out in.
 */
final class DeletePracticeSessionRequest extends FormRequest
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
            'token' => ['required', 'string', 'size:64'],
            'tz' => ['nullable', 'string', 'max:64'],
        ];
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
