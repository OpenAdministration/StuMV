<div>
    <x-livewire-form class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __($mappingId ? 'group_mailman_lists.edit_title' : 'group_mailman_lists.new_title') }}</flux:heading>
            <flux:text class="text-base">{{ __('group_mailman_lists.explanation') }}</flux:text>
        </div>

        <flux:field>
            <flux:label>{{ __('group_mailman_lists.mailman_list_id') }}</flux:label>
            <flux:description>{{ __('group_mailman_lists.mailman_list_id_description') }}</flux:description>
            <flux:input wire:model="mailman_list_id" placeholder="newsletter.lists.example.org" />
            <flux:error name="mailman_list_id" />
        </flux:field>

        @foreach(['member', 'moderator'] as $role)
            <flux:field>
                <flux:label>{{ __('group_mailman_lists.role_'.$role) }}</flux:label>
                <flux:description>{{ __('group_mailman_lists.role_'.$role.'_description') }}</flux:description>
                <flux:pillbox multiple searchable wire:model="{{ $role }}_groups" :placeholder="__('group_mailman_lists.select_groups')">
                    @foreach($groups as $group)
                        <flux:pillbox.option value="{{ $group->getFirstAttribute('cn') }}">{{ $group->getFirstAttribute('cn') }} ({{ $group->getFirstAttribute('description') }})</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
                <flux:error name="{{ $role }}_groups" />
            </flux:field>
        @endforeach

        <x-slot:abort_route>
            {{ route('realms.group-mailman-lists', ['realm' => $uid]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
