<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SessionCardResource;
use App\Http\Resources\SessionContinueCardResource;
use App\Models\PracticeSession;
use App\Queries\HomeSessions;
use App\Queries\TodaysReview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        HomeSessions $sessions,
        TodaysReview $todaysReview,
    ): Response {
        $continue = $sessions->continueCard();

        return Inertia::render('Home', [
            'unfinishedSession' => $continue instanceof PracticeSession
                ? SessionContinueCardResource::make($continue)->resolve()
                : null,
            'recentSessions' => $sessions->recent(5)
                ->map(fn (PracticeSession $session): array => SessionCardResource::make($session)->resolve())
                ->all(),
            'todayReview' => $todaysReview->card($request),
        ]);
    }
}
