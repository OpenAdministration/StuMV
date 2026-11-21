<div class="max-w-7xl mx-auto w-full">
    <x-livewire-form class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __($mappingId ? 'group_mailman_lists.edit_title' : 'group_mailman_lists.new_title') }}</flux:heading>
            <flux:text class="text-base">{{ __($this->canCreateList ? 'group_mailman_lists.explanation_with_creation' : 'group_mailman_lists.explanation') }}</flux:text>
        </div>

        @if($this->canCreateList)
            <flux:radio.group wire:model.live="list_source" variant="segmented">
                <flux:radio value="existing" :label="__('group_mailman_lists.use_existing_list')" />
                <flux:radio value="new" :label="__('group_mailman_lists.create_new_list')" />
            </flux:radio.group>
        @endif

        @if($list_source === 'new' && $this->canCreateList)
            @if($this->domains === null)
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('group_mailman_lists.domains_fetch_failed')" />
            @else
                <flux:field>
                    <flux:label>{{ __('group_mailman_lists.list_address') }}</flux:label>
                    <flux:input.group>
                        <flux:input wire:model="list_name" placeholder="newsletter" :aria-label="__('group_mailman_lists.list_name')" />
                        <flux:input.group.affix class="border-s-0 border-e-zinc-200 dark:border-e-white/10">@</flux:input.group.affix>
                        <flux:select variant="listbox" searchable wire:model="mail_host" :placeholder="__('group_mailman_lists.select_mail_host')" :aria-label="__('group_mailman_lists.mail_host')">
                            @foreach($this->domains as $domain)
                                <flux:select.option value="{{ $domain }}">{{ $domain }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:input.group>
                    <flux:error name="list_name" />
                    <flux:error name="mail_host" />
                </flux:field>
            @endif
        @else
            <flux:field>
                <flux:label>{{ __('group_mailman_lists.mailman_list_id') }}</flux:label>
                <flux:description>{{ __('group_mailman_lists.mailman_list_id_description') }}</flux:description>
                <flux:input wire:model="mailman_list_id" placeholder="newsletter.lists.example.org" />
                <flux:error name="mailman_list_id" />
            </flux:field>
        @endif

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
