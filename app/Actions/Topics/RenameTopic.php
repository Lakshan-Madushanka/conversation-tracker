<?php

declare(strict_types=1);

namespace App\Actions\Topics;

use App\Models\Topic;

/**
 * Renames a shared topic. Sessions and notebooks reference the topic itself,
 * so every reference shows the new name straight away. Study identities,
 * marks and schedules never read the name, so none of them move.
 */
final class RenameTopic
{
    public function handle(Topic $topic, string $name): Topic
    {
        $topic->name = $name;
        $topic->save();

        return $topic;
    }
}
