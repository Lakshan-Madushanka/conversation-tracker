<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PracticeFeedbackEntry;
use App\Rules\ValidFeedbackBox;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePracticeSessionRequest extends FormRequest
{
    /** Generous room for a long pasted answer. */
    public const MAX_CONTENT_LENGTH = 100_000;

    /** Maximum number of questions in one session. */
    public const MAX_QUESTIONS = 50;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Give every question the full editor shape before validating it. Clients
     * may leave boxes out; filling them here keeps the nested payload
     * predictable and stops Laravel from dropping a question whose array would
     * otherwise be empty. Values that are present but the wrong type are left
     * untouched so the rules below can report them.
     */
    protected function prepareForValidation(): void
    {
        $questions = $this->input('questions');

        if (! is_array($questions)) {
            return;
        }

        $this->merge([
            'questions' => array_map($this->normalizeQuestion(...), $questions),
        ]);
    }

    private function normalizeQuestion(mixed $question): mixed
    {
        if (! is_array($question)) {
            return $question;
        }

        $question['question_html'] ??= '';
        $question['answer_html'] ??= '';

        $feedback = $question['feedback'] ?? null;

        if (! is_array($feedback)) {
            if (array_key_exists('feedback', $question)) {
                return $question;
            }

            $feedback = [];
        }

        foreach (PracticeFeedbackEntry::TYPES as $type) {
            $feedback[$type] ??= '';
        }

        $question['feedback'] = $feedback;

        return $question;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'topic' => ['required', 'string', 'max:120'],
            // Identifies a session the editor created; a repeated first save
            // returns the same session instead of starting another one.
            'client_key' => ['nullable', 'string', 'max:64'],
            'questions' => ['required', 'array', 'min:1', 'max:'.self::MAX_QUESTIONS],
            'questions.*' => ['array'],
            'questions.*.question_html' => ['nullable', 'string', 'max:'.self::MAX_CONTENT_LENGTH],
            'questions.*.answer_html' => ['nullable', 'string', 'max:'.self::MAX_CONTENT_LENGTH],
            'questions.*.feedback' => ['nullable', 'array'],
            // The natural version stays one piece of formatted text.
            'questions.*.feedback.'.PracticeFeedbackEntry::TYPE_NATURAL_VERSION => ['nullable', 'string', 'max:'.self::MAX_CONTENT_LENGTH],
        ];

        // Every other box holds a list of entries, each with the identity it
        // was created with and the text the learner sees on screen.
        foreach (PracticeFeedbackEntry::ENTRY_TYPES as $type) {
            $rules['questions.*.feedback.'.$type] = [
                'nullable',
                new ValidFeedbackBox($type, self::MAX_CONTENT_LENGTH),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'topic.required' => 'A session needs a topic so you can find it later.',
            'topic.max' => 'Please keep the topic short — up to 120 characters.',
            'questions.required' => 'A session needs at least one question card.',
            'questions.min' => 'A session needs at least one question card.',
            'questions.max' => 'A session can hold up to 50 questions. Remove one to add another.',
            'questions.*.question_html.max' => 'This question is too long to save at once. Try splitting it into shorter parts.',
            'questions.*.answer_html.max' => 'This answer is too long to save at once. Try splitting it into shorter parts.',
            'questions.*.feedback.*.max' => 'This feedback is too long to save at once. Try splitting it into shorter parts.',
        ];
    }
}
