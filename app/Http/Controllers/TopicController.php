<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Topics\DeleteTopic;
use App\Actions\Topics\RenameTopic;
use App\Http\Requests\RenameTopicRequest;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TopicController extends Controller
{
    public function update(RenameTopicRequest $request, Topic $topic, RenameTopic $renameTopic): JsonResponse
    {
        $topic = $renameTopic->handle($topic, $request->string('name')->toString());

        return response()->json([
            'id' => $topic->id,
            'name' => $topic->name,
        ]);
    }

    public function destroy(Topic $topic, DeleteTopic $deleteTopic): Response
    {
        $deleteTopic->handle($topic);

        return response()->noContent();
    }
}
