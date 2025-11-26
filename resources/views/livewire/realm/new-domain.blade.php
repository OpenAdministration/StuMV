<x-livewire-form class="space-y-8">
    <div>
        <flux:heading size="xl" class="mb-4">{{ __('realms.domains_edit_headline') }}</flux:heading>
        <flux:text class="text-base">{{  __('realms.domains_edit_explanation') }}</flux:text>
    </div>

    <flux:field>
        <flux:label>{{ __('Realm Name') }}</flux:label>
        <flux:input wire:model="uid" disabled />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Domain FQDN') }}</flux:label>
        <flux:input wire:model="dc" />
    </flux:field>

    <x-slot:abort_route>
        {{ route('realms.domains', ['uid' => $uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
