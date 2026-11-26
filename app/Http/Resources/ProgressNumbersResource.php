<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ProgressNumbers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The totals and practice numbers the Progress screen and Home show.
 *
 * @mixin ProgressNumbers
 */
final class ProgressNumbersResource extends JsonResource
{
    public function __construct(ProgressNumbers $numbers)
    {
        parent::__construct($numbers);
    }

    /**
     * @return array{
     *     words: int,
     *     phrases: int,
     *     mistakes: int,
     *     grammar_notes: int,
     *     practice_days: int,
     *     current_streak: int,
     *     sessions: int
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var ProgressNumbers $numbers */
        $numbers = $this->resource;

        return $numbers->toArray();
    }
}
