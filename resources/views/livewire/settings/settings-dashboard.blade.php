<div>
    <div class="mb-8">
        <flux:heading size="xl">{{ __('realms.settings') }}</flux:heading>
    </div>
    <div class="grid auto-rows-fr grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 pb-6 sm:pb-8">
        <x-dashboard-tile
            :href="route('realms.edit', $uid)"
            icon="landmark"
            :heading="__('realms.dashboard.realms_edit_headline')"
            :description="__('realms.dashboard.realms_edit_explanation', ['name' => $name])"
        />
        <x-dashboard-tile
            :href="route('realms.branding', $uid)"
            icon="palette"
            :heading="__('realms.dashboard.branding_headline', ['name' => $name])"
            :description="__('realms.dashboard.branding_explanation', ['name' => $name])"
        />
        @unless($isAdminRealm)
            <x-dashboard-tile
                :href="route('realms.domains', $uid)"
                icon="globe"
                :heading="__('realms.dashboard.domains_headline', ['name' => $name])"
                :description="__('realms.dashboard.domains_explanation', ['name' => $name])"
            />
            <x-dashboard-tile
                :href="route('realms.api-clients', $uid)"
                icon="unplug"
                :heading="__('api_clients.list_title')"
                :description="__('api_clients.explanation')"
            />
            <x-dashboard-tile
                :href="route('realms.oidc-clients', $uid)"
                icon="network"
                :heading="__('oidc_clients.list_title')"
                :description="__('oidc_clients.explanation')"
            />
            <x-dashboard-tile
                :href="route('realms.identity-providers', $uid)"
                icon="git-pull-request-create-arrow"
                :heading="__('identity_providers.list_title')"
                :description="__('identity_providers.explanation')"
            />
        @endunless
    </div>
</div>
