<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            // Order inside one box, so entries reload exactly as the learner
            // pasted or added them.
            $table->unsignedInteger('position')->default(0)->after('type');

            // The identity an entry is born with, so a retried save updates it
            // instead of creating a second copy. Rows saved before entries
            // existed stay null until they are converted.
            $table->string('client_id', 64)->nullable()->after('position');

            $table->unique(['practice_question_id', 'type', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            $table->dropUnique(['practice_question_id', 'type', 'client_id']);
            $table->dropColumn(['position', 'client_id']);
        });
    }
};
