<?php

declare(strict_types=1);

namespace App\Actions\Topics;

use App\Models\Notebook;
use App\Models\PracticeSession;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a topic only while nothing references it. Every session counts —
 * unfinished and archived ones too — and so does every notebook, even an
 * empty one. A refused deletion explains that the topic is in use; it never
 * cascades into content or moves anything to General.
 */
final class DeleteTopic
{
    /**
     * @throws ValidationException while a session or notebook still uses the topic
     */
    public function handle(Topic $topic): void
    {
        DB::transaction(function () use ($topic): void {
            $referenced = PracticeSession::query()->where('topic_id', $topic->id)->exists()
                || Notebook::query()->where('topic_id', $topic->id)->exists();

            if ($referenced) {
                throw ValidationException::withMessages([
                    'topic' => 'This topic is still used by sessions or notebooks, so it cannot be deleted.',
                ]);
            }

            $topic->delete();
        });
    }
}
