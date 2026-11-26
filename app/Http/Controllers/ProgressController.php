<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ProgressNumbersResource;
use App\Queries\LearnerProgress;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    public function __invoke(LearnerProgress $progress): Response
    {
        return Inertia::render('progress/Index', [
            'progress' => ProgressNumbersResource::make($progress->numbers())->resolve(),
        ]);
    }
}
