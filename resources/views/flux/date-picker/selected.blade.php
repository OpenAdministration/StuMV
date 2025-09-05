@pure

@props([
    'placeholder' => null,
])

<ui-selected-date x-ignore wire:ignore class="truncate flex gap-2 text-start flex-1 text-zinc-700 in-[[disabled]]:text-zinc-500 dark:text-zinc-300 dark:in-[[disabled]]:text-zinc-400">
    <template name="placeholder">
        <span class="text-zinc-400 in-[[disabled]]:text-zinc-400/70 dark:text-zinc-400 dark:in-[[disabled]]:text-zinc-500" data-flux-date-picker-placeholder>
            {{ $placeholder ?? new Illuminate\Support\HtmlString('<slot></slot>') }}
        </span>
    </template>

    <template name="date">
        <div dir="auto"><slot></slot></div>
    </template>
</ui-selected-date>