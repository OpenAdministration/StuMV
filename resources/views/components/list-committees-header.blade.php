<div class="flex flex-col sm:flex-row gap-6">
    <div class="flex-1 space-y-4">
        <flux:heading size="xl">{{ __('committees.list.headline', ['name' => $community->getFirstAttribute('description')]) }}</flux:heading>
        <flux:text class="text-base">{{ __('committees.list.explain_text') }}</flux:text>
    </div>
    <div>
        <flux:button
            variant="primary"
            icon="plus"
            wire:navigate
            :href="auth()->user()->can('create', [\App\Ldap\Committee::class, $community]) ? route('committees.new', ['realm' => $realm]) : null" :disabled="auth()->user()->cannot('create', [\App\Ldap\Committee::class, $community])">
            {{ __('New Committee') }}
        </flux:button>
    </div>
</div>