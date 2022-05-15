@props([
    'type' => 'text',
    'label',
    'help',
    'id' ,
    'error' => true,
])
@php
    $model = $attributes->thatStartWith('wire:model')->first();
    $id = $id ?? $model;
@endphp
<div class="mt-4 space-y-1">
    @if(isset($label))
        <label for="{{ $id }}" {{ $label->attributes->class(['block font-medium text-sm text-gray-700']) }}">
        {{ $label }}
        </label>
    @endif
    <input
        id="{{ $id }}"
        type="{{ $type }}"
        {!! $attributes->merge(['class' => 'rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 block mt-1 w-full']) !!}
    >
    @error($model) <small wire:key="{{ $model }}" class="block text-red-500">{{ $message }}</small> @enderror
    @isset($help) <small class="block text-xs text-gray-400">{{ $help }}</small> @endisset
    {{ $slot ?? '' }}
</div>
