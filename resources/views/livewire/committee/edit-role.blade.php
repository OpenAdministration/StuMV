<div>
    <x-livewire-form class="max-w-6xl mx-auto w-full space-y-8">
        <div>
            <flux:heading size="xl">{{  __('roles.edit_heading', ['name' => $cn]) }}</flux:heading>
        </div>

        <flux:field>
            <flux:label>{{ __('roles.short_name_label') }}</flux:label>
            <flux:input wire:model="cn" disabled />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('roles.full_name_field') }}</flux:label>
            <flux:input wire:model="description" />
        </flux:field>
    </x-livewire-form>
</div>
