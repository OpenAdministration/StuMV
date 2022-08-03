<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRequiredRoutesTest extends TestCase
{
    use RefreshDatabase;

    private array $routesWithLogin = [
        '/realms'
    ];

    public function test_route()
    {
        foreach ($this->routesWithLogin as $route){
            $response = $this->get($route);
            $response->assertStatus(302);
        }
    }
}
