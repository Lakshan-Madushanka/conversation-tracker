<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How the learner judged one item in one review.
     *
     * Ratings belong to the item's identity, so a later schedule can follow it
     * through a typo fix and back into view when it returns. `unique_key` makes
     * a double tap, a retry or a repeated request count once: the second write
     * finds the row and leaves it alone. `kind` plus `item_identity` is unique
     * within a review, so one item can never be counted twice in one sitting.
     */
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('item_identity', 191);
            $table->string('rating', 16);
            $table->string('unique_key', 64)->unique();
            $table->timestamps();

            $table->unique(['review_id', 'kind', 'item_identity']);
            $table->index(['kind', 'item_identity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
