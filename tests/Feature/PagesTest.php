<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('renders each static screen', function (string $routeName, string $component): void {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component($component));
})->with([
    'home' => ['home', 'Home'],
    'library' => ['sessions.index', 'sessions/Index'],
    'new session' => ['sessions.create', 'sessions/Create'],
    'collections' => ['collections.index', 'collections/Index'],
    'review home' => ['review.index', 'review/Index'],
    'review exercise' => ['review.session', 'review/Session'],
    'progress' => ['progress.index', 'progress/Index'],
    'preferences' => ['settings.index', 'settings/Preferences'],
    'import' => ['settings.import', 'settings/Import'],
    'export' => ['settings.export', 'settings/Export'],
]);

it('renders a session detail page', function (): void {
    $this->get(route('sessions.show', 1))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('sessions/Show'));
});
