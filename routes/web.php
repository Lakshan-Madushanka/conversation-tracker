<?php

use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CollectionMarkController;
use App\Http\Controllers\CustomReviewController;
use App\Http\Controllers\CustomReviewsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LibraryBulkController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\PracticeSessionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewSuggestionController;
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

// Every item matching the current collections view, for "Select all" before
// adding to a review.
Route::get('/collections/selection', [CollectionController::class, 'selection'])->name('collections.selection');

// Custom reviews: the saved sets the learner builds item by item. These are
// distinct from /review/custom below, the Review feature's Quick review
// chooser, which starts a sitting straight away and saves nothing.
//
// The listing comes before the parameterised routes, so /custom-reviews is
// never mistaken for a review id. Search, statuses and the order travel in the
// address, and a JSON request returns the next page for "Load more".
Route::get('/custom-reviews', [CustomReviewsController::class, 'index'])->name('customReviews.index');

// The address names the review being added to, so a request never guesses it:
// an add with no review asks the server, which answers with the review, the
// picker, or the size warning.
Route::post('/custom-reviews', [CustomReviewsController::class, 'store'])->name('customReviews.store');
Route::post('/custom-reviews/items', [CustomReviewsController::class, 'storeItems'])->name('customReviews.items.store');
Route::delete('/custom-reviews/{customReview}/items', [CustomReviewsController::class, 'destroyItems'])
    ->whereNumber('customReview')
    ->name('customReviews.items.destroy');
Route::post('/custom-reviews/{customReview}/clear', [CustomReviewsController::class, 'clear'])
    ->whereNumber('customReview')
    ->name('customReviews.clear');

// The review's name, description and the learner's own Draft-or-Finished
// choice. Each saves on its own, with no other field touched.
Route::put('/custom-reviews/{customReview}', [CustomReviewsController::class, 'update'])
    ->whereNumber('customReview')
    ->name('customReviews.update');
Route::put('/custom-reviews/{customReview}/status', [CustomReviewsController::class, 'updateStatus'])
    ->whereNumber('customReview')
    ->name('customReviews.status');

// A review is named by its id, so this never answers to a word like "items".
Route::get('/custom-reviews/{customReview}', [CustomReviewsController::class, 'show'])
    ->whereNumber('customReview')
    ->name('customReviews.show');

// The learner's own marks on collection items: a star and status, and the
// personal notes with the version marker that catches another tab's change.
Route::put('/collections/marks', [CollectionMarkController::class, 'store'])->name('collections.marks.store');
Route::put('/collections/notes', [CollectionMarkController::class, 'storeNotes'])->name('collections.notes.store');

// Review and re-practice. The sitting is named in the exercise page's address,
// so a refresh returns to the next item not yet rated and a finished review
// shows its summary.
Route::get('/review', [ReviewController::class, 'index'])->name('review.index');
Route::post('/review', [ReviewController::class, 'store'])->name('review.store');

// A custom review keeps its choices in the address and never saves them, so the
// count shown is read the same way the started sitting is built.
Route::get('/review/custom', [CustomReviewController::class, 'index'])->name('review.custom');
Route::post('/review/custom', [CustomReviewController::class, 'store'])->name('review.customStore');

Route::get('/review/session', [ReviewController::class, 'show'])->name('review.session');
Route::post('/review/{review}/rate', [ReviewController::class, 'rate'])->name('review.rate');
Route::post('/review/{review}/finish-early', [ReviewController::class, 'finishEarly'])->name('review.finishEarly');

// The finish screen's status suggestions. Both answers name the item and the run
// they were made from, and only ever change the item's own status.
Route::post('/review/{review}/suggestion/confirm', [ReviewSuggestionController::class, 'confirm'])->name('review.suggestionConfirm');
Route::post('/review/{review}/suggestion/decline', [ReviewSuggestionController::class, 'decline'])->name('review.suggestionDecline');
Route::delete('/review/{review}/rating', [ReviewController::class, 'undo'])->name('review.undo');

// A past sitting, read as a result: what it held and how it went, with nothing
// to rate.
Route::get('/review/{review}/results', [ReviewController::class, 'results'])->name('review.results');

Route::inertia('/progress', 'progress/Index')->name('progress.index');

Route::inertia('/settings', 'settings/Preferences')->name('settings.index');
Route::inertia('/settings/import', 'settings/Import')->name('settings.import');
Route::inertia('/settings/export', 'settings/Export')->name('settings.export');
