<div class="flex flex-col sm:flex-row gap-6">
    <div class="flex-1 space-y-4">
        <flux:heading size="xl">{{ __('committees.list.headline') }}</flux:heading>
        <flux:text class="text-base">{{ __('committees.list.explain_text') }}</flux:text>
    </div>
    <div>
        @can('create', [\App\Ldap\Committee::class, $community])
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="route('committees.new', ['realm' => $realm])">
                {{ __('committees.new_button') }}
            </flux:button>
        @endcan
    </div>
</div>