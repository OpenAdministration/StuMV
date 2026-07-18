<div class="flex-col space-y-8" wire:init="loadModerators">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('committees.mods_heading', ['name' => $committee->getFirstAttribute('description')]) }}</flux:heading>
            <flux:text class="text-base">{{ __('committees.mods_explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="user-plus"
                wire:navigate
                :href="$isModerator ? route('committees.moderators.new', ['realm' => $realm_uid, 'ou' => $ou]) : null"
                :disabled="!$isModerator"
            >
                {{ __('common.add_moderators') }}
            </flux:button>
        </div>
    </div>

    <div wire:loading.flex wire:target="loadModerators" class="flex justify-center py-16">
        <flux:icon.loading />
    </div>

    <div wire:loading.remove wire:target="loadModerators">
        @if(count($committee_moderators) > 0)
            <flux:table>
            <flux:table.columns>
                <flux:table.column class="w-[55px]"></flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
            @foreach($committee_moderators as $moderator)
                <flux:table.row>
                    <flux:table.cell>
                        @php
                            $jpegPhoto = $moderator->jpegPhoto[0] ?? null;
                            if ($jpegPhoto) {
                                $jpegPhoto = 'data:image/jpeg;base64,' . $jpegPhoto;
                            }
                        @endphp
                        <flux:avatar
                            src="{{ $jpegPhoto }}"
                            name="{{ $moderator->cn[0] }}"
                        />
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $moderator->cn[0] }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end items-center gap-2">
                            <flux:dropdown>
                                <flux:button size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item
                                        variant="danger"
                                        icon="user-minus"
                                        :disabled="!$isModerator"
                                        wire:click="deletePrepare('{{ $moderator->uid[0] }}')"
                                    >
                                        {{ __('common.remove_moderator') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
            </flux:table.rows>
        </flux:table>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('committees.no_mods_found') }}" />
        @endif

    <div class="block h-[1px]"></div>

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('committees.delete_mod_title', ['name' => $deleteModeratorName]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('committees.delete_mod_warning', ['name' => $deleteModeratorName, 'username' => $deleteModeratorUsername]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('common.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
    </div>
</div>
