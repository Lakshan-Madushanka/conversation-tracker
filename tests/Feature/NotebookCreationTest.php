<?php

declare(strict_types=1);

use App\Models\Notebook;
use App\Models\Topic;
use Illuminate\Support\Str;

describe('creating a notebook', function (): void {
    it('creates an empty notebook under General and opens its board', function (): void {
        $response = $this->post(route('notebooks.store'), ['name' => 'Nature reading']);

        $notebook = Notebook::query()->firstOrFail();

        $response->assertRedirect(route('notebooks.show', $notebook));

        expect($notebook->name)->toBe('Nature reading')
            ->and($notebook->topic->name)->toBe('General')
            ->and($notebook->entries)->toHaveCount(0);
    });

    it('uses the topic the learner chose, tidying it the same way sessions do', function (): void {
        $this->post(route('notebooks.store'), [
            'name' => 'Book notes',
            'topic' => '  Reading   Club ',
        ])->assertRedirect();

        expect(Notebook::query()->firstOrFail()->topic->name)->toBe('Reading Club');
    });

    it('refuses a blank or over-long name', function (string $name): void {
        $this->postJson(route('notebooks.store'), ['name' => $name])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name']]);

        expect(Notebook::count())->toBe(0);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'over the limit' => str_repeat('a', 121),
    ]);

    it('refuses an over-long topic or description', function (array $payload): void {
        $this->postJson(route('notebooks.store'), [...$payload, 'name' => 'Book notes'])
            ->assertStatus(422);

        expect(Notebook::count())->toBe(0);
    })->with([
        'topic' => [['topic' => str_repeat('a', 121)]],
        'description' => [['description' => str_repeat('a', 100_001)]],
    ]);

    it('cleans the rich description before storing it', function (): void {
        $this->post(route('notebooks.store'), [
            'name' => 'Book notes',
            'description' => '<script>alert(1)</script><p>Book <strong>One</strong></p>',
        ])->assertRedirect();

        $notebook = Notebook::query()->firstOrFail();

        expect($notebook->description_html)->toBe('<p>Book <strong>One</strong></p>')
            ->and($notebook->description_text)->toBe('Book One');
    });

    it('creates nothing while the learner is only looking at the listing', function (): void {
        $this->get(route('notebooks.index'))->assertOk();

        expect(Notebook::count())->toBe(0)
            ->and(Topic::query()->where('normalized_name', 'general')->count())->toBe(0);
    });

    it('returns the same notebook when creation is retried', function (): void {
        $clientKey = (string) Str::uuid();

        $this->post(route('notebooks.store'), ['name' => 'Book notes', 'client_key' => $clientKey])
            ->assertRedirect();

        $notebook = Notebook::query()->firstOrFail();

        $this->post(route('notebooks.store'), ['name' => 'Book notes', 'client_key' => $clientKey])
            ->assertRedirect(route('notebooks.show', $notebook));

        expect(Notebook::count())->toBe(1);
    });

    it('finds or creates General again after it was renamed', function (): void {
        $this->post(route('notebooks.store'), ['name' => 'First notebook'])->assertRedirect();

        $original = Notebook::query()->firstOrFail()->topic;

        $this->putJson(route('topics.update', $original), ['name' => 'Misc'])->assertOk();

        $this->post(route('notebooks.store'), ['name' => 'Second notebook'])->assertRedirect();

        $second = Notebook::query()->orderByDesc('id')->firstOrFail();

        expect($second->topic->name)->toBe('General')
            ->and($original->refresh()->name)->toBe('Misc')
            ->and(Topic::query()->where('normalized_name', 'general')->count())->toBe(1);
    });
});
