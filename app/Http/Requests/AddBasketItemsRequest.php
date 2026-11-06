<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use App\Support\BasketIdentity;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A set of items the learner wants to add to the basket, named by kind and
 * identity.
 *
 * The identities are reduced to the same form the Collections use before they
 * are validated, so `Café` and `cafe` are one word and the same item cannot be
 * stored twice under different spellings. Nothing is written here.
 */
class AddBasketItemsRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $kind = $this->kindOrNull();

        $identityRules = ['required', 'string', 'max:'.BasketIdentity::MAX_LENGTH];

        if ($kind instanceof FeedbackType) {
            $identityRules[] = function (string $attribute, mixed $value, Closure $fail) use ($kind): void {
                if (! is_string($value) || ! BasketIdentity::isValid($kind, $value)) {
                    $fail('One of those items cannot be added to the basket.');
                }
            };
        }

        return [
            'kind' => ['required', 'string', Rule::in(FeedbackType::basketValues())],
            'identities' => ['required', 'array', 'min:1'],
            'identities.*' => $identityRules,
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

            $clean = BasketIdentity::normalize($kind, $identity);

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

    private function kindOrNull(): ?FeedbackType
    {
        $kind = $this->input('kind');

        return is_string($kind) ? FeedbackType::tryFrom($kind) : null;
    }
}
