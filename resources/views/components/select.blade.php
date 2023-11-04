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
    <div class="flex justify-between">
        @isset($label)
            {{-- Label can either be defined as slot --}}
            {{ $label }}
        @elseif($attributes->has('label'))
            {{-- or with the attributes like <x-input.group label="Upload" label.class="red" ...> --}}
            <label {{ $attributes->thatStartWith('label.')->merge(['class' => 'block text-sm font-medium leading-6 text-gray-900']) }}>
                {{ $attributes->get('label') }}
            </label>
        @endif
        @isset($note)
            <span class="text-sm leading-6 text-gray-500" id="{{ $id }}-note">{{ $note }}</span>
        @endisset
    </div>
    <select id="{{ $id }}" {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm']) }}>
        {{ $slot }}
    </select>
    @error($model) <small wire:key="{{ $model }}" class="block text-red-500">{{ $message }}</small> @enderror
    @isset($help) <small class="block text-xs text-gray-400">{{ $help }}</small> @endisset
</div>
