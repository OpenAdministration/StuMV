<div>
    <x-livewire-form class="max-w-6xl mx-auto w-full space-y-8">
        <div>
            <flux:heading size="xl">{{  __('groups.edit', ['name' => $cn]) }}</flux:heading>
        </div>

        <flux:field>
            <flux:label>{{ __('groups.short_name_label') }}</flux:label>
            <flux:input wire:model="cn" disabled />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('groups.description_label') }}</flux:label>
            <flux:input wire:model="name" />
        </flux:field>
    </x-livewire-form>
</div>
