<div>
    <div class="sm:flex-auto">
        <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('realms.dashboard.headline', ['name' => $name]) }}</h1>
        <p class="mt-2 text-sm text-gray-700">{{ __('realms.dashboard.explanation', ['name' => $name]) }}</p>
    </div>
    <div class="-mx-6 -mb-6 mt-10 border-t divide-y-1 divide-gray-200 overflow-hidden rounded-b-lg bg-gray-200 sm:grid sm:grid-cols-2 sm:gap-px sm:divide-y-0">
        <x-dashboard-card :href="route('profile')" color="bg-yellow-100 text-yellow-700" icon="fas-user-gear">
            <x-slot:headline>
                {{ __('realms.dashboard.profile_heading', ['name' => $name]) }}
            </x-slot:headline>
            {{ __('realms.dashboard.profile_explanation', ['name' => $name]) }}
        </x-dashboard-card>

        <x-dashboard-card :href="route('committees.list', $uid)" color="bg-rose-100 text-rose-700" icon="fas-sitemap">
            <x-slot:headline>
                {{ __('realms.dashboard.committee_headline', ['name' => $name]) }}
            </x-slot:headline>
            {{ __('realms.dashboard.committee_explanation', ['name' => $name]) }}
        </x-dashboard-card>

        <x-dashboard-card :href="route('realms.members', $uid)" color="bg-lime-100 text-lime-700" icon="fas-user-group">
            <x-slot:headline>
                {{ __('realms.dashboard.members_heading', ['name' => $name]) }}
            </x-slot:headline>
            {{ __('realms.dashboard.members_explanation', ['name' => $name]) }}
        </x-dashboard-card>

        <x-dashboard-card :href="route('realms.mods', $uid)" color="bg-purple-100 text-purple-700" icon="fas-graduation-cap">
            <x-slot:headline>
                {{ __('realms.dashboard.mods_headline', ['name' => $name]) }}
            </x-slot:headline>
            {{ __('realms.dashboard.mods_explanation', ['name' => $name]) }}
        </x-dashboard-card>

        <x-dashboard-card :href="route('realms.admins', $uid)" color="bg-cyan-100 text-cyan-700" icon="fas-hat-wizard">
            <x-slot:headline>
                {{ __('realms.dashboard.admin_headline', ['name' => $name]) }}
            </x-slot:headline>
            {{ __('realms.dashboard.admin_explanation', ['name' => $name]) }}
        </x-dashboard-card>

        <x-dashboard-card :href="route('realms.edit', $uid)" color="bg-sky-100 text-sky-700" icon="fas-wand-magic-sparkles"
                          :disabled="auth()->user()->cannot('edit', $community)">
            <x-slot:headline>
                {{ __('realms.dashboard.realms_edit_headline', ['name' => $name]) }}
            </x-slot:headline>
            {{ __('realms.dashboard.realms_edit_explanation', ['name' => $name]) }}
        </x-dashboard-card>

        <x-dashboard-card :href="route('realms.groups', $uid)" color="bg-blue-100 text-blue-700" icon="fas-user-tag"
                          :disabled="auth()->user()->cannot('viewAny', [\App\Ldap\Group::class, $community])">
            <x-slot:headline>
                {{ __('realms.dashboard.groups_headline', ['name' => $name]) }}
            </x-slot:headline>
            {{ __('realms.dashboard.groups_explanation', ['name' => $name]) }}
        </x-dashboard-card>

        <x-dashboard-card :href="route('realms.domains', $uid)" color="bg-indigo-100 text-indigo-700" icon="fab-internet-explorer"
                          :disabled="auth()->user()->cannot('viewAny', [\App\Ldap\Domain::class, $community])">
            <x-slot:headline>
                {{ __('realms.dashboard.domains_headline', ['name' => $name]) }}
            </x-slot:headline>
            {{ __('realms.dashboard.domains_explanation', ['name' => $name]) }}
        </x-dashboard-card>

    </div>
</div>
