<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The exercise an item was chosen to appear as. A word can be a flashcard
     * in one review and a fill-in-the-blank in another, so the choice belongs
     * to the review item rather than the saved entry. Old rows have no value
     * and fall back to the kind's natural exercise.
     */
    public function up(): void
    {
        Schema::table('review_items', function (Blueprint $table): void {
            $table->string('exercise', 32)->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('review_items', function (Blueprint $table): void {
            $table->dropColumn('exercise');
        });
    }
};
