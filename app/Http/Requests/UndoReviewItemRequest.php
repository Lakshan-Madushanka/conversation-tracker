<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The item whose rating the learner wants to take back.
 *
 * Asking for the exact item keeps undo safe: an old page cannot remove a rating
 * from an item the learner has since moved past.
 */
final class UndoReviewItemRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in([
                FeedbackType::Vocabulary->value,
                FeedbackType::Phrase->value,
            ])],
            'item_identity' => ['required', 'string', 'max:191'],
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
}
