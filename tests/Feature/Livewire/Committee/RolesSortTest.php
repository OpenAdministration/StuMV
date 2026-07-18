<?php

use App\Livewire\Committee\ListRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('roles are sorted by name', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'zzz')->fill(['description' => 'Zebra'])->save();
    TestLdap::makeRole($committee, 'aaa')->fill(['description' => 'Apple'])->save();
    TestLdap::makeRole($committee, 'mmm')->fill(['description' => 'Mango'])->save();
    actingAsModerator($community);

    $html = Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])
        ->call('loadRoles')
        ->set('showOnlyActive', false)
        ->html();

    $posApple = strpos($html, 'Apple');
    $posMango = strpos($html, 'Mango');
    $posZebra = strpos($html, 'Zebra');

    expect($posApple)->toBeLessThan($posMango)
        ->and($posMango)->toBeLessThan($posZebra);
});
