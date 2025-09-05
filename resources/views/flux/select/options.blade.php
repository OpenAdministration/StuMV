@aware([ 'searchable' ])

@props([
    'searchable' => null,
    'search' => null,
    'empty' => null,
])

@php
$classes = Flux::classes()
    ->add('[:where(&)]:min-w-48 [:where(&)]:max-h-80 p-[.3125rem]')
    ->add('rounded-lg shadow-xs')
    ->add('border border-zinc-200 dark:border-zinc-600')
    ->add('bg-white dark:bg-zinc-700')
    ;

// Searchable can also be a slot...
if (is_object($searchable)) $search = $searchable;
@endphp

<?php if (! $searchable): ?>
    <ui-options popover="manual" {{ $attributes->class($classes) }} data-flux-options>
        {{ $slot }}
    </ui-options>
<?php else: ?>
    <div popover="manual" class="[:where(&)]:min-w-48 [&:popover-open]:flex [&:popover-open]:flex-col rounded-lg shadow-xs border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 p-[.3125rem]" data-flux-options>
        <?php if ($search): ?> {{ $search }} <?php else: ?>
            <flux:select.search />
        <?php endif; ?>

        <ui-options class="max-h-80 overflow-y-auto -me-1.25 -mt-1.25 pt-[.3125rem] pe-[.3125rem] -mb-1.25 pb-[.3125rem]">
            {{ $slot }}

            <?php if ($empty): ?>
                <ui-empty class="data-hidden:hidden">{{ $empty }}</ui-empty>
            <?php else: ?>
                <flux:select.empty>{!! __('No results found') !!}</flux:select.empty>
            <?php endif; ?>
        </ui-options>
    </div>
<?php endif; ?>
