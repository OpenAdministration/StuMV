@props(['status'])

@if ($status)
    <flux:callout variant="success" icon="circle-check" heading="{{ $status }}" />
@endif
