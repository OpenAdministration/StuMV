@props(['href', 'icon', 'heading', 'description'])

<div class="relative">
    <a
        wire:navigate
        href="{{ $href }}"
        class="block rounded-lg outline-none hover:ring-2 focus-visible:ring-2 ring-(--color-accent-content)"
    >
        <flux:card class="aspect-square flex flex-col items-center justify-center gap-3 p-4 text-center">
            <flux:icon :icon="$icon" class="size-10 text-(--color-accent-content)" />
            <flux:heading size="lg">{{ $heading }}</flux:heading>
            <span class="sr-only">{{ $description }}</span>
        </flux:card>
    </a>
    {{-- Dropdown with hover instead of a tooltip: opens on hover and also on tap. --}}
    <div class="absolute top-2 right-2">
        <flux:dropdown hover position="bottom" align="end">
            <flux:button size="xs" variant="subtle" icon="info" :aria-label="__('common.info')" />
            <flux:popover class="max-w-xs">
                <flux:text>{{ $description }}</flux:text>
            </flux:popover>
        </flux:dropdown>
    </div>
</div>
