<div class="max-w-7xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('profile.export_memberships_title') }}</flux:heading>
        <flux:text class="text-base">{{ $name }}</flux:text>
    </div>

    <form wire:submit="export" class="space-y-6">
        @if(count($memberships) > 0)
            <div class="flex gap-2">
                <flux:button size="sm" icon="list-check" wire:click="selectAll">{{ __('profile.export_memberships_select_all') }}</flux:button>
                <flux:button size="sm" icon="list-x" wire:click="deselectAll">{{ __('profile.export_memberships_deselect_all') }}</flux:button>
            </div>
            <flux:checkbox.group wire:model="selected">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-0"><span class="sr-only">{{ __('profile.export_memberships_title') }}</span></flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'role'" :direction="$sortDirection" wire:click="sortBy('role')">{{ __('profile.role') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'committee'" :direction="$sortDirection" wire:click="sortBy('committee')">{{ __('profile.committee') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'from'" :direction="$sortDirection" wire:click="sortBy('from')">{{ __('profile.from') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'until'" :direction="$sortDirection" wire:click="sortBy('until')">{{ __('profile.until') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($memberships as $row)
                            <flux:table.row wire:key="membership-{{ $row['id'] }}">
                                <flux:table.cell>
                                    <flux:checkbox
                                        value="{{ $row['id'] }}"
                                        :aria-label="$row['role']->getFirstAttribute('description').' – '.$row['role']->committee()->getFirstAttribute('description')"
                                    />
                                </flux:table.cell>
                                <flux:table.cell>{{ $row['role']->getFirstAttribute('description') }}</flux:table.cell>
                                <flux:table.cell>{{ $row['role']->committee()->getFirstAttribute('description') }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ \Carbon\Carbon::parse($row['from'])->format('Y-m-d') }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">
                                    @if ($row['until'] != '')
                                        {{ \Carbon\Carbon::parse($row['until'])->format('Y-m-d') }}
                                    @else
                                        {{ __('profile.today') }}
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:checkbox.group>
            <flux:error name="selected" />

            <flux:fieldset>
                <legend class="flex items-center gap-4">
                    {{ __('profile.cover_letter_heading') }}
                    <flux:switch wire:model.live="withCoverLetter" class="ml-auto" :title="__('profile.cover_letter_toggle')" :aria-label="__('profile.cover_letter_toggle')" />
                </legend>

                @if($withCoverLetter)
                    <div class="p-4 space-y-6">
                        @unless($hasContact)
                            <flux:callout variant="warning" icon="circle-alert" :heading="__('profile.cover_letter_no_contact')" />
                        @endunless
                        <div class="grid gap-6 md:grid-cols-2 items-start">
                            <flux:fieldset>
                                <legend class="px-1">{{ __('profile.cover_letter_recipient') }}</legend>
                                <div class="space-y-4 p-4">
                                    <flux:input wire:model="recipient.organisation" :label="__('profile.cover_letter_recipient_organisation')" />
                                    <flux:input wire:model="recipient.name" :label="__('profile.cover_letter_recipient_name')" />
                                    <div class="grid grid-cols-[1fr_8rem] gap-4">
                                        <flux:input wire:model="recipient.street" :label="__('realms.contact_street')" />
                                        <flux:input wire:model="recipient.house_number" :label="__('realms.contact_house_number')" />
                                    </div>
                                    <div class="grid grid-cols-[8rem_1fr] gap-4">
                                        <flux:input wire:model="recipient.postal_code" :label="__('realms.contact_postal_code')" />
                                        <flux:input wire:model="recipient.city" :label="__('realms.contact_city')" />
                                    </div>
                                    <flux:input wire:model="recipient.country" :label="__('profile.cover_letter_recipient_country')" />
                                </div>
                            </flux:fieldset>
                            <flux:fieldset>
                                <legend class="px-1">{{ __('profile.cover_letter_sender') }}</legend>
                                <div class="space-y-4 p-4">
                                    <flux:input wire:model="senderUnit" :label="__('profile.cover_letter_sender_unit')" :placeholder="__('profile.cover_letter_sender_unit_placeholder')" />
                                    <flux:input type="email" wire:model="senderEmail" :label="__('profile.cover_letter_sender_email')" />
                                    <flux:input wire:model="signerFunction" :label="__('profile.cover_letter_signer_function')" :description="__('profile.cover_letter_signer_function_hint', ['name' => auth()->user()->full_name])" />
                                </div>
                            </flux:fieldset>
                        </div>
                        <flux:input wire:model="subject" :label="__('profile.cover_letter_subject')" />
                        <flux:editor wire:model="letterBody" :label="__('profile.cover_letter_body')" toolbar="bold italic underline | bullet ordered" />
                    </div>
                @endif
            </flux:fieldset>

            <div class="flex justify-end gap-2">
                <flux:button icon="ban" wire:navigate :href="$backUrl">{{ __('common.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="file-text">{{ __('profile.export_as_pdf') }}</flux:button>
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" inline heading="{{ __('profile.no_memberships_found') }}">
                <x-slot name="actions">
                    <flux:button icon="arrow-left" wire:navigate :href="$backUrl">{{ __('common.back') }}</flux:button>
                </x-slot>
            </flux:callout>
        @endif
    </form>
</div>
