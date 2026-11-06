<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use App\Support\CustomReviewIdentity;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A set of items the learner wants gathered into a custom review, named by
 * kind and identity, with the review they chose when they have chosen one and
 * the answer to the recommended-size warning.
 *
 * The identities are reduced to the same form the Collections use before they
 * are validated, so `Café` and `cafe` are one word and the same item cannot be
 * stored twice under different spellings. Nothing is written here.
 */
class AddCustomReviewItemsRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $kind = $this->kindOrNull();

        $identityRules = ['required', 'string', 'max:'.CustomReviewIdentity::MAX_LENGTH];

        if ($kind instanceof FeedbackType) {
            $identityRules[] = function (string $attribute, mixed $value, Closure $fail) use ($kind): void {
                if (! is_string($value) || ! CustomReviewIdentity::isValid($kind, $value)) {
                    $fail('One of those items cannot be added to a review.');
                }
            };
        }

        return [
            'kind' => ['required', 'string', Rule::in(FeedbackType::customReviewValues())],
            'identities' => ['required', 'array', 'min:1'],
            'identities.*' => $identityRules,
            'review' => ['nullable', 'integer', Rule::exists('custom_reviews', 'id')],
            'confirmed' => ['boolean'],
        ];
    }

    /**
     * Normalise before validating, so a word is checked in the form it will be
     * stored in. An entry that is blank on its own is dropped; the list then
     * fails its own required rule, calmly.
     */
    protected function prepareForValidation(): void
    {
        $kind = $this->kindOrNull();
        $identities = $this->input('identities');

        if (! $kind instanceof FeedbackType || ! is_array($identities)) {
            return;
        }

        $normalised = [];

        foreach ($identities as $identity) {
            if (! is_string($identity)) {
                $normalised[] = $identity;

                continue;
            }

            $clean = CustomReviewIdentity::normalize($kind, $identity);

            if ($clean !== '') {
                $normalised[] = $clean;
            }
        }

        $this->merge(['identities' => array_values(array_unique($normalised, SORT_REGULAR))]);
    }

    public function kind(): FeedbackType
    {
        /** @var FeedbackType */
        return FeedbackType::from((string) $this->string('kind'));
    }

    /**
     * @return list<string>
     */
    public function identities(): array
    {
        /** @var list<string> */
        return array_values($this->validated('identities'));
    }

    /**
     * The review the learner chose or is standing in, or null when they have
     * not chosen one and the app should work out whether there is a choice.
     * The review itself is read where the add is decided, not here.
     */
    public function reviewId(): ?int
    {
        $id = $this->integer('review');

        return $id > 0
            ? $id
            : null;
    }

    /** Whether the learner has already answered the recommended-size warning. */
    public function confirmed(): bool
    {
        return $this->boolean('confirmed');
    }

    private function kindOrNull(): ?FeedbackType
    {
        $kind = $this->input('kind');

        return is_string($kind) ? FeedbackType::tryFrom($kind) : null;
    }
}
