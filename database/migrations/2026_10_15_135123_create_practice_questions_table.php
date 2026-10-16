<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            // Formatted content plus a plain-text version for search and review.
            $table->text('question_html');
            $table->text('question_text');
            $table->text('answer_html');
            $table->text('answer_text');
            $table->timestamps();

            $table->unique(['practice_session_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_questions');
    }
};
