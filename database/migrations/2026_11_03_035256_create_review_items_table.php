<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fixed list of items chosen when a review starts.
     *
     * An item is named by the same identity Collections groups by (`kind` plus
     * the normalised words), never by a copy of its text: the current words are
     * read again whenever the card is shown, so an edit is reflected straight
     * away and a removed item is skipped instead of showing something stale.
     */
    public function up(): void
    {
        Schema::create('review_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('kind', 32);
            $table->string('item_identity', 191);
            $table->timestamps();

            // One item appears at most once in a review.
            $table->unique(['review_id', 'kind', 'item_identity']);
            $table->index(['review_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');
    }
};
