<x-livewire-form class="w-full space-y-8">
    <div>
        <flux:heading size="xl" class="mb-4">{{  __('committees.edit_headline', ['name' => $description]) }}</flux:heading>
        <flux:text class="text-base">{{  __('committees.edit_explanation', ['name' => $description]) }}</flux:text>
    </div>

    <flux:field>
        <flux:label>{{ __('committees.parent_committee') }}</flux:label>
        <flux:input wire:model="parent_ou" disabled />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('committees.short_name_label') }}</flux:label>
        <flux:input wire:model="ou" disabled />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('committees.full_name_label') }}</flux:label>
        <flux:input wire:model="description" />
    </flux:field>

    <x-slot:abort_route>
        {{ route('committees.list', ['realm' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
