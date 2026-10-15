<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Home')->name('home');

Route::inertia('/sessions', 'sessions/Index')->name('sessions.index');
Route::inertia('/sessions/create', 'sessions/Create')->name('sessions.create');
Route::inertia('/sessions/{session}', 'sessions/Show')->name('sessions.show');

Route::inertia('/collections', 'collections/Index')->name('collections.index');

Route::inertia('/review', 'review/Index')->name('review.index');
Route::inertia('/review/session', 'review/Session')->name('review.session');

Route::inertia('/progress', 'progress/Index')->name('progress.index');

Route::inertia('/settings', 'settings/Preferences')->name('settings.index');
Route::inertia('/settings/import', 'settings/Import')->name('settings.import');
Route::inertia('/settings/export', 'settings/Export')->name('settings.export');
