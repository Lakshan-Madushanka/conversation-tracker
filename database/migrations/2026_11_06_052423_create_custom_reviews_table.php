<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A practice set the learner builds themselves and keeps.
     *
     * A custom review is the saved set, not a sitting: the `reviews` table
     * holds a sitting the learner works through, and the two are never shared.
     * Attempts (Step 4) will point at these rows.
     *
     * A review exists from the first moment it is started, so it needs no
     * separate staging area. It is named "Custom Review N" until the learner
     * renames it, which is why the name is not unique: renaming to a name
     * already in use is allowed. The status is `App\Enums\CustomReviewStatus`;
     * a new review starts empty, matching that enum's Empty case.
     */
    public function up(): void
    {
        Schema::create('custom_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('status', 20)->default('empty');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_reviews');
    }
};
