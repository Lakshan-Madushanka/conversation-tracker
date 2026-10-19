<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PracticeSessions\CreatePracticeSession;
use App\Actions\PracticeSessions\DiscardPracticeSession;
use App\Actions\PracticeSessions\FinishPracticeSession;
use App\Actions\PracticeSessions\UpdatePracticeSession;
use App\Exceptions\PracticeSessionConflictException;
use App\Http\Requests\AutosavePracticeSessionRequest;
use App\Http\Requests\StorePracticeSessionRequest;
use App\Models\PracticeSession;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PracticeSessionController extends Controller
{
    /**
     * The Library: every session the learner has finished, newest change
     * first. Sessions still in progress stay on the Home page until they are
     * finished.
     */
    public function index(): Response
    {
        return Inertia::render('sessions/Index', [
            'sessions' => PracticeSession::finishedSessions(),
        ]);
    }

    /**
     * A saved session to read from start to finish. It is served in the same
     * shape the editor uses, so both pages show exactly what is stored.
     */
    public function show(PracticeSession $session): Response
    {
        return Inertia::render('sessions/Show', [
            'session' => $session->toEditorPayload(),
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

    public function edit(PracticeSession $session): Response
    {
        return Inertia::render('sessions/Create', [
            'topics' => Topic::pickerOptions(),
            'session' => $session->toEditorPayload(),
        ]);
    }

    public function update(
        PracticeSession $session,
        StorePracticeSessionRequest $request,
        UpdatePracticeSession $updatePracticeSession,
    ): RedirectResponse {
        $updatePracticeSession->handle($session, $request->validated());

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
        } catch (PracticeSessionConflictException $exception) {
            return response()->json([
                'conflict' => true,
                'message' => 'This session was changed somewhere else. Load the latest version or keep your changes.',
                'session' => $exception->session->toEditorPayload(),
            ], 409);
        }

        return $this->savedResponse($session);
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
            'session' => $finished->toEditorPayload(),
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
            'session' => $session->toEditorPayload(),
            'saved_at' => now()->toIso8601String(),
        ], $status);
    }
}
