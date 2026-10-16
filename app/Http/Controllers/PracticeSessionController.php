<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PracticeSessions\CreatePracticeSession;
use App\Actions\PracticeSessions\UpdatePracticeSession;
use App\Http\Requests\StorePracticeSessionRequest;
use App\Models\PracticeSession;
use App\Models\Topic;
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
}
