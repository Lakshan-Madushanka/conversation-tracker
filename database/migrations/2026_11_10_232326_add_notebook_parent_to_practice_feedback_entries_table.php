<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An entry keeps exactly one parent: a practice question or a notebook.
     * Both foreign keys stay real, so each parent's deletion cascades only to
     * its own entries; the model's save guard enforces the exactly-one choice,
     * which SQLite cannot express as an alterable CHECK constraint.
     */
    public function up(): void
    {
        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            $table->foreignId('practice_question_id')->nullable()->change();
            $table->foreignId('notebook_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unique(['notebook_id', 'type', 'client_id']);
            $table->index(['notebook_id', 'type']);
        });
    }

    /**
     * Safe only while no notebook entries exist: rows without a question
     * cannot go back under a required question parent.
     */
    public function down(): void
    {
        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            $table->dropUnique(['notebook_id', 'type', 'client_id']);
            $table->dropIndex(['notebook_id', 'type']);
            $table->dropConstrainedForeignId('notebook_id');
            $table->foreignId('practice_question_id')->nullable(false)->change();
        });
    }
};
