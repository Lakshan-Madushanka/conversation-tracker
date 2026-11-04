<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one current schedule per item: when it is next worth revisiting.
     *
     * The schedule is derived from the ratings, so this table is a stored
     * copy kept for speed, never a second source of truth — the rebuild rule
     * in `App\Support\ReviewSchedule` decides, this table remembers. At most
     * one row per kind and identity; a never-rated item has no row and is
     * due right away.
     */
    public function up(): void
    {
        Schema::create('review_schedules', function (Blueprint $table): void {
            $table->id();

            // The item's kind (the feedback type it comes from) and identity,
            // the same pairing the ratings use.
            $table->string('kind', 32);
            $table->string('item_identity', 191);

            // The learner's local calendar day the item is next worth
            // revisiting. Overdue items stay due until they are reviewed.
            $table->date('due_on');

            $table->date('last_rated_on');
            $table->string('last_rating', 16);
            $table->unsignedInteger('rated_count')->default(0);

            // How many Easy ratings in a row end the history; drives the gap.
            $table->unsignedInteger('easy_run')->default(0);

            $table->timestamps();

            // One schedule per learner and item.
            $table->unique(['kind', 'item_identity']);

            // Finding what is due, most overdue first.
            $table->index(['kind', 'due_on']);
            $table->index('last_rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_schedules');
    }
};
