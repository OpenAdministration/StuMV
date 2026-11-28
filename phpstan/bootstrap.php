<?php

use App\Models\User;

// The session guard authenticates App\Models\User (LdapRecord syncs it from LDAP),
// while config/auth.php names the LDAP model - Larastan would type auth()->user() as that.
config(['auth.providers.users.model' => User::class]);
