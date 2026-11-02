<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A small, normalised copy of what the Collections page searches, groups,
     * filters and sorts by, kept beside the words the learner wrote.
     *
     * Doing the work once, when a row is saved, means the page can group,
     * filter and order in the database instead of loading every row into
     * memory. The columns are nullable so older rows can be filled by the
     * backfill command without a database-specific default.
     */
    public function up(): void
    {
        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            // The item's public identity: the normalised word or phrase, the
            // normalised mistake pair, or the note's own `note-{id}` key.
            $table->string('collection_key', 191)->nullable()->after('type');

            // The words A to Z orders by, without capitals or accents.
            $table->string('sort_text', 191)->nullable()->after('collection_key');

            // Why this row asks for attention, in the shared wording, or null.
            $table->string('attention_reason', 255)->nullable()->after('sort_text');

            // Whether this row's line held too many separators to split.
            $table->boolean('split_broke')->default(false)->after('attention_reason');

            $table->index(['type', 'collection_key']);
            $table->index(['type', 'sort_text']);
        });

        Schema::table('collection_item_marks', function (Blueprint $table): void {
            // The item's public key, so the page can join a mark to an item
            // without reversing the opaque storage hash.
            $table->string('group_key', 191)->nullable()->after('item_key');

            // The learner's note in the same normalised form search reads.
            $table->text('notes_search_text')->nullable()->after('notes_text');

            $table->index(['kind', 'group_key']);
        });
    }

    public function down(): void
    {
        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            $table->dropIndex(['type', 'sort_text']);
            $table->dropIndex(['type', 'collection_key']);
            $table->dropColumn(['collection_key', 'sort_text', 'attention_reason', 'split_broke']);
        });

        Schema::table('collection_item_marks', function (Blueprint $table): void {
            $table->dropIndex(['kind', 'group_key']);
            $table->dropColumn(['group_key', 'notes_search_text']);
        });
    }
};
