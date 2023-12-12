<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('realms.groups_headline') }}</h1>
            <p class="mt-2 text-sm text-gray-700">
                {{  __('realms.groups_explanation') }}
            </p>
        </div>
    </div>

    <x-input.group :label="__('Realm Name')" wire:model="realm_uid" disabled/>
    <x-input.group :label="__('Short Groupname')" wire:model="cn"/>
    <x-input.group :label="__('Full Groupname')" wire:model="name"/>
    <x-slot:abort_route>
        {{ route('realms.groups', ['uid' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
