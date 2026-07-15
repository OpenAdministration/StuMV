<div class="flex-col">
    <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('superadmins.list_title') }}</flux:heading>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="user-plus"
                wire:navigate
                :href="route('superadmins.add')"
            >
                {{ __('superadmins.new_title') }}
            </flux:button>
        </div>
    </div>

    <flux:field class="mb-8">
        <flux:label>{{ __('superadmins.search_placeholder') }}</flux:label>
        <flux:input icon="search" clearable wire:model.live="search" />
    </flux:field>

    <div class="pb-8">
        @if(count($superadmins) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-[55px]"></flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'cn'" :direction="$sortDirection" wire:click="sortBy('cn')">{{ __('Name') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'uid'" :direction="$sortDirection" wire:click="sortBy('uid')">{{ __('common.username') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($superadmins as $superadmin)
                    <flux:table.row>
                        <flux:table.cell>
                            @php
                                $jpegPhoto = $superadmin->jpegPhoto[0] ?? null;
                                if ($jpegPhoto) {
                                    $jpegPhoto = 'data:image/jpeg;base64,' . $jpegPhoto;
                                }
                            @endphp
                            <flux:avatar
                                src="{{ $jpegPhoto }}"
                                name="{{ $superadmin->cn[0] }}"
                            />
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link
                                wire:navigate
                                href="{{ route('profile', ['username' => $superadmin->uid[0]]) }}"
                            >
                                {{ $superadmin->cn[0] }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $superadmin->uid[0] }}</flux:table.cell>
                        <flux:table.cell class="flex justify-end gap-2">
                            <flux:dropdown>
                                <flux:button size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item
                                        variant="danger"
                                        icon="user-minus"
                                        wire:click="deletePrepare('{{ $superadmin->uid[0] }}')"
                                    >
                                        {{ __('common.delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="pagination">
                <flux:pagination :paginator="$superadmins" />
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('superadmins.no_admins_found') }}" />
        @endif
    </div>

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('realms.delete_admin_title', ['name' => $deleteAdminName]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.delete_admin_warning', ['name' => $deleteAdminName]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('common.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
