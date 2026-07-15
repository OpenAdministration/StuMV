<div>
    <x-livewire-form class="max-w-6xl mx-auto w-full">
        <div class="mb-6">
            <flux:heading size="xl" class="mb-4">{{ __('realms.edit_headline', ['name' => $name]) }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.edit_explanation', ['name' => $name]) }}</flux:text>
        </div>
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" />
        </flux:field>
        <x-slot:abort_route>
            {{ url()->previous() }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
