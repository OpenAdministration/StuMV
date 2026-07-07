<div class="flex-col space-y-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('committees.roles_heading', ['name' => $committee->getFirstAttribute('description')]) }}</flux:heading>
            <flux:text class="text-base">{{ __('committees.roles_explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                :href="route('committees.roles.new', ['uid' => $uid, 'ou' => $ou])"
                :disabled="auth()->user()->cannot('create', [\App\Ldap\Role::class, $committee, $community])"
            >
                {{ __('New Role') }}
            </flux:button>
        </div>
    </div>

    <div class="flex items-center">
        <flux:switch wire:model.live="showOnlyActive" label="{{ __('committees.showOnlyActiveRoles') }}" align="left" />
    </div>

    <flux:field>
        <flux:label>{{ __('roles.search') }}</flux:label>
        <flux:input icon="search" clearable wire:model.live.debounce.500ms="search" />
    </flux:field>

    @php
        $hasHiddenRolesWithMembers = false;
        $committeesShown = 0;
    @endphp
    <div class="grid lg:grid-cols-2 gap-6">
        @forelse($roles as $role)
            @php
                $hasMembers = $this->getHasMembers($role);
                if (!$hasMembers && $this->showOnlyActive) {
                    $hasHiddenRolesWithMembers = true;
                }
                if ($hasMembers) {
                    $committeesShown = $committeesShown + 1;
                }
            @endphp
            @if($this->showOnlyActive && $this->getHasMembers($role) || !$this->showOnlyActive)
                <flux:card>
                    <div class="flex gap-4 items-center">
                        <div class="flex-1">
                            <flux:link
                                wire:navigate
                                href="{{ route('committees.roles.members', ['uid' => $uid, 'ou' => $ou, 'cn' => $role->getFirstAttribute('cn')]) }}"
                            >
                                {{ $role->getFirstAttribute('description') }}
                            </flux:link>
                        </div>
                        <div>
                            <flux:dropdown>
                                <flux:button size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item
                                        icon="users"
                                        wire:navigate
                                        :href="route('committees.roles.members', ['uid' => $uid, 'ou' => $ou, 'cn' => $role->getFirstAttribute('cn')])"
                                    >
                                        {{ __('roles.link_members') }}
                                    </flux:menu.item>
                                    <flux:menu.item
                                        icon="pencil"
                                        wire:navigate
                                        :href="route('committees.roles.edit', ['uid' => $uid, 'ou' => $ou, 'cn' => $role->getFirstAttribute('cn')])"
                                        :disabled="auth()->user()->cannot('edit', [$role, $committee, $community])"
                                    >
                                        {{ __('roles.link_edit') }}
                                    </flux:menu.item>
                                    <flux:menu.item
                                        variant="danger"
                                        icon="trash-2"
                                        :disabled="auth()->user()->cannot('delete', [$role, $committee, $community])"
                                        wire:click="deletePrepare('{{ $role->getFirstAttribute('cn') }}')">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                    @if($this->getHasMembers($role))
                        <div class="flex flex-wrap gap-2 mt-6">
                            @foreach($this->getMembers($role) as $member)
                                @php
                                    $jpegPhoto = $member->getFirstAttribute('jpegPhoto');
                                    if ($jpegPhoto) {
                                        $jpegPhoto = 'data:image/jpeg;base64,' . $jpegPhoto;
                                    }
                                @endphp
                                <flux:tooltip content="{{ $member->getFirstAttribute('cn') }}">
                                    <flux:avatar
                                        size="lg"
                                        src="{{ $jpegPhoto }}"
                                        name="{{ $member->getFirstAttribute('cn') }}"
                                    />
                                </flux:tooltip>
                            @endforeach
                        </div>
                    @endif
                </flux:card>
            @endif
        @empty
            <flux:callout variant="warning" icon="info" heading="{{ __('roles.no_roles_found') }}" class="col-span-full" />
        @endforelse
    </div>
    @if($hasHiddenRolesWithMembers && $committeesShown < 1)
        <flux:callout variant="warning" icon="info" heading="{{ __('roles.there_are_inactive_roles') }}" />
    @endif

    <div class="block h-[1px]"></div>

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('roles.delete_title', ['name' => $deleteRoleCn]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('roles.delete_warning', ['name' => $deleteRoleName]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
