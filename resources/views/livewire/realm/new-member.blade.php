<div>
    <x-livewire-form class="space-y-8">
        <div class="space-y-4">
            <flux:heading size="xl">{{ __('realms.members_new_heading') }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.members_new_explanation') }}</flux:text>
        </div>

        <flux:field>
            <flux:label>{{ __('realms.new_admin_label') }}</flux:label>
            <flux:pillbox
                multiple
                searchable
                placeholder="{{ __('realms.select_user') }}"
                wire:model="selectedUsers"
            >
                @foreach($selectable_users as $user)
                    <flux:pillbox.option value="{{ $user->getDn() }}">{{ $user->cn[0] }} ({{ $user->uid[0] }})</flux:pillbox.option>
                @endforeach
            </flux:pillbox>
        </flux:field>

        <x-slot:abort_route>
            {{ route('realms.members', ['uid' => $realm_uid]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
