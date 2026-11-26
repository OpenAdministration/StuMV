<div class="max-w-7xl mx-auto w-full flex-col">
    <div class="mb-8 space-y-4">
        <flux:heading size="xl">{{ __('activity_log.title') }}</flux:heading>
        <flux:text class="text-base">{{ __($allRealms ? 'activity_log.explanation_all' : 'activity_log.explanation') }}</flux:text>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <flux:input icon="search" clearable wire:model.live.debounce="search" :label="__('activity_log.search')" />
        <flux:pillbox multiple searchable wire:model.live="actions" :label="__('activity_log.action')" :placeholder="__('activity_log.all_actions')">
            @foreach($actionOptions as $key => $label)
                <flux:pillbox.option value="{{ $key }}">{{ $label }}</flux:pillbox.option>
            @endforeach
        </flux:pillbox>
        <flux:input type="date" wire:model.live="from" :label="__('activity_log.from')" />
        <flux:input type="date" wire:model.live="until" :label="__('activity_log.until')" />
    </div>

    @if($logs->isNotEmpty())
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('activity_log.time') }}</flux:table.column>
                @if($allRealms)
                    <flux:table.column>{{ __('activity_log.realm') }}</flux:table.column>
                @endif
                <flux:table.column>{{ __('activity_log.actor') }}</flux:table.column>
                <flux:table.column>{{ __('activity_log.action') }}</flux:table.column>
                <flux:table.column>{{ __('activity_log.subject') }}</flux:table.column>
                <flux:table.column>{{ __('activity_log.details') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($logs as $log)
                    <flux:table.row wire:key="log-{{ $log->id }}">
                        <flux:table.cell class="whitespace-nowrap align-top tabular-nums">
                            <span class="whitespace-pre-line" wire:key="time-{{ $log->id }}" wire:ignore x-data="localDateTime('{{ $log->created_at->toIso8601String() }}', '\n')">{{ $log->created_at->format('Y-m-d') }}
{{ $log->created_at->format('H:i') }} UTC</span>
                        </flux:table.cell>
                        @if($allRealms)
                            <flux:table.cell class="align-top">{{ $log->realm ?? '–' }}</flux:table.cell>
                        @endif
                        <flux:table.cell class="align-top">
                            @if($log->actor_type === null)
                                {{ __('activity_log.system') }}
                            @elseif($log->actor_type === 'user' && isset($people[$log->actor_id]))
                                <flux:link wire:navigate :href="$people[$log->actor_id]['url']">{{ $people[$log->actor_id]['name'] }}</flux:link>
                            @elseif($log->actor_type === 'identity_provider' && isset($providers[$log->actor_id]))
                                {{ $providers[$log->actor_id] }}
                            @else
                                {{ __('activity_log.deleted_actor') }}
                            @endif
                            @if($log->ip)
                                <div class="text-xs text-zinc-500">{{ $log->ip }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="align-top">{{ __('activity_log.actions.'.$log->action) }}</flux:table.cell>
                        <flux:table.cell class="align-top whitespace-normal wrap-anywhere min-w-40">
                            @if($log->subject_type === \App\Ldap\User::class && isset($people[$log->subject_id]))
                                <flux:link wire:navigate :href="$people[$log->subject_id]['url']">{{ $log->subject_label }}</flux:link>
                            @else
                                {{ $log->subject_label ?? '–' }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="align-top min-w-64">
                            @if($log->details)
                                <dl class="text-xs space-y-1 max-w-md whitespace-normal wrap-anywhere">
                                    @foreach($log->details as $field => $value)
                                        <div class="break-words">
                                            <dt class="inline font-medium">{{ $this->fieldLabel($field) }}:</dt>
                                            <dd class="inline">
                                                @if(is_array($value) && array_key_exists('old', $value) && array_key_exists('new', $value) && count($value) === 2)
                                                    {{ $this->formatValue($value['old']) }} → {{ $this->formatValue($value['new']) }}
                                                @elseif($field === 'person' && is_string($value) && isset($people[$value]))
                                                    <flux:link wire:navigate :href="$people[$value]['url']">{{ $people[$value]['name'] }}</flux:link>
                                                @else
                                                    {{ $this->formatValue($value) }}
                                                @endif
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="pagination">
            <flux:pagination :paginator="$logs" />
        </div>
    @else
        <flux:callout variant="warning" icon="circle-alert" heading="{{ __('activity_log.empty') }}" />
    @endif
</div>
