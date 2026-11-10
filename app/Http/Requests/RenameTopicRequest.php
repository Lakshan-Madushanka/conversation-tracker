<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Topic;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A topic's new name, checked for duplicates in the same form topic creation
 * stores it, so "Holiday" and "holiday" cannot become two topics.
 */
final class RenameTopicRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return Closure(Validator): void
     */
    public function after(): Closure
    {
        return function (Validator $validator): void {
            if ($validator->errors()->has('name')) {
                return;
            }

            $normalized = mb_strtolower(Topic::normalizeName($this->string('name')->toString()));

            $taken = Topic::query()
                ->where('normalized_name', $normalized)
                ->whereKeyNot($this->route('topic'))
                ->exists();

            if ($taken) {
                $validator->errors()->add('name', 'A topic with this name already exists.');
            }
        };
    }
}
