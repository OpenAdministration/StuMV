<div>
    <div class="mb-8 space-y-4">
        <flux:heading size="xl">{{ __('tools.tools') }}</flux:heading>
        <flux:text class="text-base">{{ __('tools.dashboard_explanation') }}</flux:text>
    </div>
    <div class="grid auto-rows-fr grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 pb-6 sm:pb-8">
        <x-dashboard-tile
            :href="route('tools.compare-email-list', $uid)"
            icon="list-check"
            :heading="__('tools.compare_email_list_headline')"
            :description="__('tools.compare_email_list_explanation')"
        />
        @if($unildapDataExists)
            <x-dashboard-tile
                :href="route('tools.import-user-uni-ldap', $uid)"
                icon="user-plus"
                :heading="__('tools.import_users_from_uni_ldap_headline')"
                :description="__('tools.import_users_from_uni_ldap_explanation')"
            />
            <x-dashboard-tile
                :href="route('tools.users-not-in-uni-ldap', $uid)"
                icon="user-search"
                :heading="__('tools.users_not_in_uni_ldap_headline')"
                :description="__('tools.users_not_in_uni_ldap_explanation')"
            />
        @endif
        <x-dashboard-tile
            :href="route('tools.unused-roles', $uid)"
            icon="list-x"
            :heading="__('tools.unused_roles_headline')"
            :description="__('tools.unused_roles_explanation')"
        />
        <x-dashboard-tile
            :href="route('tools.invitations', $uid)"
            icon="mail"
            :heading="__('tools.invite_user_headline')"
            :description="__('tools.invite_user_explanation')"
        />
    </div>
</div>
