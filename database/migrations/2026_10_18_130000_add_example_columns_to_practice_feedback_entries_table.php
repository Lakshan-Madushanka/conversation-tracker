<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            // The optional example of a vocabulary or phrase row: a short line
            // that keeps bold and italic, with its plain-text copy in
            // `example`. Examples used to share the mistake's `reason` pair;
            // these columns give them a home of their own.
            $table->text('example')->default('')->after('reason_html');
            $table->json('example_html')->default('')->after('example');
        });

        // Move every example already stored under the reason columns.
        DB::table('practice_feedback_entries')
            ->whereIn('type', ['vocabulary', 'phrase'])
            ->update([
                'example' => DB::raw('reason'),
                'example_html' => DB::raw('reason_html'),
                'reason' => '',
                'reason_html' => '',
            ]);

        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            // Only examples were ever written here, and they now live in
            // `example_html`.
            $table->dropColumn('reason_html');
        });
    }

    public function down(): void
    {
        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            $table->text('reason_html')->default('')->after('reason');
        });

        DB::table('practice_feedback_entries')
            ->whereIn('type', ['vocabulary', 'phrase'])
            ->update([
                'reason' => DB::raw('example'),
                'reason_html' => DB::raw('example_html'),
            ]);

        Schema::table('practice_feedback_entries', function (Blueprint $table): void {
            $table->dropColumn(['example', 'example_html']);
        });
    }
};
