<div class="max-w-4xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('tools.invite_user_headline') }}</flux:heading>
        <flux:text class="text-base">{{ __('tools.invite_user_explanation') }}</flux:text>
    </div>

    <x-livewire-form :abort_route="route('tools.dashboard', ['realm' => $uid])" class="mb-12">
        <div class="grid sm:grid-cols-2 gap-6 mb-6">
            <flux:field>
                <flux:label>{{ __('common.email') }}</flux:label>
                <flux:input wire:model="email" type="email" autofocus />
                <flux:error name="email" />
            </flux:field>

            <flux:field class="col-span-full">
                <flux:label>{{ __('tools.roles_to_grant') }}</flux:label>
                <flux:pillbox
                    multiple
                    searchable
                    placeholder="{{ __('tools.roles_to_grant_placeholder') }}"
                    wire:model="roleSelections"
                >
                    @foreach($roleOptions as $value => $label)
                        <flux:pillbox.option value="{{ $value }}">{{ $label }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
                <flux:error name="roleSelections" />
                <flux:description>{{ __('tools.roles_to_grant_description') }}</flux:description>
            </flux:field>
        </div>

        <x-slot:submit_label>{{ __('tools.invite_user_send_button') }}</x-slot:submit_label>
        <x-slot:submit_icon>send</x-slot:submit_icon>
    </x-livewire-form>

    <div class="space-y-4">
        <flux:heading size="lg">{{ __('tools.pending_invitations_headline') }}</flux:heading>

        @if($pending->isEmpty())
            <flux:callout variant="secondary" icon="mail" heading="{{ __('tools.no_pending_invitations') }}" />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('common.email') }}</flux:table.column>
                    <flux:table.column>{{ __('tools.roles') }}</flux:table.column>
                    <flux:table.column>{{ __('tools.invitation_expires') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($pending as $invitation)
                        <flux:table.row>
                            <flux:table.cell>{{ $invitation->email }}</flux:table.cell>
                            <flux:table.cell>{{ implode(', ', $this->roleLabelsFor($invitation)) ?: '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $invitation->expires_at->translatedFormat('d.m.Y') }}</flux:table.cell>
                            <flux:table.cell class="flex justify-end">
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash-2"
                                    wire:click="revoke({{ $invitation->id }})"
                                >
                                    {{ __('tools.invitation_revoke') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>
