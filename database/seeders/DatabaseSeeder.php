<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DoctorSeeder::class,
        ]);

        foreach (range(1, 5) as $i) {
            User::factory()->create([
                'name' => "Test Patient $i",
                'email' => "patient$i@example.com",
            ]);
        }
    }
}
