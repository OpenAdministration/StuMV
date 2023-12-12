<div class="flex-col space-y-4">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('realms.list_headline') }}</h1>
            <p class="mt-2 text-sm text-gray-700">
                {{  __('realms.list_explanation') }}
                <x-link class="inline-flex items-baseline" href="mailto:{{ config('app.help_contact_mail') }}">
                    <x-fas-envelope class="w-3 h-3 items-baseline ml-1"/> {{ __('Contact us') }}
                </x-link>
            </p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
            <x-button.link-primary href="{{ route('realms.new') }}" icon-leading="fas-plus" :disabled="auth()->user()->cannot('create', \App\Ldap\Community::class)">
                {{ __('New Realm') }}
            </x-button.link-primary>
        </div>
    </div>

    <div class="flex justify-between">
        <x-input.group wire:model.live.debounce="search" placeholder="{{ __('realms.search') }}"/>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading sortable wire:click="sortBy('uid')"
                             :direction="$sortField === 'uid' ? $sortDirection : null">
                {{ __('realms.shortcode') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('long_name')"
                :direction="$sortField === 'long_name' ? $sortDirection : null"
            >
                {{ __('Name') }}
            </x-table.heading>
            <x-table.heading/>
            <x-table.heading/>
            <x-table.heading/>
        </x-slot>
        @php /** @var \App\Ldap\Community $realm */ @endphp
        @forelse($realmSlice->items() as $realm)
            <x-table.row>
                <x-table.cell>{{ $realm->getShortCode() }}</x-table.cell>
                <x-table.cell>{{ $realm->getLongName() }}</x-table.cell>
                <x-table.cell>
                    <x-link :disabled="Auth::user()->cannot('enter', $realm)"
                        href="#" wire:click="enter('{{ $realm->getShortCode() }}')" >
                        <x-fas-dungeon/> {{ __('Enter') }}
                    </x-link>
                </x-table.cell>
                <x-table.cell>
                    <x-link :disabled="Auth::user()->cannot('edit', $realm)"
                        href="{{ route('realms.edit', ['uid' => $realm->getShortCode()]) }}">
                        <x-fas-pencil/> {{ __('Edit') }}
                    </x-link>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link-danger icon-leading="fas-trash"
                        :disabled="Auth::user()->cannot('delete', $realm)"
                        wire:click="deletePrepare('{{ $realm->getShortCode() }}')">
                        {{ __('Delete') }}
                    </x-button.link-danger>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="6">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('realms.no_realms_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('realms.delete_title', ['name' => $deleteRealmName]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('realms.delete_warning', ['name' => $deleteRealmName]) }}
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
