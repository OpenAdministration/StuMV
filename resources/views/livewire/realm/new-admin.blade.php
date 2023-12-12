<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('realms.new_admin_headline', ['name' => $community->getFirstAttribute('description'), 'uid' => $realm_uid]) }}</h1>
            <p class="mt-2 text-sm text-gray-700">{{ __('realms.new_admin_explanation') }}</p>
        </div>
    </div>

    <x-select wire:model.live="dn" class="mt-2">
        <x-slot:label>{{ __('realms.new_admin_label') }}</x-slot:label>
        @foreach($selectable_users as $user)
            <option value="{{ $user->getDn() }}">{{ $user->cn[0] }} ({{ $user->uid[0] }})</option>
        @endforeach
    </x-select>
    <x-slot:abort_route>
        {{ route('realms.admins', ['uid' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
