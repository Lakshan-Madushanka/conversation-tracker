<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The kinds the candidate list should offer. The learner's choice is never
 * inferred from the boxes that happen to have content; leaving it out means
 * every kind the notebook can hold.
 */
final class NotebookReviewCandidatesRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kinds' => ['nullable', 'array', 'min:1'],
            'kinds.*' => ['string', 'distinct', Rule::in(FeedbackType::customReviewValues())],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** Where the next page of candidates starts; the first page starts at zero. */
    public function offset(): int
    {
        $offset = $this->validated('offset');

        return is_numeric($offset) ? max(0, (int) $offset) : 0;
    }

    /**
     * @return list<FeedbackType>|null
     */
    public function kinds(): ?array
    {
        $values = $this->query('kinds');

        if (! is_array($values)) {
            return null;
        }

        /** @var list<string> $values */
        return array_map(FeedbackType::from(...), $values);
    }
}
