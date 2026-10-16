<?php

use App\Http\Controllers\PracticeSessionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Home')->name('home');

Route::inertia('/sessions', 'sessions/Index')->name('sessions.index');

Route::get('/sessions/create', [PracticeSessionController::class, 'create'])->name('sessions.create');
Route::post('/sessions', [PracticeSessionController::class, 'store'])->name('sessions.store');

Route::get('/sessions/{session}/edit', [PracticeSessionController::class, 'edit'])->name('sessions.edit');
Route::put('/sessions/{session}', [PracticeSessionController::class, 'update'])->name('sessions.update');

Route::inertia('/sessions/{session}', 'sessions/Show')->name('sessions.show');

Route::inertia('/collections', 'collections/Index')->name('collections.index');

Route::inertia('/review', 'review/Index')->name('review.index');
Route::inertia('/review/session', 'review/Session')->name('review.session');

Route::inertia('/progress', 'progress/Index')->name('progress.index');

Route::inertia('/settings', 'settings/Preferences')->name('settings.index');
Route::inertia('/settings/import', 'settings/Import')->name('settings.import');
Route::inertia('/settings/export', 'settings/Export')->name('settings.export');
