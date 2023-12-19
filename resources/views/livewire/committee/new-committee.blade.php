<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('New Committee') }}</h1>
        </div>
    </div>
    <x-select :label="__('Parent Committee')" wire:model.live="parent_dn">
        <option>{{ __('none') }}</option>
        @foreach($select_parents as $select_parent)
            <option value="{{ $select_parent->getDn() }}">{{ $select_parent->getFirstAttribute('description') }}</option>
        @endforeach
    </x-select>
    <x-input.group :label="__('Short Committee Name')" wire:model.live="ou">
        <x-slot:help>{{ __('committees.new_hint_shortname') }}</x-slot:help>
    </x-input.group>
    <x-input.group :label="__('Full Committee Name')" wire:model.live="description">
        <x-slot:help>{{ __('committees.new_hint_longname') }}</x-slot:help>
    </x-input.group>
    <x-slot:abort_route>
        {{ route('committees.list', ['uid' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
