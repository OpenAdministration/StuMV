@pure

@props([
    'field' => null,
    'format' => null,
])

@php
$format = is_array($format) ? \Illuminate\Support\Js::encode($format) : $format;
@endphp

<span {{ $attributes }}>
    <slot @if ($field) field="{{ $field }}" @endif @if ($format) format="{{ $format }}" @endif></slot>
</span>
