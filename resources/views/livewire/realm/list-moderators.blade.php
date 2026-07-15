<div class="flex-col space-y-8" wire:init="loadModerators">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('realms.mods_heading') }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.mods_explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="user-plus"
                wire:navigate
                :href="auth()->user()->can('add_moderator', $community) ? route('realms.mods.new', ['realm' => $community_name]) : null"
                :disabled="auth()->user()->cannot('add_moderator', $community)"
            >
                {{ __('common.add_moderators') }}
            </flux:button>
        </div>
    </div>

    <flux:field>
        <flux:label>{{ __('realms.search_moderators') }}</flux:label>
        <flux:input icon="search" clearable wire:model.live="search" />
    </flux:field>

    <div wire:loading.flex wire:target="loadModerators" class="flex justify-center py-16">
        <flux:icon.loading />
    </div>

    <div wire:loading.remove wire:target="loadModerators">
        @if(count($realm_members) > 0)
            <flux:table>
            <flux:table.columns>
                <flux:table.column class="w-[55px]"></flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'cn'" :direction="$sortDirection" wire:click="sortBy('cn')">{{ __('Name') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
            @foreach($realm_members as $realm_member)
                <flux:table.row>
                    <flux:table.cell>
                        @php
                            $jpegPhoto = $realm_member->jpegPhoto[0] ?? null;
                            if ($jpegPhoto) {
                                $jpegPhoto = 'data:image/jpeg;base64,' . $jpegPhoto;
                            }
                        @endphp
                        <flux:avatar
                            src="{{ $jpegPhoto }}"
                            name="{{ $realm_member->cn[0] }}"
                        />
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($isAdmin)
                            <flux:link
                                wire:navigate
                                :href="route('profile', ['username' => $realm_member->uid[0]])"
                            >
                                {{ $realm_member->cn[0] }}
                            </flux:link>
                        @else
                            {{ $realm_member->cn[0] }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end items-center gap-2">
                            <flux:dropdown>
                                <flux:button size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item
                                        variant="danger"
                                        icon="user-minus"
                                        :disabled="!$canRemoveModerator"
                                        wire:click="deletePrepare('{{ $realm_member->uid[0] }}')"
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

            <div class="pagination">
                <flux:pagination :paginator="$realm_members" />
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('realms.no_moderators_found') }}" />
        @endif
    </div>

    <div class="block h-[1px]"></div>

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('realms.delete_mod_title', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.delete_mod_warning', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('common.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
