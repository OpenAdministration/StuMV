@props(['href', 'icon', 'heading', 'description'])

<div class="relative h-full">
    <a
        wire:navigate
        href="{{ $href }}"
        class="block h-full rounded-lg outline-none hover:ring-2 focus-visible:ring-2 ring-(--color-accent-content)"
    >
        <flux:card class="h-full min-h-36 flex flex-col items-center justify-center gap-3 p-4 text-center">
            <flux:icon :icon="$icon" class="size-10 text-(--color-accent-content)" />
            <flux:heading size="lg" class="hyphens-none">{{ $heading }}</flux:heading>
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
