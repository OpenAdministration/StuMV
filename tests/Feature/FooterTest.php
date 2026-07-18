<?php

test('the "based on" footer note is translated when the app is white-labeled', function (): void {
    app()->setLocale('de');
    config(['app.name' => 'Whitelabel']);

    $response = $this->get('/register');

    $response->assertSee(__('common.based_on', ['name' => 'Whitelabel']))
        ->assertDontSee('Whitelabel is based on');
});

test('the "based on" note in the info modal is translated when the app is white-labeled', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    app()->setLocale('de');
    config(['app.name' => 'Whitelabel']);

    $response = $this->get('/'.$uid.'/dashboard');

    $response->assertSee(__('common.based_on', ['name' => 'Whitelabel']))
        ->assertDontSee('Whitelabel is based on');
});
