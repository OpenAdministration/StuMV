@unless ($breadcrumbs->isEmpty())
    @php
        $items = collect($breadcrumbs)->values();
        $total = $items->count();
    @endphp

    @if($total === 1)
        {{-- Nothing to ever collapse - home + the one breadcrumb. --}}
        <flux:breadcrumbs>
            <flux:breadcrumbs.item icon="house" href="/" />
            <flux:breadcrumbs.item>
                @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[0], 'onlyItem' => true])
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>
    @else
        {{--
            Collapses middle breadcrumbs (everything between the first item
            and the last) into a "..." dropdown once they no longer fit the
            available width, instead of a fixed item-count threshold. Home
            and the first/last breadcrumb always stay visible.

            An off-screen clone of every possible item (never collapsed) is
            rendered purely to measure natural widths - real widths depend on
            translated text length, so they can't be known server-side. It's
            re-measured on resize and after every wire:navigate, since
            Alpine's Livewire-morph integration otherwise preserves this
            component's state (including a stale item count) across page
            navigations that don't change the element's own width.
        --}}
        <div
            class="relative w-full overflow-hidden"
            x-data="{
                collapsedCount: 0,
                _ro: null,
                _onNavigated: null,
                init() {
                    this.recalculate();
                    this._ro = new ResizeObserver(() => this.recalculate());
                    this._ro.observe(this.$el);
                    this._onNavigated = () => this.recalculate();
                    document.addEventListener('livewire:navigated', this._onNavigated);
                },
                destroy() {
                    this._ro?.disconnect();
                    if (this._onNavigated) document.removeEventListener('livewire:navigated', this._onNavigated);
                },
                recalculate() {
                    const available = this.$el.clientWidth;
                    const home = this.$refs.measureHome.offsetWidth;
                    const first = this.$refs.measureFirst.offsetWidth;
                    const last = this.$refs.measureLast.offsetWidth;
                    const dropdown = this.$refs.measureDropdown.offsetWidth;
                    const middles = Array.from(this.$refs.measureMiddles.children).map(el => el.offsetWidth);

                    for (let collapsed = 0; collapsed <= middles.length; collapsed++) {
                        const shown = middles.slice(collapsed).reduce((a, b) => a + b, 0);
                        const reserve = collapsed > 0 ? dropdown : 0;
                        if (home + first + last + reserve + shown <= available || collapsed === middles.length) {
                            this.collapsedCount = collapsed;
                            return;
                        }
                    }
                },
            }"
        >
            <flux:breadcrumbs>
                <flux:breadcrumbs.item icon="house" href="/" />

                @if($items[0]->url)
                    <flux:breadcrumbs.item href="{{ $items[0]->url }}">
                        @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[0]])
                    </flux:breadcrumbs.item>
                @else
                    <flux:breadcrumbs.item>
                        @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[0]])
                    </flux:breadcrumbs.item>
                @endif

                <flux:breadcrumbs.item x-show="collapsedCount > 0">
                    <flux:dropdown>
                        <flux:button icon="ellipsis" variant="ghost" size="sm" />
                        <flux:navmenu>
                            @for($i = 1; $i <= $total - 2; $i++)
                                @if($items[$i]->url)
                                    <flux:navmenu.item icon="corner-down-right" href="{{ $items[$i]->url }}" x-show="{{ $i }} <= collapsedCount">
                                        {{ $items[$i]->title }}
                                    </flux:navmenu.item>
                                @endif
                            @endfor
                        </flux:navmenu>
                    </flux:dropdown>
                </flux:breadcrumbs.item>

                @for($i = 1; $i <= $total - 2; $i++)
                    @if($items[$i]->url)
                        <flux:breadcrumbs.item href="{{ $items[$i]->url }}" x-show="{{ $i }} > collapsedCount">
                            @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[$i]])
                        </flux:breadcrumbs.item>
                    @else
                        <flux:breadcrumbs.item x-show="{{ $i }} > collapsedCount">
                            @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[$i]])
                        </flux:breadcrumbs.item>
                    @endif
                @endfor

                <flux:breadcrumbs.item>
                    @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[$total - 1]])
                </flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <flux:breadcrumbs class="invisible absolute pointer-events-none" aria-hidden="true">
                <flux:breadcrumbs.item x-ref="measureHome" icon="house" href="/" />
                <flux:breadcrumbs.item x-ref="measureFirst" href="{{ $items[0]->url }}">
                    @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[0]])
                </flux:breadcrumbs.item>
                <flux:breadcrumbs.item x-ref="measureDropdown">
                    <flux:button icon="ellipsis" variant="ghost" size="sm" />
                </flux:breadcrumbs.item>
                <span x-ref="measureMiddles" class="contents">
                    @for($i = 1; $i <= $total - 2; $i++)
                        <flux:breadcrumbs.item href="{{ $items[$i]->url }}">
                            @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[$i]])
                        </flux:breadcrumbs.item>
                    @endfor
                </span>
                <flux:breadcrumbs.item x-ref="measureLast">
                    @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[$total - 1]])
                </flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>
    @endif
@endunless
