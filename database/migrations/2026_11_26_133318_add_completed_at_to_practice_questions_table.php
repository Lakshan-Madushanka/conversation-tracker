<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A practice day is a day on which the learner recorded a complete
     * conversation: a question holding an answer and a natural version. The
     * moment that became true is kept here once, so editing the question later
     * never moves the day. Questions that were already complete are backfilled
     * from the moment their natural version was saved, or from the question's
     * own moment when that is unknown.
     */
    public function up(): void
    {
        Schema::table('practice_questions', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->after('feedback_search_text');
        });

        DB::table('practice_questions')
            ->whereRaw("TRIM(answer_text) <> ''")
            ->whereExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('practice_feedback_entries')
                    ->whereColumn('practice_feedback_entries.practice_question_id', 'practice_questions.id')
                    ->where('practice_feedback_entries.type', 'natural_version')
                    ->where(function (Builder $entry): void {
                        $entry->whereRaw("TRIM(practice_feedback_entries.content_text) <> ''")
                            ->orWhereRaw("TRIM(practice_feedback_entries.content_html) <> ''");
                    });
            })
            ->update([
                'completed_at' => DB::raw(
                    'COALESCE((SELECT MIN(pfe.created_at) FROM practice_feedback_entries pfe '
                    .'WHERE pfe.practice_question_id = practice_questions.id '
                    ."AND pfe.type = 'natural_version' "
                    ."AND (TRIM(pfe.content_text) <> '' OR TRIM(pfe.content_html) <> '')), "
                    .'practice_questions.created_at)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('practice_questions', function (Blueprint $table): void {
            $table->dropColumn('completed_at');
        });
    }
};
