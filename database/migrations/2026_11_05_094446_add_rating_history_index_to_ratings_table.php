<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An item's ratings in the order they were given.
     *
     * The schedule rebuild and the status suggestions both read one item's
     * history oldest first, so the pair lookup and the ordering share one index.
     */
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table): void {
            $table->dropIndex('ratings_kind_item_identity_index');
            $table->index(['kind', 'item_identity', 'created_at'], 'ratings_item_history_index');
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table): void {
            $table->dropIndex('ratings_item_history_index');
            $table->index(['kind', 'item_identity'], 'ratings_kind_item_identity_index');
        });
    }
};
