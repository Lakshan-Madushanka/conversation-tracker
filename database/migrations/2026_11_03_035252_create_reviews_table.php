<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One review sitting: the fixed list of items the learner works through.
     *
     * There is no learner column because the app serves one learner, the same
     * way Collections keeps its marks without one. The items live in their own
     * table, and a review is finished once every item has a rating or can no
     * longer be shown.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
