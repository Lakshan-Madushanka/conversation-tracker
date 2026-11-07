<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One attempt the learner made at a custom review.
     *
     * An attempt is its own thing: it belongs to a custom review, keeps its own
     * ratings, and never changes a review, an item, a mark or a schedule. The
     * number counts every attempt actually started (a Not started shell keeps
     * none, so it never takes a number), and `status` is descriptive only.
     *
     * Counts are never stored: they are worked out from the attempt's items
     * each time they are read.
     */
    public function up(): void
    {
        Schema::create('custom_review_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_review_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number')->nullable();
            $table->string('status', 20);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // One attempt number per review, and never two rows for it.
            $table->unique(['custom_review_id', 'number']);

            // The history and the card read a review's attempts by when they
            // started, without scanning the other reviews.
            $table->index(['custom_review_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_review_attempts');
    }
};
