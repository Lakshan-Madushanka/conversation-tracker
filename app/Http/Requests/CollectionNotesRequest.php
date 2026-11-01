<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Collections\SaveItemNotes;
use App\Enums\FeedbackType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The learner's personal note for one collection item, sent with the version
 * it was read at. The cleaned note's character limit is checked after saving
 * has made the text safe, in `SaveItemNotes`.
 */
final class CollectionNotesRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(array_map(
                fn (FeedbackType $type): string => $type->value,
                FeedbackType::collectable(),
            ))],
            'item' => ['required', 'string', 'max:1200'],
            // Generous room for a long pasted note; the plain-text limit the
            // learner sees is applied after the markup is cleaned.
            'html' => ['nullable', 'string', 'max:'.SaveItemNotes::MAX_LENGTH * 20],
            // The version the note was read at; 0 for no note yet.
            'version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function kind(): FeedbackType
    {
        /** @var FeedbackType */
        return FeedbackType::from((string) $this->string('kind'));
    }

    public function item(): string
    {
        return (string) $this->string('item');
    }

    public function html(): string
    {
        return (string) $this->string('html');
    }

    public function version(): int
    {
        return (int) $this->integer('version');
    }
}
