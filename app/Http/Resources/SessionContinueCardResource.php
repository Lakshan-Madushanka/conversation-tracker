<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PracticeSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The most recently touched unfinished session, shaped for the Home page's
 * "Continue where you left off" card.
 *
 * @mixin PracticeSession
 */
final class SessionContinueCardResource extends JsonResource
{
    /**
     * @return array{id: int, topic: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        /** @var PracticeSession $session */
        $session = $this->resource;

        $session->loadMissing('topic');

        return [
            'id' => $session->id,
            'topic' => $session->topic->name,
            'updated_at' => $session->updated_at?->toIso8601String() ?? '',
        ];
    }
}
