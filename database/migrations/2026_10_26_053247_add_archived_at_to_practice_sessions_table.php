<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archiving is a quiet, reversible way to hide a finished session. The
     * moment it happened is enough to know both that a session is archived and
     * when, so no separate flag is needed.
     */
    public function up(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('last_changed_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};
