<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The daily suggestion sometimes asks "which items were rated today?"
     * (for the "Review a bit more" selection). One small index keeps that a
     * lookup instead of a scan of the whole history.
     */
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table): void {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
        });
    }
};
