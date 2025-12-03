<div>
    <div class="w-full">
        <div class="mb-4 -mx-6 -mt-6 px-6 flex border-b border-zinc-200 gap-3">
            <a wire:navigate href="{{ route('profile', ['username' => $currentUsername]) }}" class="inline-flex items-center gap-x-1.5 px-2 pt-4 pb-3 border-b-2 border-transparent font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">{{ __('Profile') }}</a>
            <a wire:navigate href="{{ route('profile.memberships', ['username' => $currentUsername]) }}" class="inline-flex items-center gap-x-1.5 px-2 pt-4 pb-3 border-b-2 border-indigo-400 font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-indigo-400 focus:outline-none focus:text-gray-700 focus:border-indigo-400 transition duration-150 ease-in-out">{{ __('profile.memberships') }}</a>
            <a wire:navigate href="{{ route('password.change', ['username' => $currentUsername]) }}" class="inline-flex items-center gap-x-1.5 px-2 pt-4 pb-3 border-b-2 border-transparent font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">{{ __('Change Password') }}</a>
        </div>
    </div>
    
    <div>
        <div class="mt-6 flex">
            <div class="ml-auto">
                <x-button.primary wire:click="exportPdf">{{ __('profile.exportAsPdf') }}</x-button.primary>
            </div>
        </div>
        <div class="mt-6 mb-6">
            <input id="showOnlyActive" type="checkbox" wire:model.change="showOnlyActive" class="rounded-sm border-indigo-400">
            <label for="showOnlyActive" class="ml-4">{{ __('profile.showOnlyActiveMemberships') }}</label>
        </div>
        <x-table>
            <x-slot name="head">
                <x-table.heading>
                    {{ __('profile.role') }}
                </x-table.heading>
                <x-table.heading>
                    {{ __('profile.committee') }}
                </x-table.heading>
                <x-table.heading>
                    {{ __('profile.from') }}
                </x-table.heading>
                <x-table.heading>
                    {{ __('profile.until') }}
                </x-table.heading>
                <x-table.heading>
                    {{ __('profile.decision') }}
                </x-table.heading>
                <x-table.heading>
                    {{ __('profile.comment') }}
                </x-table.heading>
            </x-slot>
            @forelse($memberships as $row)
                <x-table.row>
                    <x-table.cell>
                        {{ $row['role']->getFirstAttribute('description') }}
                    </x-table.cell>
                    <x-table.cell>
                        {{ $row['role']->committee()->getFirstAttribute('description') }}
                    </x-table.cell>
                    <x-table.cell>
                        {{ \Carbon\Carbon::parse($row['from'])->format('Y-m-d') }}
                    </x-table.cell>
                    <x-table.cell>
                        @if ($row['until'] != '')
                        {{ \Carbon\Carbon::parse($row['until'])->format('Y-m-d') }}
                        @else
                        {{ __('profile.today') }}
                        @endif
                    </x-table.cell>
                    <x-table.cell>
                        {{ \Carbon\Carbon::parse($row['decided'])->format('Y-m-d') }}
                    </x-table.cell>
                    <x-table.cell>
                        {{ $row['comment'] }}
                    </x-table.cell>
                </x-table.row>
            @empty
                <x-table.row>
                    <x-table.cell colspan="4">
                        <div class="flex justify-center item-center">
                            <span class="text-gray-400 text-xl py-2 font-medium">{{ __('groups.no_roles_found') }}</span>
                        </div>
                    </x-table.cell>
                </x-table.row>
            @endforelse
        </x-table>
    </div>
</div>
