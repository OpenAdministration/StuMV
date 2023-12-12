<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{  __('committees.edit_headline', ['name' => $description]) }}</h1>
            <p class="mt-2 text-sm text-gray-700">
                {{  __('committees.edit_explanation', ['name' => $description]) }}
            </p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
            <x-button.link-primary href="{{ route('realms.new') }}" icon-leading="fas-plus" :disabled="auth()->user()->cannot('create', \App\Ldap\Community::class)">
                {{ __('New Realm') }}
            </x-button.link-primary>
        </div>
    </div>


    <x-input.group :label="__('Realm Name')" wire:model.live="realm_uid" disabled/>
    <x-input.group :label="__('Parent Committee')" wire:model.live="parent_ou" :placeholder="__('None')" disabled/>
    <x-input.group :label="__('Short Committee Name')" wire:model.live="ou" disabled/>
    <x-input.group :label="__('Full Committee Name')" wire:model.live="description"/>
    <x-slot:abort_route>
        {{ route('committees.list', ['uid' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
