<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
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

        User::factory()->state([
            'id' => 1,
            'username' => 'admin',
            'full_name' => 'Axel Admin',
            'email' => 'admin@stumv.de',
            'uid' => '61616161-6161-6161-6164-61646d696e',
            'email_verified_at' => now(),
        ])->create();

        // DB-side records for the LDAP demo logins (20-demo.ldif), emails verified.
        $this->call(DemoUsersSeeder::class);
        // \App\Models\User::factory(5)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
