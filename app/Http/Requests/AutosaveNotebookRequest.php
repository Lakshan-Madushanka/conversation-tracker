<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FeedbackType;
use App\Rules\ValidFeedbackBox;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One automatic save of the whole board: metadata, the three boxes, and the
 * version/save key that catch another tab's change and make a retry safe.
 */
final class AutosaveNotebookRequest extends StoreNotebookRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'topic' => ['required', 'string', 'max:120'],
            'version' => ['required', 'integer', 'min:1'],
            'save_key' => ['nullable', 'string', 'max:64'],
            'feedback' => ['required', 'array'],
            'feedback.vocabulary' => ['nullable', new ValidFeedbackBox(FeedbackType::Vocabulary, self::MAX_CONTENT_LENGTH)],
            'feedback.phrase' => ['nullable', new ValidFeedbackBox(FeedbackType::Phrase, self::MAX_CONTENT_LENGTH)],
            'feedback.grammar_note' => ['nullable', new ValidFeedbackBox(FeedbackType::GrammarNote, self::MAX_CONTENT_LENGTH)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'version.required' => 'Reload the page before saving this notebook again.',
            'version.integer' => 'Reload the page before saving this notebook again.',
        ];
    }

    /**
     * Give the board the full editor shape before validating it, so a box the
     * client left out still reads as an empty box rather than a missing field.
     * Null cell values (from ConvertEmptyStringsToNull) become empty strings
     * so blank rows pass validation and are skipped by the save action.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $feedback = $this->input('feedback');

        if (! is_array($feedback)) {
            return;
        }

        foreach ([FeedbackType::Vocabulary, FeedbackType::Phrase, FeedbackType::GrammarNote] as $type) {
            $feedback[$type->value] ??= '';
        }

        foreach ($feedback as $key => $box) {
            if (! is_array($box)) {
                continue;
            }

            $feedback[$key] = array_map($this->normalizeItem(...), $box);
        }

        $this->merge(['feedback' => $feedback]);
    }

    private function normalizeItem(mixed $item): mixed
    {
        if (! is_array($item)) {
            return $item;
        }

        foreach ($item as $key => $value) {
            if ($value === null) {
                $item[$key] = '';
            }
        }

        return $item;
    }
}
