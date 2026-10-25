<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // A fresh local database gets a long Library to browse. The seeder
        // itself refuses to run outside the local environment.
        if (app()->isLocal() && PracticeSession::query()->doesntExist()) {
            $this->call(LibrarySeeder::class);
        }
    }
}
