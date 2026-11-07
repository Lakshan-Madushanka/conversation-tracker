<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Queries\CustomReviewsListing;
use App\Support\CustomReviewFilters;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The cursor for the next page of Custom reviews, plus the search, statuses
 * and order kept in the page address.
 *
 * A value the server does not understand is ignored rather than rejected, so
 * a stale bookmark still opens the listing.
 */
final class CustomReviewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function cursor(): ?string
    {
        $cursor = $this->validated('cursor');

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    public function perPage(): int
    {
        $perPage = (int) $this->validated('per_page', CustomReviewsListing::PAGE_SIZE);

        return $perPage >= 1 && $perPage <= 100
            ? $perPage
            : CustomReviewsListing::PAGE_SIZE;
    }

    /** Everything the learner has chosen, normalised in one place. */
    public function filters(): CustomReviewFilters
    {
        return CustomReviewFilters::fromArray([
            'q' => $this->query('q'),
            'status' => $this->query('status'),
            'sort' => $this->query('sort'),
        ]);
    }
}
