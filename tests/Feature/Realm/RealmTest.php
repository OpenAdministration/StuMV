<?php

namespace Tests\Feature\Realm;

use App\Models\User;
use App\Models\Realm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use Tests\TestCase;

class RealmTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->assertAuthenticated();
    }

    public function test_is_route_accessible_and_livewire_there() : void
    {
        $response = $this->get('/realms');
        $response->assertStatus(200);
        $response->assertSeeLivewire('realm.crud');
    }

    public function test_shows_realm() : void
    {
        $realm = Realm::factory()->create();
        Livewire::test('realm.crud')
            ->assertSee([$realm->uid, $realm->long_name]);
    }

    public function test_search_with_result() : void
    {
        $realm = Realm::factory()->create();
        $search = substr($realm->uid, 1,1);
        Livewire::test('realm.crud')
            ->set('search', $search)->send()
            ->assertSee([$realm->long_name]);
    }

    public function test_search_without_result() : void
    {
        $realm = Realm::factory()->create();
        $search = 'aaaaa';
        Livewire::test('realm.crud')
            ->set('search', $search)->send()
            ->assertDontSee([$realm->long_name]);
    }

    public function test_open_edit_modal() : void
    {
        $realm = Realm::factory()->create();
        Livewire::test('realm.crud')
            ->call('edit', $realm->uid)
            ->assertSet('showEditModal', true)
            ->assertSet('editRealm.uid', $realm->uid);
    }

    public function test_edit_longName_and_save() : void
    {
        $realm = Realm::factory()->create();
        $newName = $this->faker->company();
        Livewire::test('realm.crud')
            ->call('edit', $realm->uid)
            ->set('editRealm.long_name', $newName)->send()
            ->call('save')
            ->assertSee($newName);
        $realm->refresh();
        $this->assertEquals($newName, $realm->long_name);
    }

    public function test_uid_not_editable() : void
    {
        $realm = Realm::factory()->create();
        $newName = $realm->uid . 'a';
        Livewire::test('realm.crud')
            ->call('edit', $realm->uid)
            ->set('editRealm.uid', $newName)->send()
            ->call('save')
            ->assertDontSee($newName);
        $realm->refresh();
        $this->assertNotEquals($newName, $realm->uid);
    }


}
