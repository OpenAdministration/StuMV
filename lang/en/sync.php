<?php

return [
    'menu_title' => 'Synchronize',
    'menu_ldap' => 'LDAP',
    'menu_mailman' => 'Mailing lists',
    'ldap_title' => 'Synchronize role members and rights with LDAP',
    'ldap_success' => 'LDAP has been synchronized successfully.',
    'ldap_preview_heading' => 'Review changes',
    'ldap_preview_text' => 'The following changes will be made to LDAP when you synchronize:',
    'ldap_preview_empty' => 'No changes - LDAP is already up to date.',
    'mailman_title' => 'Synchronize the mailing lists with the rights groups',
    'mailman_confirm_text' => 'All mailing lists of this community will be matched to their rights groups. Addresses that no longer belong to the group will be removed from the list.',
    'mailman_running' => 'Synchronizing. This can take a few minutes for large lists.',
    'mailman_success' => 'The mailing lists have been synchronized successfully.',
    'mailman_failed' => 'The mailing lists could not be fully synchronized. See the log for details.',
    'roles_section' => 'Roles',
    'groups_section' => 'Groups',
    'confirm' => 'Synchronize',
    'member_added' => 'Added:',
    'member_removed' => 'Removed:',
];
