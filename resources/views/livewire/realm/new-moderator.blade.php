<x-livewire-form>
    <x-slot:title>
        {{ __('realms.new_member') }}
    </x-slot:title>

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
