<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A session is finished when the learner says so, and the moment it
     * happened is kept so the library and progress can order and present
     * finished sessions. Unfinished sessions keep this null.
     */
    public function up(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table): void {
            $table->timestamp('finished_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table): void {
            $table->dropColumn('finished_at');
        });
    }
};
