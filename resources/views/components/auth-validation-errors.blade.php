@props(['errors'])

@if ($errors->any())
    <flux:callout variant="danger" icon="circle-x" />
    <div {{ $attributes }}>
        <flux:callout.heading>
            {{ __('Whoops! Something went wrong.') }}
        </flux:callout.heading>

        <flux:callout.text>
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </flux:callout.text>
    </div>
@endif
