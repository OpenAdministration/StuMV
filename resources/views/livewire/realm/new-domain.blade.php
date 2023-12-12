<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('realms.domains_edit_explanation') }}</h1>
            <p class="mt-2 text-sm text-gray-700">
                {{  __('realms.domains_edit_explanation') }}
            </p>
        </div>
    </div>

    <x-input.group :label="__('Realm Name')" wire:model.live="uid" disabled/>
    <x-input.group :label="__('Domain FQDN')" wire:model.live="dc"/>
    <x-slot:abort_route>
        {{ route('realms.domains', ['uid' => $uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
