<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The learner's own marks for collection items: a star, a status and
     * personal notes.
     *
     * Marks belong to the item, not to one session, so they are keyed by the
     * item's identity in one place (`kind` plus `item_key`, an opaque short
     * hash of the shared identity), never by a session. That keeps marks alive
     * when an item disappears and lets them return with it. Every grammar note
     * keeps its own identity, so its marks belong to that note itself.
     *
     * The note is stored twice: as cleaned formatted text and as the
     * plain-text copy a later search reads. `notes_version` detects changes
     * made in another tab.
     *
     * One row per learner and item, enforced by the unique index, even when
     * requests repeat or arrive together. Nothing here is ever deleted when an
     * item disappears, so Step 5 can filter starred and status and search the
     * plain text directly on this table.
     */
    public function up(): void
    {
        Schema::create('collection_item_marks', function (Blueprint $table): void {
            $table->comment('The learner\'s star, status and personal notes for one collection item.');

            $table->id();

            // The kind of item (word, phrase, mistake or grammar note), named
            // with the feedback type it comes from.
            $table->string('kind', 30);

            // An opaque, shortened identity of the item, derived from the
            // shared identity rules, so marks, grouping and search agree.
            $table->string('item_key', 64);

            $table->boolean('starred')->default(false);

            // The learner's own label: null until they choose one, and only
            // ever changed by them.
            $table->string('status', 20)->nullable();

            // The personal note as cleaned formatted text and its plain-text
            // copy, kept for note-less items as empty strings.
            $table->text('notes_html')->default('');
            $table->text('notes_text')->default('');

            // Detects notes changed in another tab; 0 means no note yet.
            $table->unsignedBigInteger('notes_version')->default(0);

            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();

            // At most one record per learner and item.
            $table->unique(['kind', 'item_key']);

            // Whatever Step 5 needs to filter starred-only and by status.
            $table->index(['kind', 'starred']);
            $table->index(['kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_item_marks');
    }
};
