<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the orders and filters the Library offers. The id is part of
     * each cursor index because two sessions can share a moment and the list
     * still needs one stable order.
     */
    public function up(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table): void {
            $table->index(['created_at', 'id'], 'practice_sessions_created_cursor_index');
            $table->index(['last_changed_at', 'id'], 'practice_sessions_edited_cursor_index');
            $table->index(['status', 'created_at'], 'practice_sessions_status_created_index');
            $table->index(['topic_id', 'created_at'], 'practice_sessions_topic_created_index');
        });

        // A session that was never edited is ordered by the day it happened.
        DB::table('practice_sessions')
            ->whereNull('last_changed_at')
            ->update(['last_changed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table): void {
            $table->dropIndex('practice_sessions_created_cursor_index');
            $table->dropIndex('practice_sessions_edited_cursor_index');
            $table->dropIndex('practice_sessions_status_created_index');
            $table->dropIndex('practice_sessions_topic_created_index');
        });
    }
};
