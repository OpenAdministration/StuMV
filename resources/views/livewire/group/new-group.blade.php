<div>
    <x-livewire-form class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('realms.groups_headline') }}</flux:heading>
            <flux:text class="text-base">{{  __('realms.groups_explanation') }}</flux:text>
        </div>

        <flux:field>
            <flux:label>{{ __('groups.short_name_label') }}</flux:label>
            <flux:input wire:model="cn" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('groups.description_label') }}</flux:label>
            <flux:input wire:model="name" />
        </flux:field>

        <x-slot:abort_route>
            {{ route('realms.groups', ['realm' => $realm_uid]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
