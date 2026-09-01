<div class="max-w-2xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('tools.invite_user_headline') }}</flux:heading>
        <flux:text class="text-base">{{ __('tools.invite_user_explanation') }}</flux:text>
    </div>

    <x-livewire-form :abort_route="route('tools.invitations', ['realm' => $uid])" class="space-y-8">
        <flux:field>
            <flux:label>{{ __('common.email') }}</flux:label>
            <flux:input wire:model="email" type="email" autofocus />
            <flux:error name="email" />
        </flux:field>

        <div class="space-y-4">
            <div>
                <flux:label>{{ __('tools.roles_to_grant') }}</flux:label>
                <flux:description>{{ __('tools.roles_to_grant_description') }}</flux:description>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 sm:items-end">
                <flux:field class="flex-1">
                    <flux:label>{{ __('groups.field_committee') }}</flux:label>
                    <flux:select
                        variant="listbox"
                        searchable
                        wire:model.live="selected_committee_dn"
                        placeholder="{{ __('tools.select_committee_placeholder') }}"
                    >
                        @foreach($committees as $committee)
                            <flux:select.option value="{{ $committee->getDn() }}">{{ $committee->getFirstAttribute('description') }} ({{ $committee->getFirstAttribute('ou') }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field class="flex-1">
                    <flux:label>{{ __('groups.field_role') }}</flux:label>
                    <flux:select
                        variant="listbox"
                        searchable
                        wire:model.live="selected_role_dn"
                        placeholder="{{ __('tools.select_role_placeholder') }}"
                        :disabled="empty($selected_committee_dn)"
                    >
                        @foreach($roles as $role)
                            <flux:select.option value="{{ $role->getDn() }}">{{ $role->getFirstAttribute('description') }} ({{ $role->getFirstAttribute('cn') }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="selected_role_dn" />
                </flux:field>

                {{-- :disabled is computed server-side at render time, so the role
                     select above must be wire:model.live too (like the committee
                     select) - a deferred model would never round-trip on its own,
                     leaving this button stuck disabled from the last render. --}}
                <flux:button
                    icon="plus"
                    wire:click="addRoleSelection"
                    :disabled="empty($selected_committee_dn) || empty($selected_role_dn)"
                >
                    {{ __('tools.add_role_selection_button') }}
                </flux:button>
            </div>

            @if(count($queuedRoleSelections) > 0)
                <ul class="space-y-2">
                    @foreach($queuedRoleSelections as $key => $selection)
                        <li class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                            <flux:text>{{ $selection['label'] }}</flux:text>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash-2"
                                wire:click="removeRoleSelection('{{ $key }}')"
                            />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <x-slot:submit_label>{{ __('tools.invite_user_send_button') }}</x-slot:submit_label>
        <x-slot:submit_icon>send</x-slot:submit_icon>
    </x-livewire-form>
</div>
