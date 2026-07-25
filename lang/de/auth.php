<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'Unbekannte Anmeldedaten.',
    'password' => 'Das angegebene Passwort ist inkorrekt.',
    'logout_confirmation' => 'Aktuell eingeloggt als :user. Möchtest du dich wirklich ausloggen?',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuche es in :seconds Sekunden nochmal.',
    'secure_area_text' => 'Dies ist ein geschützter Bereich der Anwendung. Bitte bestätige zunächst dein Passwort.',
    'forgot_password_text' => 'Du hast dein Passwort vergessen? Das ist kein Problem! Teile uns einfach deine E-Mail-Adresse mit und wir senden dir einen Link zum Zurücksetzen des Passworts zu, mit dem du ein neues Passwort setzen kannst.',
    'verification_text' => 'Danke für deine Registrierung! Um dich zu verifizieren, nutze den unten stehenden Button. Du bekommst dann eine E-Mail mit einem Link zugesandt, um deine Identität zu bestätigen.',
    'verification_link_sent_text' => 'Ein neuer Verifizierungslink wurde an die E-Mail-Adresse gesendet, die du bei der Registrierung angegeben hast.',

    'verification_mail_subject' => 'E-Mail-Adresse bestätigen',
    'verification_mail_line_between_greeting_and_action' => 'Bitte klicke den Button, um deine E-Mail-Adresse zu bestätigen und deinen Account im StuMV freizuschalten.',
    'verification_mail_button_action' => 'E-Mail-Adresse bestätigen',
    'verification_mail_line_after_action' => 'Falls du im StuMV keinen Account erstellt hast, musst du nichts weiter tun.',

    'remember_me' => 'Angemeldet bleiben',
    'forgot_password' => 'Passwort vergessen?',
    'send_verification_email' => 'Verifizierungs-E-Mail senden',
    'log_in' => 'Anmelden',
    'or_log_in_with' => 'oder anmelden mit',
    'log_out' => 'Abmelden',
    'confirm' => 'Bestätigen',
    'username_or_mail' => 'Anmeldename oder E-Mail',
    'reset_password' => 'Passwort zurücksetzen',
    'send_reset_link' => 'Link zum Zurücksetzen versenden',
    'sign_up_prompt' => 'Registrieren',
    'log_out_button' => 'Logout',
    'confirm_logout_title' => 'Bestätige Logout',
    'pick_realm_label' => 'Studierendenschaft',
    'pick_realm_placeholder' => 'Studierendenschaft auswählen',
    'pick_realm_continue' => 'Weiter',

    'authorize_heading' => 'Informationsweitergabe',
    'authorize_access_notice' => 'Du bist dabei, auf folgenden Dienst zuzugreifen:',
    'authorize_permissions_notice' => 'Dieser Dienst kann auf folgende Informationen zugreifen:',
    'authorize_reject' => 'Ablehnen',
    'authorize_accept' => 'Zulassen',

    'scope_openid' => 'OpenID Connect',
    'scope_openid_detail' => 'Bestätigt deine Identität, damit dieser Dienst weiß, wer sich anmeldet',
    'scope_profile' => 'Profil-Informationen',
    'scope_profile_detail' => 'Dein Name, Benutzername und Profilbild',
    'scope_email' => 'E-Mail-Adresse',
    'scope_email_detail' => 'Deine E-Mail-Adresse und ob sie bestätigt wurde',
    'scope_phone' => 'Telefonnummer',
    'scope_phone_detail' => 'Deine Telefonnummer',
    'scope_address' => 'Adresse',
    'scope_address_detail' => 'Deine Straße, Postleitzahl und dein Ort',
    'scope_committees' => 'Gremien und Rollen',
    'scope_committees_detail' => 'Die Gremien und Rollen, die du in dieser Studierendenschaft innehast',
    'scope_groups' => 'Gruppen',
    'scope_groups_detail' => 'Die Gruppen, in denen du Mitglied bist',
    'scope_users' => 'Konten',
    'scope_users_detail' => 'Verzeichnisinformationen über Mitglieder dieser Studierendenschaft (Name, Benutzername und Studiengang)',

    'claim_name' => 'Name',
    'claim_given_name' => 'Vorname',
    'claim_family_name' => 'Nachname',
    'claim_preferred_username' => 'Benutzername',
    'claim_picture' => 'Profilbild',
    'claim_email' => 'E-Mail',
    'claim_email_verified_suffix' => 'bestätigt',
    'claim_phone_number' => 'Telefon',
    'claim_address' => 'Adresse',
    'claim_groups' => 'Gruppen',
];
