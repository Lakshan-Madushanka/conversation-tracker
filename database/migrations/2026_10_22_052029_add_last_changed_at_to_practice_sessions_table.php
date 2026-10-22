<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table): void {
            $table->timestamp('last_changed_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table): void {
            $table->dropColumn('last_changed_at');
        });
    }
};
