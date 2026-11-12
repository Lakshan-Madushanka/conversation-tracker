<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Queries\NotebookReviewCandidates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One item Create review and practise can offer, with the words it will show
 * and whether it can be practised yet.
 *
 * @phpstan-import-type NotebookReviewCandidate from NotebookReviewCandidates
 */
final class NotebookReviewCandidateResource extends JsonResource
{
    /**
     * @return array{
     *     kind: string,
     *     identity: string,
     *     title: string,
     *     meaning: string,
     *     example: string,
     *     eligible: bool,
     *     reason: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var NotebookReviewCandidate $candidate */
        $candidate = $this->resource;

        return [
            'kind' => $candidate['kind'],
            'identity' => $candidate['identity'],
            'title' => $candidate['title'],
            'meaning' => $candidate['meaning'],
            'example' => $candidate['example'],
            'eligible' => $candidate['eligible'],
            'reason' => $candidate['reason'],
        ];
    }
}
