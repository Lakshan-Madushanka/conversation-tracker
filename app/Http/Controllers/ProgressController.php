<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ProgressNumbersResource;
use App\Queries\LearnerProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    public function __invoke(Request $request, LearnerProgress $progress): Response
    {
        return Inertia::render('progress/Index', [
            'progress' => ProgressNumbersResource::make($progress->numbers($request))->resolve(),
        ]);
    }
}
