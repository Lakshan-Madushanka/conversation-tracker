<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The rating the learner gave one reviewed item.
 *
 * The item travels as its kind and identity, the same names Collections uses,
 * so a request can be repeated or retried without ever landing on a different
 * item than the one on screen.
 */
final class RateReviewItemRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(FeedbackType::reviewableValues())],
            'item_identity' => ['required', 'string', 'max:191'],
            'rating' => ['required', 'string', Rule::in(ReviewRating::values())],
        ];
    }

    public function kind(): FeedbackType
    {
        /** @var FeedbackType */
        return FeedbackType::from((string) $this->string('kind'));
    }

    public function itemIdentity(): string
    {
        return (string) $this->string('item_identity');
    }

    public function rating(): ReviewRating
    {
        /** @var ReviewRating */
        return ReviewRating::from((string) $this->string('rating'));
    }
}
