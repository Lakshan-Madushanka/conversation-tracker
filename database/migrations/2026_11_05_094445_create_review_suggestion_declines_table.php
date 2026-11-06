<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The suggestions the learner said "Not now" to.
     *
     * One row per item and status, naming the rating that began the run it was
     * declined for. The run, not the item, is what the refusal covers: while
     * the same run goes on the finish screen stays quiet, and a new run that
     * reaches the threshold may suggest again. Undoing the rating that began a
     * run takes the refusal with it, because the run it named no longer exists.
     */
    public function up(): void
    {
        Schema::create('review_suggestion_declines', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 32);
            $table->string('item_identity', 191);
            $table->string('status', 32);
            $table->foreignId('run_started_id')->constrained('ratings')->cascadeOnDelete();
            $table->timestamps();

            // One refusal per item and status; a later refusal of the same
            // item names its newer run instead.
            $table->unique(['kind', 'item_identity', 'status'], 'review_suggestion_declines_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_suggestion_declines');
    }
};
