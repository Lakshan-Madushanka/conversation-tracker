<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PracticeSessions\CreatePracticeSession;
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

    private function savedResponse(PracticeSession $session, int $status = 200): JsonResponse
    {
        return response()->json([
            'session' => $session->toEditorPayload(),
            'saved_at' => now()->toIso8601String(),
        ], $status);
    }
}
