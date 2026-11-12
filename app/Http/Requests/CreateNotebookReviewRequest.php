<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use App\Support\CustomReviewIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One Create review and practise request: the key that makes it repeatable,
 * the kinds the learner chose, the items they selected when the notebook held
 * more than the maximum, and their answer to the size warning.
 *
 * The identities are exact stored keys, so they are never normalised as text:
 * a notebook key would otherwise be lower-cased into something nothing holds.
 * The server revalidates every one of them against the notebook's current
 * candidates before writing.
 */
final class CreateNotebookReviewRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'creation_key' => ['required', 'string', 'max:64'],
            'kinds' => ['nullable', 'array', 'min:1'],
            'kinds.*' => ['string', 'distinct', Rule::in(FeedbackType::customReviewValues())],
            'identities' => ['nullable', 'array'],
            'identities.*' => ['string', 'max:'.CustomReviewIdentity::MAX_LENGTH],
            'confirmed' => ['boolean'],
        ];
    }

    public function creationKey(): string
    {
        return (string) $this->validated('creation_key');
    }

    /**
     * @return list<FeedbackType>|null
     */
    public function kinds(): ?array
    {
        /** @var list<string>|null $values */
        $values = $this->validated('kinds');

        return $values === null
            ? null
            : array_map(FeedbackType::from(...), $values);
    }

    /**
     * @return list<string>|null
     */
    public function identities(): ?array
    {
        /** @var list<string>|null $identities */
        $identities = $this->validated('identities');

        return $identities;
    }

    public function confirmed(): bool
    {
        return $this->boolean('confirmed');
    }
}
