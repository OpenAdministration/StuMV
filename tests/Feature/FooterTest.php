<?php

test('the "based on" footer note is translated when the app is white-labeled', function (): void {
    config(['app.name' => 'Whitelabel']);

    // App\Http\Middleware\SetLocale derives the locale fresh from each
    // request's Accept-Language header, overriding anything app()->setLocale()
    // set beforehand - the header is what actually needs to say "de" here.
    // Also compute the expected string via trans(..., 'de') explicitly
    // rather than __() (which reads whatever the *current* app locale
    // happens to be at assertion time), so this doesn't depend on the
    // ambient locale either.
    //
    // /register just redirects to the shared login picker (see
    // routes/auth.php) - follow it to reach the actual rendered page the
    // footer lives on.
    $response = $this->withHeader('Accept-Language', 'de')->followingRedirects()->get('/register');

    $response->assertSee(trans('common.based_on', ['name' => 'Whitelabel'], 'de'))
        ->assertDontSee('Whitelabel is based on');
});

test('the "based on" note in the info modal is translated when the app is white-labeled', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    config(['app.name' => 'Whitelabel']);

    $response = $this->withHeader('Accept-Language', 'de')->get('/'.$uid.'/dashboard');

    $response->assertSee(trans('common.based_on', ['name' => 'Whitelabel'], 'de'))
        ->assertDontSee('Whitelabel is based on');
});
