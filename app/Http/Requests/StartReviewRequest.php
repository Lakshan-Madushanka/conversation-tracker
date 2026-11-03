<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Starting a review from a card. `daily` marks it as the day's suggested
 * review; `more` starts the "Review a bit more" sitting, which leaves out
 * the items already rated today and is never the daily review.
 */
final class StartReviewRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'daily' => ['sometimes', 'boolean'],
            'more' => ['sometimes', 'boolean'],
        ];
    }

    public function daily(): bool
    {
        return $this->boolean('daily');
    }

    public function more(): bool
    {
        return $this->boolean('more');
    }
}
