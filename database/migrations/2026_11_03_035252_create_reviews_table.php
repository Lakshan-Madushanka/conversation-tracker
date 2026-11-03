<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('session_id')->constrained('practice_sessions')->onDelete('cascade');
            $table->string('item_identity'); // normalized key for the item
            $table->string('item_type'); // 'word' or 'phrase'
            $table->boolean('is_reviewed_first_time'); // whether this was the first review of this item
            $table->json('metadata')->nullable(); // flexible metadata
            $table->timestamps();

            $table->index(['learner_id', 'session_id']);
            $table->index(['learner_id', 'item_identity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};