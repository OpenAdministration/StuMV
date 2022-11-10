@props([
    'label',
    'help',
    'id' ,
    'error' => true,
])
@php
    $model = $attributes->thatStartWith('wire:model')->first();
    if (empty($id)) $id = $model;
@endphp
<div class="mt-4 space-y-1">
    @isset($label)
        <label for="{{ $id }}" {{ $label->attributes->class(['block text-sm font-medium text-gray-700']) }}>{{ $label }}</label>
    @endif
    <select id="{{ $id }}" {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm']) }}>
        {{ $slot }}
    </select>
    @error($model) <small wire:key="{{ $model }}" class="block text-red-500">{{ $message }}</small> @enderror
    @isset($help) <small class="block text-xs text-gray-400">{{ $help }}</small> @endisset
</div>
