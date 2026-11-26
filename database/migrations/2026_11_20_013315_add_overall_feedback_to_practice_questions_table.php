<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The learner may keep a tutor's overall remark about a whole answer,
     * beside the five structured feedback boxes. It follows the same shape as
     * the question and answer: formatted markup, a plain-text copy for search
     * and counting, and the normalised copy search reads.
     */
    public function up(): void
    {
        Schema::table('practice_questions', function (Blueprint $table): void {
            $table->text('feedback_html')->default('')->after('answer_search_text');
            $table->text('feedback_text')->default('')->after('feedback_html');
            $table->text('feedback_search_text')->nullable()->after('feedback_text');
        });
    }

    public function down(): void
    {
        Schema::table('practice_questions', function (Blueprint $table): void {
            $table->dropColumn(['feedback_html', 'feedback_text', 'feedback_search_text']);
        });
    }
};
