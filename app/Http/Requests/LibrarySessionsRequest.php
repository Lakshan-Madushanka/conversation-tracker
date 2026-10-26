<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PracticeSession;
use App\Support\LibraryFilters;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The cursor for the next page of the Library, plus the filters and sort kept
 * in the page address. A filter the server does not understand is ignored
 * rather than rejected, so a stale bookmark still opens the Library.
 */
final class LibrarySessionsRequest extends FormRequest
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
        $perPage = (int) $this->validated('per_page', PracticeSession::LIBRARY_PAGE_SIZE);

        return $perPage >= 1 && $perPage <= 100
            ? $perPage
            : PracticeSession::LIBRARY_PAGE_SIZE;
    }

    /**
     * Everything the learner has chosen, normalised in one place. The browser
     * may send the time zone as `tz`; when it does not, a previously stored
     * cookie or the app's own zone is used.
     */
    public function filters(): LibraryFilters
    {
        $timezone = $this->query('tz');

        if (! is_string($timezone) || $timezone === '') {
            $cookie = $this->cookie('app_timezone');
            $timezone = is_string($cookie) ? $cookie : null;
        }

        return LibraryFilters::fromArray([
            'topics' => $this->query('topics'),
            'types' => $this->query('types'),
            'date' => $this->query('date'),
            'from' => $this->query('from'),
            'to' => $this->query('to'),
            'status' => $this->query('status'),
            'sort' => $this->query('sort'),
            'q' => $this->query('q'),
            'tz' => $timezone,
        ]);
    }
}
