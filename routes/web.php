<?php

use App\Http\Controllers\CollectionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LibraryBulkController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\PracticeSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/sessions', LibraryController::class)->name('sessions.index');

// Selection routes sit before the parameterised session routes, so "selection"
// and "bulk" are never mistaken for a session id.
Route::get('/sessions/selection', [LibraryBulkController::class, 'selection'])->name('sessions.selection');
Route::post('/sessions/bulk/archive', [LibraryBulkController::class, 'archive'])->name('sessions.bulkArchive');
Route::post('/sessions/bulk/restore', [LibraryBulkController::class, 'restore'])->name('sessions.bulkRestore');
Route::post('/sessions/bulk/delete-preview', [LibraryBulkController::class, 'deletePreview'])->name('sessions.bulkDeletePreview');
Route::delete('/sessions/bulk/delete', [LibraryBulkController::class, 'delete'])->name('sessions.bulkDelete');

Route::get('/sessions/create', [PracticeSessionController::class, 'create'])->name('sessions.create');

Route::post('/sessions/autosave', [PracticeSessionController::class, 'autosaveStore'])->name('sessions.autosave.store');
Route::post('/sessions', [PracticeSessionController::class, 'store'])->name('sessions.store');

Route::get('/sessions/{session}/edit', [PracticeSessionController::class, 'edit'])->name('sessions.edit');
Route::get('/sessions/{session}/delete-preview', [PracticeSessionController::class, 'deletePreview'])->name('sessions.deletePreview');
Route::delete('/sessions/{sessionId}/delete', [PracticeSessionController::class, 'deletePermanently'])->name('sessions.deletePermanently');
Route::put('/sessions/{session}/autosave', [PracticeSessionController::class, 'autosaveUpdate'])->name('sessions.autosave.update');
Route::post('/sessions/{session}/finish', [PracticeSessionController::class, 'finish'])->name('sessions.finish');
Route::post('/sessions/{session}/archive', [PracticeSessionController::class, 'archive'])->name('sessions.archive');
Route::post('/sessions/{session}/restore', [PracticeSessionController::class, 'restore'])->name('sessions.restore');
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
