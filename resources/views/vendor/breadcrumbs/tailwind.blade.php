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
            Collapses everything between home and the current (last) item into
            a "..." dropdown once it no longer fits the available width,
            instead of a fixed item-count threshold. Only home and the last
            (current-page) item are ever exempt from collapsing - every other
            item is treated uniformly as collapsible, starting from the one
            closest to home, so there's no fixed-width floor that can still
            overflow on a narrow enough viewport or a long enough trail.

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
                    const last = this.$refs.measureLast.offsetWidth;
                    const dropdown = this.$refs.measureDropdown.offsetWidth;
                    const collapsibles = Array.from(this.$refs.measureCollapsibles.children).map(el => el.offsetWidth);

                    for (let collapsed = 0; collapsed <= collapsibles.length; collapsed++) {
                        const shown = collapsibles.slice(collapsed).reduce((a, b) => a + b, 0);
                        const reserve = collapsed > 0 ? dropdown : 0;
                        if (home + last + reserve + shown <= available || collapsed === collapsibles.length) {
                            this.collapsedCount = collapsed;
                            return;
                        }
                    }
                },
            }"
        >
            <flux:breadcrumbs>
                <flux:breadcrumbs.item icon="house" href="/" />

                {{--
                    x-show is passed through by <flux:breadcrumbs.item> onto its
                    INNER link/text element, not the item's own outer box - a
                    "hidden" item would still render its outer box and
                    separator chevron at full width. <template x-if> instead
                    removes the whole item from the DOM, which is what's
                    actually needed here.
                --}}
                <template x-if="collapsedCount > 0">
                    <flux:breadcrumbs.item>
                        <flux:dropdown>
                            <flux:button icon="ellipsis" variant="ghost" size="sm" />
                            <flux:navmenu>
                                @for($i = 0; $i <= $total - 2; $i++)
                                    @if($items[$i]->url)
                                        <template x-if="{{ $i }} < collapsedCount">
                                            <flux:navmenu.item icon="corner-down-right" href="{{ $items[$i]->url }}">
                                                {{ $items[$i]->title }}
                                            </flux:navmenu.item>
                                        </template>
                                    @endif
                                @endfor
                            </flux:navmenu>
                        </flux:dropdown>
                    </flux:breadcrumbs.item>
                </template>

                @for($i = 0; $i <= $total - 2; $i++)
                    <template x-if="{{ $i }} >= collapsedCount">
                        @if($items[$i]->url)
                            <flux:breadcrumbs.item href="{{ $items[$i]->url }}">
                                @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[$i]])
                            </flux:breadcrumbs.item>
                        @else
                            <flux:breadcrumbs.item>
                                @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[$i]])
                            </flux:breadcrumbs.item>
                        @endif
                    </template>
                @endfor

                <flux:breadcrumbs.item>
                    @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[$total - 1]])
                </flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <flux:breadcrumbs class="invisible absolute pointer-events-none" aria-hidden="true">
                <flux:breadcrumbs.item x-ref="measureHome" icon="house" href="/" />
                <flux:breadcrumbs.item x-ref="measureDropdown">
                    <flux:button icon="ellipsis" variant="ghost" size="sm" />
                </flux:breadcrumbs.item>
                <span x-ref="measureCollapsibles" class="contents">
                    @for($i = 0; $i <= $total - 2; $i++)
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
