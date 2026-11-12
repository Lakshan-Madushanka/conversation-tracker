<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One key per notebook creation request, so a retry or a double press
     * finds the review it already made instead of making a second one.
     */
    public function up(): void
    {
        Schema::table('custom_reviews', function (Blueprint $table): void {
            $table->string('creation_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('custom_reviews', function (Blueprint $table): void {
            $table->dropUnique(['creation_key']);
            $table->dropColumn('creation_key');
        });
    }
};
