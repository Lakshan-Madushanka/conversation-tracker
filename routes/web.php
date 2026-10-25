<?php

use App\Http\Controllers\CollectionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\PracticeSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/sessions', LibraryController::class)->name('sessions.index');

Route::get('/sessions/create', [PracticeSessionController::class, 'create'])->name('sessions.create');

Route::post('/sessions/autosave', [PracticeSessionController::class, 'autosaveStore'])->name('sessions.autosave.store');
Route::post('/sessions', [PracticeSessionController::class, 'store'])->name('sessions.store');

Route::get('/sessions/{session}/edit', [PracticeSessionController::class, 'edit'])->name('sessions.edit');
Route::put('/sessions/{session}/autosave', [PracticeSessionController::class, 'autosaveUpdate'])->name('sessions.autosave.update');
Route::post('/sessions/{session}/finish', [PracticeSessionController::class, 'finish'])->name('sessions.finish');
Route::put('/sessions/{session}', [PracticeSessionController::class, 'update'])->name('sessions.update');
Route::delete('/sessions/{session}', [PracticeSessionController::class, 'destroy'])->name('sessions.destroy');

Route::get('/sessions/{session}', [PracticeSessionController::class, 'show'])->name('sessions.show');

Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');

Route::inertia('/review', 'review/Index')->name('review.index');
Route::inertia('/review/session', 'review/Session')->name('review.session');

Route::inertia('/progress', 'progress/Index')->name('progress.index');

Route::inertia('/settings', 'settings/Preferences')->name('settings.index');
Route::inertia('/settings/import', 'settings/Import')->name('settings.import');
Route::inertia('/settings/export', 'settings/Export')->name('settings.export');
