<?php

namespace Database\Seeders;

use App\Models\Committee;
use App\Models\Domain;
use App\Models\Realm;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
                ['for_registration' => true],
                ['for_registration' => false],
            ))
            ->has(Committee::factory(1)->has(
                Committee::factory(2)->state(function (array $attributes, Committee $committee){
                    return ['realm_uid' => $committee->realm_uid];
                })->has(Role::factory(1)->default_names()),
                'childCommittee')
                ->has(Role::factory(1)->default_names())
            )
            ->create();

        User::factory()->state([
            'id' => 1,
            'username' =>  'admin',
            'full_name' => 'admin',
            'email' => 'admin@example.com',
            'is_superuser' => true,
            'password' => Hash::make('admin'),
            'uid' => '61616161-6161-6161-6164-61646d696e',
            'domain' => 'default'
        ])->create();
        //\App\Models\User::factory(5)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
