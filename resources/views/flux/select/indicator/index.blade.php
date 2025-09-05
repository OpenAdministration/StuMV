@aware([ 'variant' ])

@props([
    'variant' => 'check',
])

@php
// This prevents variants picked up by `@aware()` from other wrapping components like flux::modal from being used here...
$variant = $variant !== 'check' && Flux::componentExists('select.indicator.variants.' . $variant)
    ? $variant
    : 'check';
@endphp

<flux:delegate-component :component="'select.indicator.variants.' . $variant">{{ $slot }}</flux:delegate-component>
