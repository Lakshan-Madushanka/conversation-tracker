<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a review was, and when the learner finished it.
     *
     * `is_custom_review` marks a sitting the learner chose themselves, so it
     * can never be mistaken for the day's suggestion or change the "You've
     * reviewed today" state. `finished_at` records the moment the finish screen
     * was reached — every item rated, or the learner chose to finish early — so
     * the recent results list has an honest date and never shows a sitting the
     * learner simply walked away from.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->boolean('is_custom_review')->default(false)->after('is_daily_review');
            $table->timestamp('finished_at')->nullable()->after('completed_on');

            // The recent results list: finished reviews, most recent first.
            $table->index(['finished_at', 'id'], 'reviews_finished_recent_index');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex('reviews_finished_recent_index');
            $table->dropColumn(['is_custom_review', 'finished_at']);
        });
    }
};
