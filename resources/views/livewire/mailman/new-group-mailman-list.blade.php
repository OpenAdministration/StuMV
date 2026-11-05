<div>
    <x-livewire-form class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('group_mailman_lists.new_title') }}</flux:heading>
            <flux:text class="text-base">{{ __('group_mailman_lists.explanation') }}</flux:text>
        </div>

        <flux:field>
            <flux:label>{{ __('group_mailman_lists.group') }}</flux:label>
            <flux:select variant="listbox" searchable wire:model="group_cn">
                @foreach($groups as $group)
                    <flux:select.option value="{{ $group->getFirstAttribute('cn') }}">{{ $group->getFirstAttribute('cn') }} ({{ $group->getFirstAttribute('description') }})</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="group_cn" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('group_mailman_lists.mailman_list_id') }}</flux:label>
            <flux:description>{{ __('group_mailman_lists.mailman_list_id_description') }}</flux:description>
            <flux:input wire:model="mailman_list_id" placeholder="newsletter.lists.example.org" />
            <flux:error name="mailman_list_id" />
        </flux:field>

        <x-slot:abort_route>
            {{ route('realms.group-mailman-lists', ['realm' => $uid]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
