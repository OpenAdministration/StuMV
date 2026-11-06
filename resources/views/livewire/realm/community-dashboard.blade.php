<div>
    <div class="mb-8 space-y-4">
        <flux:heading size="xl">{{ __('realms.dashboard.headline', ['name' => $name]) }}</flux:heading>
        <flux:text class="text-base">{{ __('realms.dashboard.explanation', ['name' => $name]) }}</flux:text>
    </div>
    <div class="grid auto-rows-fr grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 pb-6 sm:pb-8">
        <x-dashboard-tile
            :href="route('profile', ['realm' => $uid, 'username' => auth()->user()->username])"
            icon="circle-user"
            :heading="__('profile.breadcrumb')"
            :description="__('realms.dashboard.profile_explanation', ['name' => $name])"
        />
        @unless($community->isAdminRealm())
            <x-dashboard-tile
                :href="route('committees.list', $uid)"
                icon="list-tree"
                :heading="__('realms.nav_committees_and_roles')"
                :description="__('realms.dashboard.committee_explanation', ['name' => $name])"
            />
        @endunless
        <x-dashboard-tile
            :href="route('realms.members', $uid)"
            icon="users"
            :heading="__('realms.nav_people')"
            :description="__('realms.dashboard.members_explanation', ['name' => $name])"
        />
        @unless($community->isAdminRealm())
            <x-dashboard-tile
                :href="route('realms.mods', $uid)"
                icon="user-star"
                :heading="__('realms.dashboard.mods_headline', ['name' => $name])"
                :description="__('realms.dashboard.mods_explanation', ['name' => $name])"
            />
        @endunless
        @if(! $community->isAdminRealm() && (auth()->user()->can('moderator', $community) || auth()->user()->can('admin', $community) || auth()->user()->can('superadmin', \App\Models\User::class)))
            <x-dashboard-tile
                :href="route('realms.admins', $uid)"
                icon="shield-user"
                :heading="__('realms.dashboard.admin_headline', ['name' => $name])"
                :description="__('realms.dashboard.admin_explanation', ['name' => $name])"
            />
        @endif
        @if(! $community->isAdminRealm() && auth()->user()->can('viewAny', [\App\Ldap\Group::class, $community]))
            <x-dashboard-tile
                :href="route('realms.groups', $uid)"
                icon="key-round"
                :heading="__('realms.dashboard.groups_headline', ['name' => $name])"
                :description="__('realms.dashboard.groups_explanation', ['name' => $name])"
            />
        @endif
        @if(! $community->isAdminRealm() && auth()->user()->can('viewAny', [\App\Ldap\Group::class, $community]))
            <x-dashboard-tile
                :href="route('realms.group-mailman-lists', $uid)"
                icon="mail"
                :heading="__('realms.dashboard.group_mailman_lists_headline', ['name' => $name])"
                :description="__('realms.dashboard.group_mailman_lists_explanation', ['name' => $name])"
            />
        @endif
        @if(auth()->user()->can('admin', $community) || auth()->user()->can('superadmin', \App\Models\User::class))
            <x-dashboard-tile
                :href="route('settings.dashboard', $uid)"
                icon="settings"
                :heading="__('realms.settings')"
                :description="__('realms.dashboard.settings_explanation')"
            />
        @endif
    </div>
</div>
