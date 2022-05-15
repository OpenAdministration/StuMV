<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Realm;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {

        Realm::factory(5)
            ->has(Domain::factory(4)->sequence(
                ['forRegistration' => true],
                ['forRegistration' => false],
            ))
            ->create();
        \App\Models\User::factory(5)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
