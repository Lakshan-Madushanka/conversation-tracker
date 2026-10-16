<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_feedback_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('practice_question_id')->constrained()->cascadeOnDelete();
            // One of the five feedback boxes: mistake, vocabulary, phrase,
            // grammar_note or natural_version. Stored per entry (rather than as
            // columns on the question) so a box can later hold several entries.
            $table->string('type', 30);
            // Rich types keep their formatted markup plus a plain-text version
            // for search, counting and review; plain types fill only the text.
            $table->text('content_html')->default('');
            $table->text('content_text');
            $table->timestamps();

            $table->index(['practice_question_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_feedback_entries');
    }
};
