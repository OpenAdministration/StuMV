<x-livewire-form class="w-full space-y-8">
    <div>
        <flux:heading size="xl">{{  __('roles.edit_heading', ['name' => $cn]) }}</flux:heading>
    </div>

    <flux:field>
        <flux:label>{{ __('Short Rolename') }}</flux:label>
        <flux:input wire:model="cn" disabled />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Full Name') }}</flux:label>
        <flux:input wire:model="description" />
    </flux:field>
</x-livewire-form>
