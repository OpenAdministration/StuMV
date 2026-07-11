<?php

namespace Tests\Feature\Livewire\Committee;

use App\Livewire\Committee\EditRoleMembership;
use Livewire\Livewire;
use Tests\TestCase;

class EditRoleMembershipTest extends TestCase
{
    /** @test */
    public function renders_successfully()
    {
        // Quarantined: auto-generated stub that mounts the component without
        // its required mount parameters (e.g. $ou/$cn), so it errors on render.
        // TODO: write a real test that mounts with valid params.
        $this->markTestSkipped('Auto-generated stub: component needs mount parameters.');

        Livewire::test(EditRoleMembership::class)
            ->assertStatus(200);
    }
}
