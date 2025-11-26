<x-livewire-form class="space-y-8">
    <div>
        <flux:heading size="xl" class="mb-4">{{ __('realms.groups_headline') }}</flux:heading>
        <flux:text class="text-base">{{  __('realms.groups_explanation') }}</flux:text>
    </div>

    <flux:field>
        <flux:label>{{ __('Realm Name') }}</flux:label>
        <flux:input wire:model="realm_uid" disabled />
    </flux:field>
    <flux:field>
        <flux:label>{{ __('Short Groupname') }}</flux:label>
        <flux:input wire:model="cn" />
    </flux:field>
    <flux:field>
        <flux:label>{{ __('Full Groupname') }}</flux:label>
        <flux:input wire:model="name" />
    </flux:field>
    
    <x-slot:abort_route>
        {{ route('realms.groups', ['uid' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
