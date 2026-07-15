<div>
    <x-livewire-form class="max-w-6xl mx-auto w-full">
        <flux:heading size="xl" class="mb-6">{{ __('committees.new_button') }}</flux:heading>
        <flux:field class="flex flex-col mb-6">
            <flux:label>{{ __('committees.parent_committee') }}</flux:label>
            <flux:select
                variant="listbox"
                searchable
                placeholder="{{ __('committees.select_committee') }}"
                wire:model="parent_dn"
            >
                <flux:select.option value="">{{ __('committees.no_parent_committee') }}</flux:select.option>
                @foreach($select_parents as $key => $select_parent)
                    <flux:select.option value="{{ $key }}">{{ $select_parent['description'] }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
        <flux:field class="flex flex-col mb-6">
            <flux:label>{{ __('committees.short_name_label') }}</flux:label>
            <flux:description>{{ __('committees.new_hint_shortname') }}</flux:description>
            <flux:input type="text" wire:model.live="ou" required />
            <flux:error name="ou" />
        </flux:field>
        <flux:field class="flex flex-col mb-6">
            <flux:label>{{ __('committees.full_name_label') }}</flux:label>
            <flux:description>{{ __('committees.new_hint_longname') }}</flux:description>
            <flux:input type="text" wire:model.live="description" required />
            <flux:error name="description" />
        </flux:field>
        <flux:field class="flex flex-col">
            <flux:label>{{ __('committees.add_roles') }}</flux:label>
            <flux:description>{{ __('committees.new_hint_roles') }}</flux:description>
            <flux:pillbox wire:model="roles" multiple>
                @foreach($defaultRoles as $key => $r)
                    <flux:pillbox.option value="{{ $key }}">{{ $r['description'] }}</flux:pillbox.option>
                @endforeach
            </flux:pillbox>
            <flux:error name="roles" />
        </flux:field>
        <x-slot:abort_route>
            {{ route('committees.list', ['realm' => $realm_uid]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
