<?php

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a password reset is logged with the account itself as actor', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    $token = Password::broker()->createToken($member);
    $password = 'Aa1!'.bin2hex(random_bytes(8));

    $this->post(route('password.update', ['realm' => $community->getShortCode()]), [
        'token' => $token,
        'mail' => $member->email,
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertRedirect(route('realm.login', ['realm' => $community->getShortCode()]));

    expect(ActivityLog::sole()->only(['action', 'realm', 'subject_id', 'actor_type', 'actor_id']))->toBe([
        'action' => 'profile.password_reset',
        'realm' => $community->getShortCode(),
        'subject_id' => $member->uid,
        'actor_type' => 'user',
        'actor_id' => $member->uid,
    ]);
});
