<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The kinds a review is allowed to hold, when the learner chooses a subset.
     *
     * Empty means every kind is allowed, which is what every existing review
     * and every new one starts with; only a learner's own narrowing writes a
     * value here. The add path and the + Add items view read it, and nothing
     * stored on an attempt ever does.
     */
    public function up(): void
    {
        Schema::table('custom_reviews', function (Blueprint $table): void {
            $table->json('allowed_kinds')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('custom_reviews', function (Blueprint $table): void {
            $table->dropColumn('allowed_kinds');
        });
    }
};
