<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('New Role') }}</h1>
        </div>
    </div>
    <x-input.group :label="__('Short Rolename')" wire:model.live="cn">
        <x-slot:help>{{ __('roles.new_hint_shortname') }}</x-slot:help>
    </x-input.group>
    <x-input.group :label="__('Full Rolename')" wire:model="description">
        <x-slot:help>{{ __('roles.new_hint_longname') }}</x-slot:help>
    </x-input.group>
    <x-slot:abort_route>
        {{ route('committees.roles', ['uid' => $uid, 'ou' => $ou]) }}
    </x-slot:abort_route>
</x-livewire-form>
