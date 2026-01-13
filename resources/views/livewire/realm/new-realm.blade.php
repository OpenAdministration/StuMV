<div>
    <x-livewire-form class="space-y-8">
        <flux:field>
            <flux:label>{{ __('realms.shortcode') }}</flux:label>
            <flux:input wire:model="uid" required />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" required />
        </flux:field>
        
        <x-slot:abort_route>{{ route('realms.pick') }}</x-slot:abort_route>
    </x-livewire-form>
</div>
