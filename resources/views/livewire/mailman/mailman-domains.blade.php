<div class="max-w-7xl mx-auto w-full">
    <x-livewire-form class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('mailman_domains.title') }}</flux:heading>
            <flux:text class="text-base">{{ __('mailman_domains.explanation') }}</flux:text>
        </div>

        @if($fetchFailed)
            <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('mailman_domains.fetch_failed')" />
        @elseif($allowed === [])
            <flux:callout icon="information-circle" :heading="__('mailman_domains.no_domains')" />
        @else
            @foreach($allowed as $i => $entry)
                <flux:field wire:key="domain-{{ $entry['mail_host'] }}">
                    <flux:label>{{ $entry['mail_host'] }}</flux:label>
                    <flux:pillbox multiple searchable wire:model="allowed.{{ $i }}.realms" :placeholder="__('mailman_domains.select_communities')">
                        @foreach($this->communities as $uid => $name)
                            <flux:pillbox.option value="{{ $uid }}">{{ $name }}</flux:pillbox.option>
                        @endforeach
                    </flux:pillbox>
                    <flux:error name="allowed.{{ $i }}.realms.*" />
                </flux:field>
            @endforeach
        @endif

        <x-slot:abort_route>
            {{ route('settings.dashboard', ['realm' => $uid]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
