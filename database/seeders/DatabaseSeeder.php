<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events stay switched on: the searchable copy and the Collections
     * identity are worked out as a row saves, so a seed that muted them would
     * leave rows the Library search and the Collection pages cannot see.
     */
    public function run(): void
    {
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
