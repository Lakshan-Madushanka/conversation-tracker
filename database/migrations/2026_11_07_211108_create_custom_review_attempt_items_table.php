<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One item in one attempt, with everything the attempt needs to show it
     * again after the live item has changed.
     *
     * The snapshot is taken when the attempt starts and is never updated: the
     * display text, the card's words, its topic, source session and the moment
     * it was read all live here. The rating is written to this row only, with a
     * unique key, so a repeated request counts once. `skipped_at` is kept for
     * the attempt's completion rule and the history's Skipped state.
     */
    public function up(): void
    {
        Schema::create('custom_review_attempt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_review_attempt_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('kind', 30);
            $table->string('exercise', 30);
            $table->string('item_identity', 191);

            // What the attempt shows, frozen at start.
            $table->text('snapshot_text');
            $table->json('snapshot');
            $table->string('topic')->default('');
            $table->timestamp('session_date')->nullable();
            $table->unsignedBigInteger('source_session_id')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('snapshot_taken_at');

            $table->string('rating', 16)->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->string('rating_key', 64)->nullable()->unique();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            // One item appears at most once in an attempt.
            $table->unique(
                ['custom_review_attempt_id', 'kind', 'item_identity'],
                'custom_review_attempt_items_attempt_kind_identity_unique',
            );

            // The attempt screen reads one attempt's items in order.
            $table->index(
                ['custom_review_attempt_id', 'position'],
                'custom_review_attempt_items_attempt_position_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_review_attempt_items');
    }
};
