<div class="max-w-7xl mx-auto w-full flex-col">
    <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('realms.membership_export.headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.membership_export.explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="route('realms.membership-export.new', ['realm' => $uid])"
            >
                {{ __('realms.membership_export.new') }}
            </flux:button>
        </div>
    </div>

    @if($templates->isNotEmpty())
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('realms.membership_export.name') }}</flux:table.column>
                <flux:table.column>{{ __('realms.membership_export.letter_from') }}</flux:table.column>
                <flux:table.column>{{ __('realms.membership_export.date_format') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($templates as $template)
                    <flux:table.row wire:key="template-{{ $template->id }}">
                        <flux:table.cell>
                            <flux:link wire:navigate :href="route('realms.membership-export.edit', ['realm' => $uid, 'template' => $template->id])">
                                {{ $template->name }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $template->with_letter ? $template->sender->label() : __('realms.membership_export.without_letter') }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $template->date_format->label() }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end items-center gap-2">
                                <flux:dropdown>
                                    <flux:button size="sm" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item
                                            icon="pencil"
                                            wire:navigate
                                            :href="route('realms.membership-export.edit', ['realm' => $uid, 'template' => $template->id])"
                                        >
                                            {{ __('common.edit') }}
                                        </flux:menu.item>
                                        <flux:menu.item variant="danger" icon="trash-2" wire:click="deletePrepare({{ $template->id }})">
                                            {{ __('realms.membership_export.delete') }}
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
        <flux:callout variant="warning" icon="circle-alert" :heading="__('realms.membership_export.no_templates')" />
    @endif

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('realms.membership_export.delete_title', ['name' => $deleteName]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.membership_export.delete_warning', ['name' => $deleteName]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-4">
                    <flux:button wire:click="closeDelete()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="danger" type="submit">{{ __('realms.membership_export.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
