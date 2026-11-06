<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The items a custom review holds.
     *
     * An item is named by the same kind and identity Collections groups by,
     * never by a copy of its text: the current words are read again whenever
     * the review is shown, so an edit is reflected straight away.
     *
     * The pair of review and item is unique, so a repeated Add, a retry or two
     * tabs cannot give one review the same item twice. A review's own status
     * follows these rows and is held on `custom_reviews`.
     */
    public function up(): void
    {
        Schema::create('custom_review_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_review_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('item_identity', 191);
            $table->timestamp('added_at');

            // One item appears at most once in a review.
            $table->unique(
                ['custom_review_id', 'kind', 'item_identity'],
                'custom_review_items_review_kind_identity_unique',
            );

            // The review's page reads one review's items grouped by kind,
            // newest first, without scanning the other reviews.
            $table->index(
                ['custom_review_id', 'kind', 'added_at'],
                'custom_review_items_review_kind_added_index',
            );

            // An item's own row is found across every review that holds it, so
            // pruning a deleted item and following an edit never scan the table.
            $table->index(['kind', 'item_identity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_review_items');
    }
};
