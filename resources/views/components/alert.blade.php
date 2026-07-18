@props([
    'status' => session('status', 'success'),
])
@php
    $isSuccess = ($status === 'success');
    $isWarning = ($status === 'warning');
    $isError = ($status === 'error')
@endphp

@if (session()->has('message'))
    <div @class([
        "rounded-md",
        "p-4",
        "mb-6",
        "bg-green-100" => $isSuccess,
        "bg-yellow-100" => $isWarning,
        "bg-red-100" => $isError,
    ])>
        <div class="flex">
            <div class="shrink-0">
                @if($isSuccess)
                    <flux:icon name="award" class="h-5 w-5 text-green-400" />
                @elseif($isWarning)
                    <flux:icon name="triangle-alert" class="h-5 w-5 text-yellow-500" />
                @elseif($iError)
                    <flux:icon name="zap" class="h-5 w-5 text-red-400" />
                @endif
            </div>
            <div class="ml-3">
                <p @class([
                    "text-sm",
                    "font-medium",
                    "text-green-800" => $isSuccess,
                    "text-yellow-800" => $isWarning,
                    "text-red-800" => $isWarning,
                ])>{{ session('message') }}</p>
            </div>
        </div>
    </div>
@endif
