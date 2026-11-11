<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The notebook's own metadata and the markers its automatic saving needs:
     * a cleaned rich description with its plain-text and search projections,
     * a version for two-tab conflict detection, a client key so a repeated
     * creation returns the same notebook, and the last save key so a retried
     * save changes nothing.
     */
    public function up(): void
    {
        Schema::table('notebooks', function (Blueprint $table): void {
            $table->text('description_html')->nullable();
            $table->text('description_text')->nullable();
            $table->text('search_text')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('client_key', 64)->nullable()->unique();
            $table->string('last_save_key', 64)->nullable();
            $table->timestamp('last_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notebooks', function (Blueprint $table): void {
            $table->dropUnique(['client_key']);
            $table->dropColumn([
                'description_html',
                'description_text',
                'search_text',
                'version',
                'client_key',
                'last_save_key',
                'last_changed_at',
            ]);
        });
    }
};
