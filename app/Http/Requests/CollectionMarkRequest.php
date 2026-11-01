<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The learner's star and status for one collection item, sent as the state
 * they want — never a toggle — so the same request can be repeated, retried
 * or sent from another tab without ever flipping the outcome.
 */
final class CollectionMarkRequest extends FormRequest
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
            // The item's identity as the collection names it: the normalised
            // word or phrase, the pair key of a mistake, or the note row's
            // own key.
            'item' => ['required', 'string', 'max:1200'],
            'starred' => ['required', 'boolean'],
            'status' => ['required', 'string', Rule::in(CollectionItemStatus::requestValues())],
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

    public function starred(): bool
    {
        return $this->boolean('starred');
    }

    /** The status the learner asked for, or null to clear it. */
    public function status(): ?CollectionItemStatus
    {
        $value = (string) $this->string('status');

        return CollectionItemStatus::fromRequestValue($value === '' ? null : $value);
    }
}
