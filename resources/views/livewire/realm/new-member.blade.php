<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('realms.members_new_heading') }}</h1>
            <p class="mt-2 text-sm text-gray-700">{{ __('realms.members_new_explanation') }}</p>
        </div>
    </div>

    <flux:field>
        <flux:label>{{ __('realms.new_admin_label') }}</flux:label>
        <flux:select
            variant="listbox"
            searchable
            placeholder="{{ __('realms.select_user') }}"
            wire:model="dn"
        >
            @foreach($selectable_users as $user)
                <flux:select.option value="{{ $user->getDn() }}">{{ $user->cn[0] }} ({{ $user->uid[0] }})</flux:select.option>
            @endforeach
        </flux:select>
    </flux:field>

    <x-slot:abort_route>
        {{ route('realms.admins', ['uid' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
