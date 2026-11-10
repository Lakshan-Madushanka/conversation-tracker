<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notebooks', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191);
            // The topic outlives every notebook using it: deletion is refused
            // by the write that checks references, and the database keeps that
            // promise too.
            $table->foreignId('topic_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notebooks');
    }
};
