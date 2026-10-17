<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automatic saving needs a few extra markers on a session:
     *
     * - `version` lets a save detect that another tab changed the session, so
     *   newer work is never silently overwritten.
     * - `client_key` identifies a session the editor created, so a repeated
     *   first save cannot create a second session.
     * - `last_save_key` remembers the last save the server applied, so a
     *   retried save of the same content changes nothing.
     */
    public function up(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->string('client_key', 64)->nullable()->unique()->after('version');
            $table->string('last_save_key', 64)->nullable()->after('client_key');
        });
    }

    public function down(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table): void {
            $table->dropUnique(['client_key']);
            $table->dropColumn(['version', 'client_key', 'last_save_key']);
        });
    }
};
