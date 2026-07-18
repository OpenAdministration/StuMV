<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('reset password link screen can be rendered', function (): void {
    $this->get('/forgot-password')->assertStatus(200);
});
