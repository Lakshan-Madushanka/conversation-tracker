<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A normalised copy of everything the Library can search, kept beside the
     * words the learner wrote. Normalising once, when content is saved, keeps
     * the search query simple and makes it behave the same on SQLite and
     * MySQL: lower case, no accents, single spaces.
     *
     * The columns are nullable so an older row can be filled by the backfill
     * command without needing a default that differs between databases.
     */
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table): void {
            $table->text('search_text')->nullable()->after('normalized_name');
        });

        Schema::table('practice_questions', function (Blueprint $table): void {
            $table->text('question_search_text')->nullable()->after('question_text');
            $table->text('answer_search_text')->nullable()->after('answer_text');
        });

        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            $table->text('search_text')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            $table->dropColumn('search_text');
        });

        Schema::table('practice_questions', function (Blueprint $table): void {
            $table->dropColumn(['question_search_text', 'answer_search_text']);
        });

        Schema::table('topics', function (Blueprint $table): void {
            $table->dropColumn('search_text');
        });
    }
};
