<x-livewire-form>
    <div class="mb-6">
        <flux:heading size="xl" class="mb-4">{{ __('realms.new_mod_headline', ['name' => $community->getFirstAttribute('description'), 'uid' => $realm_uid]) }}</flux:heading>
        <flux:text class="text-base">{{ __('realms.new_mod_explanation') }}</flux:text>
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
        {{ route('realms.mods', ['uid' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
