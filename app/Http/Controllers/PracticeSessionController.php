<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PracticeSessions\ArchivePracticeSession;
use App\Actions\PracticeSessions\CreatePracticeSession;
use App\Actions\PracticeSessions\DiscardPracticeSession;
use App\Actions\PracticeSessions\FinishPracticeSession;
use App\Actions\PracticeSessions\RestorePracticeSession;
use App\Actions\PracticeSessions\SplitLegacyFeedbackEntries;
use App\Actions\PracticeSessions\UpdatePracticeSession;
use App\Exceptions\PracticeSessionArchivedException;
use App\Exceptions\PracticeSessionConflictException;
use App\Exceptions\PracticeSessionNotFinishedException;
use App\Http\Requests\AutosavePracticeSessionRequest;
use App\Http\Requests\StorePracticeSessionRequest;
use App\Http\Resources\SessionEditorResource;
use App\Models\PracticeSession;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PracticeSessionController extends Controller
{
    /**
     * A saved session to read from start to finish. An unfinished session is
     * sent straight to the editor, because the learner is still working on it.
     */
    public function show(PracticeSession $session): Response|RedirectResponse
    {
        if (! $session->isFinished()) {
            return redirect()->route('sessions.edit', $session);
        }

        return Inertia::render('sessions/Show', [
            'session' => $this->payload($session),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sessions/Create', [
            'topics' => Topic::pickerOptions(),
            'session' => null,
        ]);
    }

    public function store(
        StorePracticeSessionRequest $request,
        CreatePracticeSession $createPracticeSession,
    ): RedirectResponse {
        $session = $createPracticeSession->handle($request->validated());

        return redirect()->route('sessions.edit', $session);
    }

    public function edit(PracticeSession $session): Response|RedirectResponse
    {
        // An archived session is read-only. The reading page explains that and
        // offers Restore, so a stale editor address lands there calmly.
        if ($session->isArchived()) {
            return redirect()->route('sessions.show', $session);
        }

        return Inertia::render('sessions/Create', [
            'topics' => Topic::pickerOptions(),
            'session' => $this->payload($session),
        ]);
    }

    public function update(
        PracticeSession $session,
        StorePracticeSessionRequest $request,
        UpdatePracticeSession $updatePracticeSession,
    ): RedirectResponse {
        try {
            $updatePracticeSession->handle($session, $request->validated());
        } catch (PracticeSessionArchivedException) {
            return redirect()
                ->route('sessions.show', $session)
                ->with('error', 'This session is archived. Restore it to keep editing.');
        }

        return redirect()->route('sessions.edit', $session);
    }

    /**
     * Starts a session from the first automatic save, so unfinished work is
     * never lost. Returns the saved session for the editor to continue from.
     */
    public function autosaveStore(
        StorePracticeSessionRequest $request,
        CreatePracticeSession $createPracticeSession,
    ): JsonResponse {
        $session = $createPracticeSession->handle($request->validated());

        return $this->savedResponse($session, 201);
    }

    /**
     * Saves an automatic update. A stale version returns 409 with the latest
     * saved session so the client can offer to reload it.
     */
    public function autosaveUpdate(
        PracticeSession $session,
        AutosavePracticeSessionRequest $request,
        UpdatePracticeSession $updatePracticeSession,
    ): JsonResponse {
        try {
            $session = $updatePracticeSession->handle($session, $request->validated());
        } catch (PracticeSessionArchivedException) {
            return response()->json([
                'archived' => true,
                'message' => 'This session was archived. Restore it to keep editing.',
            ], 423);
        } catch (PracticeSessionConflictException $exception) {
            return response()->json([
                'conflict' => true,
                'message' => 'This session was changed somewhere else. Load the latest version or keep your changes.',
                'session' => $this->payload($exception->session),
            ], 409);
        }

        return $this->savedResponse($session);
    }

    /**
     * Puts a finished session away. It is a gentle, reversible change, so the
     * learner gets the session back and a message with Undo. An unfinished
     * session is refused with the reason and nothing is changed.
     */
    public function archive(
        PracticeSession $session,
        ArchivePracticeSession $archivePracticeSession,
    ): JsonResponse {
        try {
            $archived = $archivePracticeSession->handle($session);
        } catch (PracticeSessionNotFinishedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'archived' => true,
            'session' => $this->payload($archived),
        ]);
    }

    /**
     * Brings an archived session back exactly as it was. Safe to repeat.
     */
    public function restore(
        PracticeSession $session,
        RestorePracticeSession $restorePracticeSession,
    ): JsonResponse {
        $restored = $restorePracticeSession->handle($session);

        return response()->json([
            'archived' => false,
            'session' => $this->payload($restored),
        ]);
    }

    /**
     * Finishes a session: removes empty question cards, marks it finished, and
     * returns the counts of what was saved. An empty session is left untouched
     * and reported back so the learner can be warned instead of finishing it
     * silently. Safe to repeat.
     */
    public function finish(
        PracticeSession $session,
        FinishPracticeSession $finishPracticeSession,
    ): JsonResponse {
        $result = $finishPracticeSession->handle($session);

        if ($result->empty) {
            return response()->json(['empty' => true]);
        }

        $finished = $result->session;

        // Count first: the payload runs the one-time legacy split, so counting
        // before it keeps the numbers based purely on the stored entries.
        $counts = $finished->counts();
        $needsAttention = $finished->needsAttention();

        return response()->json([
            'empty' => false,
            'session' => $this->payload($finished),
            'counts' => $counts,
            'needs_attention' => $needsAttention,
            'empty_questions_removed' => $result->emptyQuestionsRemoved,
        ]);
    }

    /**
     * Discards a session that holds nothing. A session with any content is
     * refused, because removing filled-in work belongs to the Library later.
     */
    public function destroy(
        PracticeSession $session,
        DiscardPracticeSession $discardPracticeSession,
    ): JsonResponse {
        if (! $discardPracticeSession->allows($session)) {
            return response()->json([
                'message' => 'This session has content, so it cannot be discarded here.',
            ], 422);
        }

        $discardPracticeSession->handle($session);

        return response()->json(['discarded' => true]);
    }

    private function savedResponse(PracticeSession $session, int $status = 200): JsonResponse
    {
        return response()->json([
            'session' => $this->payload($session),
            'saved_at' => now()->toIso8601String(),
        ], $status);
    }

    /**
     * The session shaped for the editor. Content saved before entries existed
     * is split once, so old content looks the same as new content.
     *
     * @return array<string, mixed>
     */
    private function payload(PracticeSession $session): array
    {
        app(SplitLegacyFeedbackEntries::class)->handle($session);

        return SessionEditorResource::make($session)->resolve();
    }
}
