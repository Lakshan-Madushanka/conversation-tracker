<?php

declare(strict_types=1);

namespace App\Actions\Notebooks;

use App\Actions\Topics\FindOrCreateTopic;
use App\Models\Notebook;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a notebook under a shared topic, defaulting to General. The client
 * key makes a repeated creation return the same notebook, so a retried first
 * save never starts a second one.
 */
final readonly class CreateNotebook
{
    public function __construct(
        private FindOrCreateTopic $findOrCreateTopic,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Notebook
    {
        $clientKey = $this->clientKey($data);

        if ($clientKey !== null) {
            $existing = Notebook::query()->where('client_key', $clientKey)->first();

            if ($existing instanceof Notebook) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($data, $clientKey): Notebook {
            $description = RichText::cleanWithText((string) ($data['description'] ?? ''));
            $topicName = trim((string) ($data['topic'] ?? ''));

            try {
                $notebook = Notebook::create([
                    'name' => (string) $data['name'],
                    'topic_id' => $this->findOrCreateTopic->handle($topicName === '' ? 'General' : $topicName)->id,
                    'description_html' => $description['html'],
                    'description_text' => $description['text'],
                    'client_key' => $clientKey,
                ]);
                $notebook->forceFill(['last_changed_at' => CarbonImmutable::now()])->saveQuietly();
            } catch (UniqueConstraintViolationException) {
                // Another request created it a moment ago; reuse that notebook.
                return Notebook::query()->where('client_key', $clientKey)->firstOrFail();
            }

            return $notebook;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clientKey(array $data): ?string
    {
        $clientKey = $data['client_key'] ?? null;

        return is_string($clientKey) && $clientKey !== '' ? $clientKey : null;
    }
}
