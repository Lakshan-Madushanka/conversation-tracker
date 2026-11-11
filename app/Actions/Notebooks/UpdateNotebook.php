<?php

declare(strict_types=1);

namespace App\Actions\Notebooks;

use App\Actions\Topics\FindOrCreateTopic;
use App\Exceptions\NotebookConflictException;
use App\Models\Notebook;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Saves one automatic update of the whole board. A stale version is refused
 * with the latest notebook, and a retried save key changes nothing, so two
 * tabs never silently overwrite each other.
 */
final readonly class UpdateNotebook
{
    public function __construct(
        private FindOrCreateTopic $findOrCreateTopic,
        private SaveNotebookFeedback $saveNotebookFeedback,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Notebook $notebook, array $data): Notebook
    {
        return DB::transaction(function () use ($notebook, $data): Notebook {
            /** @var Notebook $locked */
            $locked = Notebook::query()
                ->whereKey($notebook->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->applyMetadataAndVersion($locked, $data)) {
                // The exact same save was already applied; a retry changes nothing.
                return $locked->refresh();
            }

            $feedback = is_array($data['feedback'] ?? null) ? $data['feedback'] : [];

            $this->saveNotebookFeedback->handle($locked, $feedback);

            return $locked->refresh();
        });
    }

    /**
     * Applies the metadata and bumps the version, unless this exact save
     * already ran. Returns false when nothing needed to change.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyMetadataAndVersion(Notebook $notebook, array $data): bool
    {
        $expectedVersion = isset($data['version']) ? (int) $data['version'] : null;
        $saveKey = $data['save_key'] ?? null;
        $saveKey = is_string($saveKey) && $saveKey !== '' ? $saveKey : null;

        if ($saveKey !== null && $notebook->last_save_key === $saveKey) {
            return false;
        }

        if ($expectedVersion !== null && $notebook->version !== $expectedVersion) {
            throw new NotebookConflictException($notebook);
        }

        $description = RichText::cleanWithText((string) ($data['description'] ?? ''));

        $notebook->name = (string) $data['name'];
        $notebook->topic_id = $this->findOrCreateTopic->handle((string) $data['topic'])->id;
        $notebook->description_html = $description['html'];
        $notebook->description_text = $description['text'];
        $notebook->version += 1;
        $notebook->last_save_key = $saveKey;
        $notebook->last_changed_at = CarbonImmutable::now();
        $notebook->save();

        return true;
    }
}
