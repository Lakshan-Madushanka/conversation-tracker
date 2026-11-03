<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The daily marker: whether a review was started from the day's
     * suggestion, and the learner's local day on which it was completed, so
     * "You've reviewed today" can be worked out without guessing. A review
     * left part-way never gets a completed day, so the card never nags.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->boolean('is_daily_review')->default(false)->after('updated_at');
            $table->date('completed_on')->nullable()->after('is_daily_review');

            $table->index(['is_daily_review', 'completed_on']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex(['is_daily_review', 'completed_on']);
            $table->dropColumn(['is_daily_review', 'completed_on']);
        });
    }
};
