@unless ($breadcrumbs->isEmpty())
    <flux:breadcrumbs>
        @if(count($breadcrumbs) < 5)
            @foreach($breadcrumbs as $breadcrumb)
                @if($breadcrumb->url && !$loop->last)
                    <flux:breadcrumbs.item href="{{ $breadcrumb->url }}">{{ $breadcrumb->title }}</flux:breadcrumbs.item>
                @else
                    <flux:breadcrumbs.item>{{ $breadcrumb->title }}</flux:breadcrumbs.item>
                @endif
            @endforeach
        @else
            <flux:breadcrumbs.item href="{{ $breadcrumbs[0]->url }}">{{ $breadcrumbs[0]->title }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item href="{{ $breadcrumbs[1]->url }}">{{ $breadcrumbs[1]->title }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                <flux:dropdown>
                    <flux:button icon="ellipsis" variant="ghost" size="sm" />
                    <flux:navmenu>
                        @foreach($breadcrumbs as $index => $breadcrumb)
                            @if($breadcrumb->url && !$loop->first && !$loop->last && $index !== 1)
                                <flux:navmenu.item icon="corner-down-right" href="{{ $breadcrumb->url }}">{{ $breadcrumb->title }}</flux:navmenu.item>
                            @endif
                        @endforeach
                    </flux:navmenu>
                </flux:dropdown>
            </flux:breadcrumbs.item>

            @foreach($breadcrumbs as $breadcrumb)
                @if($breadcrumb->url && $loop->last)
                    <flux:breadcrumbs.item>{{ $breadcrumb->title }}</flux:breadcrumbs.item>
                @endif
            @endforeach
        @endif
    </flux:breadcrumbs>
@endunless
