<div>
    <x-livewire-form class="max-w-7xl mx-auto w-full">
        <div class="mb-6">
            <flux:heading size="xl">{{ $templateId ? $name : __('realms.membership_export.new') }}</flux:heading>
        </div>
        <div class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="name" :label="__('realms.membership_export.name')" :placeholder="__('realms.membership_export.name_placeholder')" />
                <flux:select wire:model="dateFormat" :label="__('realms.membership_export.date_format')">
                    @foreach($dateFormats as $format)
                        <flux:select.option :value="$format->value">{{ $format->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:switch wire:model.live="withLetter" :label="__('profile.cover_letter_toggle')" align="left" />

            @if($withLetter)
                <flux:radio.group wire:model.live="sender" :label="__('profile.cover_letter_sender')" variant="segmented" class="max-w-md">
                    @foreach($senders as $option)
                        <flux:radio :value="$option->value" :label="$option->label()" />
                    @endforeach
                </flux:radio.group>

                @if($sender === 'realm')
                    <flux:fieldset>
                        <legend>{{ __('profile.cover_letter_header') }}</legend>
                        <div class="grid gap-4 p-4 md:grid-cols-2">
                            <flux:input wire:model="headerTitle" :label="__('profile.cover_letter_header_title')" :placeholder="$senderName ?: $realmName" />
                            <flux:input wire:model="headerSubtitle" :label="__('profile.cover_letter_header_subtitle')" />
                        </div>
                    </flux:fieldset>
                @endif
                <div class="grid gap-6 md:grid-cols-2 items-start">
                    <flux:fieldset>
                        <legend>{{ __('profile.cover_letter_recipient') }}</legend>
                        <div class="space-y-4 p-4">
                            <flux:input wire:model="recipient.organisation" :label="__('profile.cover_letter_recipient_organisation')" />
                            <flux:input wire:model="recipient.unit" :label="__('profile.cover_letter_recipient_unit')" />
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
                        <legend>{{ __('profile.cover_letter_sender') }}</legend>
                        <div class="space-y-4 p-4">
                            @if($sender === 'realm')
                                <flux:input wire:model="senderName" :label="__('profile.cover_letter_sender_name')" :placeholder="$realmName" />
                                <flux:input wire:model="senderUnit" :label="__('profile.cover_letter_sender_unit')" :placeholder="__('profile.cover_letter_sender_unit_placeholder')" />
                                <div class="grid grid-cols-[1fr_8rem] gap-4">
                                    <flux:input wire:model="senderBuilding" :label="__('realms.contact_office_building')" :placeholder="$contactBuilding" />
                                    <flux:input wire:model="senderRoom" :label="__('realms.contact_office_room')" :placeholder="$contactRoom" />
                                </div>
                                <flux:input type="email" wire:model="senderEmail" :label="__('profile.cover_letter_sender_email')" :placeholder="$contactEmail" />
                                <flux:input type="tel" wire:model="senderPhone" :label="__('profile.cover_letter_sender_phone')" :placeholder="$contactPhone" />
                                <flux:input type="url" wire:model="senderWebsite" :label="__('profile.cover_letter_sender_website')" placeholder="https://" />
                            @else
                                <flux:text>{{ __('realms.membership_export.sender_user_hint') }}</flux:text>
                            @endif
                        </div>
                    </flux:fieldset>
                </div>
                <flux:fieldset>
                    <legend>{{ __('profile.cover_letter_content') }}</legend>
                    <div class="space-y-4 p-4">
                        <flux:input wire:model="subject" :label="__('profile.cover_letter_subject')" />
                        <flux:editor wire:model="letterBody" :label="__('profile.cover_letter_body')" toolbar="bold italic underline | bullet ordered" />
                        <div>
                            <flux:heading>{{ __('realms.membership_export.placeholders') }}</flux:heading>
                            <dl class="mt-2 grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
                                @foreach($placeholders as $placeholder => $label)
                                    <dt><code class="select-all">{{ $placeholder }}</code></dt>
                                    <dd class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                </flux:fieldset>
            @endif
        </div>
        <x-slot:abort_route>
            {{ route('realms.membership-export', ['realm' => $uid]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
