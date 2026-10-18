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
            // The correction column of a mistake: a short line that keeps bold
            // and italic, with a plain-text copy for search and review.
            $table->text('correction_html')->default('')->after('content_text');
            $table->text('correction_text')->default('')->after('correction_html');

            // The optional "why" column of a mistake.
            $table->text('reason')->default('')->after('correction_text');

            // Whether this row has already been through the splitter, so
            // converting old mistakes stays a one-time job.
            $table->boolean('is_split')->default(false)->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            $table->dropColumn(['correction_html', 'correction_text', 'reason', 'is_split']);
        });
    }
};
