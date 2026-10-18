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
            // The third column of a feedback table that keeps formatting, such
            // as the optional example of a vocabulary row: a short line that
            // keeps bold and italic, with its plain-text copy in `reason`.
            $table->text('reason_html')->default('')->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            $table->dropColumn('reason_html');
        });
    }
};
