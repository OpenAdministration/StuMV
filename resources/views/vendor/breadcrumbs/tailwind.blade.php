@unless ($breadcrumbs->isEmpty())
    @php
        // Route-model-bound 'uid' is a Community LDAP entry, not the plain
        // short code route()/RouteServiceProvider::home() expect (unlike
        // Eloquent, LdapRecord models don't resolve their route key on their
        // own when passed straight to route()).
        $homeCommunity = \Illuminate\Support\Facades\Route::current()?->parameter('uid');
        $homeUid = $homeCommunity?->getFirstAttribute('ou');
    @endphp
    <flux:breadcrumbs>
        <flux:breadcrumbs.item icon="house" href="{{ \App\Providers\RouteServiceProvider::home($homeUid) }}" />
        @if(count($breadcrumbs) < 5)
            @foreach($breadcrumbs as $breadcrumb)
                @if($breadcrumb->url && !$loop->last)
                    <flux:breadcrumbs.item href="{{ $breadcrumb->url }}">
                        @include('vendor.breadcrumbs.title', ['breadcrumb' => $breadcrumb])
                    </flux:breadcrumbs.item>
                @else
                    <flux:breadcrumbs.item>
                        @include('vendor.breadcrumbs.title', ['breadcrumb' => $breadcrumb])
                    </flux:breadcrumbs.item>
                @endif
            @endforeach
        @else
            <flux:breadcrumbs.item href="{{ $breadcrumbs[0]->url }}">
                @include('vendor.breadcrumbs.title', ['breadcrumb' => $breadcrumbs[0]])
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item href="{{ $breadcrumbs[1]->url }}">
                @include('vendor.breadcrumbs.title', ['breadcrumb' => $breadcrumbs[1]])
            </flux:breadcrumbs.item>
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
                    <flux:breadcrumbs.item>
                        @include('vendor.breadcrumbs.title', ['breadcrumb' => $breadcrumb])
                    </flux:breadcrumbs.item>
                @endif
            @endforeach
        @endif
    </flux:breadcrumbs>
@endunless
