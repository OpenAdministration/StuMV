<div>
    <x-livewire-form>
        <div class="mb-8">
            <flux:heading size="xl">{{ __('superadmins.new_title') }}</flux:heading>
        </div>

        <flux:field>
            <flux:label>{{ __('superadmins.new_superadmins_label') }}</flux:label>
            <flux:pillbox
                multiple
                searchable
                placeholder="{{ __('realms.select_user') }}"
                wire:model="usersToAdd"
            >
                @foreach($users as $user)
                    <flux:pillbox.option value="{{ $user->getDn() }}">{{ $user->cn[0] }} ({{ $user->uid[0] }})</flux:pillbox.option>
                @endforeach
            </flux:pillbox>
        </flux:field>

        <x-slot:abort_route>
            {{ route('superadmins.list') }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
