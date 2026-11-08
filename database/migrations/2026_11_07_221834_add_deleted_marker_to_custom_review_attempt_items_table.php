<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An attempt keeps its snapshot for ever, so a snapshot whose item was
     * deleted carries its own marker instead of looking the original up again.
     * Once a snapshot is marked deleted nothing can undo it, because a deleted
     * item never comes back — even when the same words are saved again later.
     *
     * The index is the one that answers both questions Step 5 asks about an
     * identity: which references to leave a review, and which snapshots to
     * mark as deleted.
     */
    public function up(): void
    {
        Schema::table('custom_review_attempt_items', function (Blueprint $table): void {
            $table->boolean('is_deleted')->default(false)->after('skipped_at');
            $table->index(['kind', 'item_identity']);
        });
    }

    public function down(): void
    {
        Schema::table('custom_review_attempt_items', function (Blueprint $table): void {
            $table->dropIndex(['kind', 'item_identity']);
            $table->dropColumn('is_deleted');
        });
    }
};
