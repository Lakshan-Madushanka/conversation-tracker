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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->onDelete('cascade');
            $table->string('item_identity'); // normalized key matching Collections' item identity
            $table->string('kind'); // 'word' or 'phrase'
            $table->enum('rating', ['hard', 'okay', 'easy']);
            $table->string('unique_key'); // unique key to prevent double-saving
            $table->timestamp('given_at')->useCurrent();
            $table->boolean('is_pending')->default(false);

            $table->index(['review_id', 'item_identity', 'kind']);
            $table->index(['unique_key']);
            $table->index(['item_identity', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};