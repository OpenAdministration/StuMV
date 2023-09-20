<x-livewire-form>
    <x-input.group wire:model="uid">
        <x-slot:label>{{ __('realms.shortcode') }}</x-slot:label>
        <x-slot:help>Erforderlich</x-slot:help>
    </x-input.group>
    <x-input.group wire:model="name">
        <x-slot:label>{{ __('Name') }}</x-slot:label>
        <x-slot:help>Erforderlich</x-slot:help>
    </x-input.group>
    <x-slot:abort_route>{{ route('realms') }}</x-slot:abort_route>
</x-livewire-form>
