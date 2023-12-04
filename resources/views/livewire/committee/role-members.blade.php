<div class="flex-col space-y-4">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">Lorem Ipsum</h1>
            <p class="mt-2 text-sm text-gray-700">Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren,</p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
            <x-button.link-primary :href="route('committees.roles.add-member', ['uid' => $uid, 'cn' => $cn, 'ou' => $ou])"
                                   icon-leading="fas-plus" :disabled="false">
                {{ __('Add Member') }}
            </x-button.link-primary>
        </div>
    </div>
    <div class="flex justify-between">
        <x-input.group wire:model.live.debounce="search" placeholder="{{ __('roles.members.search') }}"></x-input.group>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading/>
            <x-table.heading>
                {{ __('User') }}
            </x-table.heading>
            <x-table.heading>
                {{ __('From') }}
            </x-table.heading>
            <x-table.heading>
                {{ __('Until') }}
            </x-table.heading>
            <x-table.heading>
                {{ __('Decided') }}
            </x-table.heading>
            <x-table.heading>
                {{ __('Comment') }}
            </x-table.heading>
            <x-table.heading/>
        </x-slot>
        @forelse($members as $member)
            <x-table.row>
                <x-table.cell>
                    <span @class(["inline-block", "h-2", "w-2", "flex-shrink-0", "rounded-full",
                        "bg-green-400" => $member->isActive(),
                        "bg-gray-200" => !$member->isActive(),
                    ]) aria-hidden="true"></span>
                </x-table.cell>
                <x-table.cell>
                    {{ $member->username }}
                </x-table.cell>
                <x-table.cell><span class="flex justify-center">
                    {{ $member->from->format('d.m.Y') }}
                </span></x-table.cell>
                <x-table.cell><span class="flex justify-center">
                    @empty($member->until)
                        <x-fas-forward-fast
                            wire:click="prepareTermination({{ $member->id }})"
                            class="text-indigo-600 hover:text-indigo-500 cursor-pointer"/>
                    @else
                        {{ $member->until->format('d.m.Y') }}
                    @endempty
                </span></x-table.cell>
                <x-table.cell><span class="flex justify-center">
                    @empty($member->decided)
                        <hr class="w-16"/>
                    @else
                        {{ $member->decided->format('d.m.Y') }}
                    @endempty
                </span></x-table.cell>
                <x-table.cell>
                    @empty($member->comment)
                        <hr class="w-16"/>
                    @else
                        {{ $member->comment }}
                    @endempty
                </x-table.cell>
                <x-table.cell><span class="flex gap-x-6 group-hover:opacity-100 opacity-0">
                    <x-fas-pencil :href="route('committees.roles.members.edit', ['uid' => $uid, 'ou' => $ou, 'cn' => $cn, 'id' => $member->id])" class="text-indigo-500 hover:text-indigo-400"/>
                    <x-fas-trash class="text-red-500 hover:text-red-400 cursor-pointer" wire:click="prepareDeletion({{ $member->id }})"/>
                </span></x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="6">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('roles.no_members_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>

    <form wire:submit="commitDeletion">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('roles.members.delete_title', ['name' => $deleteUsername]) }}
            </x-slot:title>
            <x-slot:content>
                <span>{{ __('roles.members.delete_text', ['name' => $deleteUsername]) }}</span>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>


    <form wire:submit="commitTermination">
        <x-modal.confirmation wire:model="showTerminateModal">
            <x-slot:title>
                {{ __('roles.members.terminate_title', ['name' => $terminateUsername]) }}
            </x-slot:title>
            <x-slot:content>
                <span>{{ __('roles.members.terminate_text', ['name' => $terminateUsername]) }}</span>
                <x-input.group autofocus type="date" :label="__('Terminiation Date')" wire:model="terminateDate"/>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.primary type="submit">{{ __('Terminate') }}</x-button.primary>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
