<?php

declare(strict_types=1);

namespace App\Actions\PracticeSessions;

use App\Actions\PracticeQuestions\SavePracticeQuestions;
use App\Actions\Topics\FindOrCreateTopic;
use App\Exceptions\PracticeSessionConflictException;
use App\Models\PracticeSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Saves edits to the topic and questions of an existing session.
 *
 * The editor passes the `version` it last read. The session row is locked while
 * saving, and the save is applied only when the stored version still matches.
 * A newer version means another tab has saved in the meantime, so the save is
 * refused with a conflict rather than silently overwriting that work.
 *
 * The editor also passes a `save_key`. A retried save that carries the key the
 * server already applied changes nothing, so repeated saves stay safe.
 */
final readonly class UpdatePracticeSession
{
    public function __construct(
        private FindOrCreateTopic $findOrCreateTopic,
        private SavePracticeQuestions $savePracticeQuestions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PracticeSession $session, array $data): PracticeSession
    {
        return DB::transaction(function () use ($session, $data): PracticeSession {
            /** @var PracticeSession $locked */
            $locked = PracticeSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->applyTopicAndVersion($locked, $data)) {
                // The exact same save was already applied; a retry changes nothing.
                return $locked->refresh();
            }

            /** @var array<int, array<string, mixed>> $questions */
            $questions = is_array($data['questions'] ?? null) ? array_values($data['questions']) : [];

            $this->savePracticeQuestions->handle($locked, $questions);

            return $locked->refresh();
        });
    }

    /**
     * Applies the topic and bumps the version, unless this exact save already
     * ran. Returns false when nothing needed to change.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyTopicAndVersion(PracticeSession $session, array $data): bool
    {
        $expectedVersion = isset($data['version']) ? (int) $data['version'] : null;
        $saveKey = $data['save_key'] ?? null;
        $saveKey = is_string($saveKey) && $saveKey !== '' ? $saveKey : null;

        if ($saveKey !== null && $session->last_save_key === $saveKey) {
            return false;
        }

        if ($expectedVersion !== null && $session->version !== $expectedVersion) {
            throw new PracticeSessionConflictException($session);
        }

        $session->topic_id = $this->findOrCreateTopic->handle((string) $data['topic'])->id;
        $session->version += 1;
        $session->last_save_key = $saveKey;
        $session->last_changed_at = CarbonImmutable::now();
        $session->save();

        return true;
    }
}
