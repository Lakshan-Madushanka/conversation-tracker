<?php

declare(strict_types=1);

use App\Support\SearchText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The listing searches a review's name and description without caring
     * about case, accents or extra spaces, so both are kept as one normalised
     * projection, exactly as the Library does for its entries.
     *
     * The indexes are the ones the listing's own controls read: matching a
     * name, narrowing the three statuses, and ordering by when a review was
     * created.
     */
    public function up(): void
    {
        Schema::table('custom_reviews', function (Blueprint $table): void {
            $table->string('search_text', 700)->nullable()->after('description');
            $table->index('name');
            $table->index('status');
            $table->index('created_at');
        });

        DB::table('custom_reviews')
            ->select(['id', 'name', 'description'])
            ->orderBy('id')
            ->chunkById(100, function ($reviews): void {
                foreach ($reviews as $review) {
                    $text = trim($review->name.' '.($review->description));

                    DB::table('custom_reviews')
                        ->where('id', $review->id)
                        ->update(['search_text' => SearchText::normalize($text)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('custom_reviews', function (Blueprint $table): void {
            $table->dropIndex(['name']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropColumn('search_text');
        });
    }
};
