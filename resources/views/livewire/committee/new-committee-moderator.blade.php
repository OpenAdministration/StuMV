<div>
    <x-livewire-form>
        <div class="mb-6">
            <flux:heading size="xl" class="mb-4">{{ __('committees.new_mod_headline', ['name' => $committee->getFirstAttribute('description')]) }}</flux:heading>
            <flux:text class="text-base">{{ __('committees.new_mod_explanation') }}</flux:text>
        </div>

        <flux:field>
            <flux:label>{{ __('realms.new_admin_label') }}</flux:label>
            <flux:pillbox
                multiple
                searchable
                placeholder="{{ __('realms.select_user') }}"
                wire:model="dn"
            >
                @foreach($selectable_users as $user)
                    <flux:pillbox.option value="{{ $user->getDn() }}">{{ $user->cn[0] }} ({{ $user->uid[0] }})</flux:pillbox.option>
                @endforeach
            </flux:pillbox>
        </flux:field>
        <x-slot:abort_route>
            {{ route('committees.moderators', ['uid' => $realm_uid, 'ou' => $ou]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
