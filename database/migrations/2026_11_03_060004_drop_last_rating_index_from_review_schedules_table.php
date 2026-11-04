<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The daily choice reads each kind's schedules in bulk, so nothing
     * queries these two columns on their own. Only the identity pair
     * stays indexed, and even that one is needed for the upsert.
     */
    public function up(): void
    {
        Schema::table('review_schedules', function (Blueprint $table): void {
            $table->dropIndex('review_schedules_last_rating_index');
            $table->dropIndex('review_schedules_kind_due_on_index');
        });
    }

    public function down(): void
    {
        Schema::table('review_schedules', function (Blueprint $table): void {
            $table->index(['kind', 'due_on']);
            $table->index('last_rating');
        });
    }
};
