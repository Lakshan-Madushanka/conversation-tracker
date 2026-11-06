<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Models\Rating;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The learner's answer to one status suggestion on the finish screen.
 *
 * The item travels as its kind and identity, the same names a rating uses, and
 * the run of ratings the suggestion was made from travels by the id of the
 * rating that began it. Confirming and declining carry the same fields, so a
 * refusal is tied to the exact run the learner said "Not now" during, and a
 * request that names no real run of that item is refused.
 */
final class StatusSuggestionRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(array_map(
                fn (FeedbackType $type): string => $type->value,
                FeedbackType::markable(),
            ))],
            'item_identity' => ['required', 'string', 'max:191'],
            'status' => ['required', 'string', Rule::in([
                CollectionItemStatus::Learned->value,
                CollectionItemStatus::StillDifficult->value,
            ])],
            'run_started_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $belongs = Rating::query()
                        ->whereKey((int) $value)
                        ->where('kind', (string) $this->input('kind'))
                        ->where('item_identity', (string) $this->input('item_identity'))
                        ->exists();

                    if (! $belongs) {
                        $fail('This is not a rating of that item.');
                    }
                },
            ],
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

    public function status(): CollectionItemStatus
    {
        /** @var CollectionItemStatus */
        return CollectionItemStatus::from((string) $this->string('status'));
    }

    /** The rating that began the run the suggestion was made from. */
    public function runStartedId(): int
    {
        return (int) $this->integer('run_started_id');
    }
}
