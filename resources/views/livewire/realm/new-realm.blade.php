<div>
    <x-livewire-form class="space-y-8">
        <flux:heading size="xl" class="mb-6">{{ __('realms.new_realm_title') }}</flux:heading>

        <flux:field>
            <flux:label>{{ __('realms.shortcode') }}</flux:label>
            <flux:input wire:model="uid" required />
            <flux:error name="uid" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" required />
            <flux:error name="name" />
        </flux:field>

        <x-slot:abort_route>{{ route('realms.pick') }}</x-slot:abort_route>
    </x-livewire-form>
</div>
