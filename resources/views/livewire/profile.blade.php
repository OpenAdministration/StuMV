<div class="max-w-full! flex flex-col -m-6! sm:-m-8!">
    <x-navbar-profile :username="$currentUsername" />

    <div class="flex-1 p-6 sm:p-8">
        <div class="max-w-6xl mx-auto space-y-6">
            <x-livewire-form :abort_route="null" wire:submit="save">
                <div class="grid lg:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>{{ __('Username') }}</flux:label>
                        <flux:input wire:model="uid" disabled />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('E-Mail') }}</flux:label>
                        <flux:input wire:model="email" disabled />
                    </flux:field>
                </div>
                <div class="grid lg:grid-cols-2 gap-6 mt-6">
                    <flux:field>
                        <flux:label>{{ __('Vorname') }}</flux:label>
                        <flux:input wire:model="givenName" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Nachname') }}</flux:label>
                        <flux:input wire:model="sn" />
                    </flux:field>
                </div>
                <div class="grid lg:grid-cols-2 gap-6 mt-6">
                    <flux:field>
                        <flux:label>{{ __('Studiengang') }}</flux:label>
                        <flux:input wire:model="course" />
                    </flux:field>
                </div>
                <div class="grid lg:grid-cols-2 gap-6 mt-6">
                    <flux:field>
                        <flux:label>{{ __('Straße und Hausnummer') }}</flux:label>
                        <flux:input wire:model="street" />
                    </flux:field>
                    <div class="grid grid-cols-[1fr_2fr] gap-6">
                        <flux:field>
                            <flux:label>{{ __('Postleitzahl') }}</flux:label>
                            <flux:input wire:model="postalCode" />
                        </flux:field>
                        <flux:field>
                            <flux:label>{{ __('Ort') }}</flux:label>
                            <flux:input wire:model="city" />
                        </flux:field>
                    </div>
                </div>
                <div class="grid lg:grid-cols-2 gap-6 mt-6">
                    <flux:field>
                        <flux:label>{{ __('Telefon') }}</flux:label>
                        <flux:input wire:model="phone" />
                    </flux:field>
                </div>
                <x-slot:abort_route>
                    {{ url()->previous() }}
                </x-slot:abort_route>
            </x-livewire-form>
        </div>
    </div>
</div>
