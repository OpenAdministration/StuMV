<div class="max-w-4xl mx-auto w-full">
    <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('tools.pending_invitations_headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('tools.pending_invitations_explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                href="{{ route('tools.invite-user', ['realm' => $uid]) }}"
            >
                {{ __('tools.new_invitation_button') }}
            </flux:button>
        </div>
    </div>

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
