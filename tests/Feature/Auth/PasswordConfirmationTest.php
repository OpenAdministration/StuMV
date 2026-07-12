<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('confirm password screen can be rendered', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/confirm-password')->assertStatus(200);
});

test('password is not confirmed with invalid password', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/confirm-password', ['password' => 'wrong-password'])
        ->assertSessionHasErrors();
});
