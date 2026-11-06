<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The items being gathered for a custom review. Only a reference is kept:
     * the kind and the item's own identity, never a copy of its words. One
     * record per item, so adding the same item twice changes nothing.
     */
    public function up(): void
    {
        Schema::create('basket_items', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 30);
            $table->string('item_identity', 191);
            $table->timestamp('added_at');

            // A repeated Add, a retry or two tabs share the same identity and
            // therefore the same row.
            $table->unique(['kind', 'item_identity']);

            // The basket page and the bar read the basket newest first, and
            // count it by kind, without scanning the other kinds.
            $table->index(['kind', 'added_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basket_items');
    }
};
