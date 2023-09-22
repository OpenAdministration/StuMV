<div>
    <h1 class="text-2xl font-semibold text-gray-900">Profile</h1>
    <x-livewire-form :abort_route="null" wire:submit="save">
        <div class="mt-6 sm:mt-5">
            <x-input.group disabled label="Username" wire:model.live="uid">
                <x-slot:help>Kann nicht verändert werden</x-slot:help>
            </x-input.group>
            <x-input.group label="Name" wire:model.live="fullName"/>
            <x-input.group type="email" label="E-Mail" wire:model.live="email"/>
        </div>
    </x-livewire-form>
</div>
