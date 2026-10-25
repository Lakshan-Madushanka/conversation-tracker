<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Actions\PracticeQuestions\SavePracticeQuestions;
use App\Actions\Topics\FindOrCreateTopic;
use App\Models\PracticeSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Starts a new practice session from the submitted questions.
 *
 * A save from the editor carries a `client_key`. If the same key arrives again,
 * the session it already created is returned instead of starting a second one,
 * so a retried first save is safe.
 */
final readonly class CreatePracticeSession
{
    public function __construct(
        private FindOrCreateTopic $findOrCreateTopic,
        private SavePracticeQuestions $savePracticeQuestions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): PracticeSession
    {
        $clientKey = $this->clientKey($data);

        if ($clientKey !== null) {
            $existing = PracticeSession::query()->where('client_key', $clientKey)->first();

            if ($existing instanceof PracticeSession) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($data, $clientKey): PracticeSession {
            try {
                $session = PracticeSession::create([
                    'topic_id' => $this->findOrCreateTopic->handle((string) $data['topic'])->id,
                    'client_key' => $clientKey,
                ]);
                $session->forceFill(['last_changed_at' => CarbonImmutable::now()])->saveQuietly();
            } catch (UniqueConstraintViolationException) {
                // Another request created it a moment ago; reuse that session.
                return PracticeSession::query()->where('client_key', $clientKey)->firstOrFail();
            }

            /** @var array<int, array<string, mixed>> $questions */
            $questions = is_array($data['questions'] ?? null) ? array_values($data['questions']) : [];

            $this->savePracticeQuestions->handle($session, $questions);

            return $session;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clientKey(array $data): ?string
    {
        $clientKey = $data['client_key'] ?? null;

        return is_string($clientKey) && $clientKey !== '' ? $clientKey : null;
    }
}
