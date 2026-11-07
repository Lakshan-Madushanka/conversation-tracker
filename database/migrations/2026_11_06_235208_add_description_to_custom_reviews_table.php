<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A custom review's optional description.
     *
     * Only the learner's own words are stored here. The review's items, their
     * availability and the "Too Many Items" tag are read live, never copied.
     */
    public function up(): void
    {
        Schema::table('custom_reviews', function (Blueprint $table): void {
            $table->string('description', 500)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('custom_reviews', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
