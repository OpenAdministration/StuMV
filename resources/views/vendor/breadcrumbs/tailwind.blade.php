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
                @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[0]])
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
                lastCollapsed: false,
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
                itemWidth(ref) {
                    // The Flux breadcrumb item component forwards x-ref onto
                    // its INNER link/text element, not the item's own outer
                    // box - which excludes the separator chevron (a sibling
                    // of that inner element). Walk up to the real item box.
                    return ref.closest('[data-flux-breadcrumbs-item]').offsetWidth;
                },
                recalculate() {
                    const available = this.$el.clientWidth;
                    const home = this.itemWidth(this.$refs.measureHome);
                    const last = this.itemWidth(this.$refs.measureLast);
                    const dropdown = this.itemWidth(this.$refs.measureDropdown);
                    const collapsibles = [];
                    for (let i = 0; this.$refs['measureCollapsible' + i]; i++) {
                        collapsibles.push(this.itemWidth(this.$refs['measureCollapsible' + i]));
                    }

                    for (let collapsed = 0; collapsed <= collapsibles.length; collapsed++) {
                        const shown = collapsibles.slice(collapsed).reduce((a, b) => a + b, 0);
                        const reserve = collapsed > 0 ? dropdown : 0;
                        if (home + last + reserve + shown <= available) {
                            this.collapsedCount = collapsed;
                            this.lastCollapsed = false;
                            return;
                        }
                    }

                    // Not even home + the current page + dropdown fits with
                    // everything else already collapsed - fold the current
                    // page into the dropdown too, as a last resort, leaving
                    // just home + '...'.
                    this.collapsedCount = collapsibles.length;
                    this.lastCollapsed = true;
                },
            }"
        >
            <flux:breadcrumbs>
                <flux:breadcrumbs.item icon="house" href="/" />

                {{--
                    x-show is passed through by the Flux breadcrumb item
                    component onto its INNER link/text element, not the item's
                    own outer box - a "hidden" item would still render its
                    outer box and separator chevron at full width. A template
                    x-if removes the whole item from the DOM instead, which is
                    what's actually needed here.
                --}}
                <template x-if="collapsedCount > 0 || lastCollapsed">
                    {{--
                        The current page (a separate template x-if right after
                        this one) always stays in the DOM even when hidden, so
                        Flux's own "last visible item" separator styling never
                        actually applies to this dropdown - hide its separator
                        manually once it's the last thing actually shown. A
                        plain (non-Flux) wrapping div is needed for the class
                        binding to land where CSS can reach the separator: Flux
                        only special-cases bare "class"/"style" attributes onto
                        the item's outer box, not x-bind:class.
                    --}}
                    <div x-bind:class="{ '[&_[data-flux-breadcrumbs-item]>svg]:hidden': lastCollapsed }">
                        <flux:breadcrumbs.item>
                            <flux:dropdown>
                                <flux:button icon="ellipsis" variant="ghost" size="sm" />
                                <flux:navmenu>
                                    @for($i = 0; $i <= $total - 2; $i++)
                                        @if($items[$i]->url)
                                            <template x-if="collapsedCount > {{ $i }}">
                                                <flux:navmenu.item icon="corner-down-right" href="{{ $items[$i]->url }}">
                                                    {{ $items[$i]->title }}
                                                </flux:navmenu.item>
                                            </template>
                                        @endif
                                    @endfor
                                    {{-- The current page itself, only shown here once there's no
                                         room left for it in the main bar either. --}}
                                    <template x-if="lastCollapsed">
                                        <flux:navmenu.item icon="corner-down-right">
                                            {{ $items[$total - 1]->title }}
                                        </flux:navmenu.item>
                                    </template>
                                </flux:navmenu>
                            </flux:dropdown>
                        </flux:breadcrumbs.item>
                    </div>
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

                <template x-if="!lastCollapsed">
                    <flux:breadcrumbs.item>
                        @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[$total - 1]])
                    </flux:breadcrumbs.item>
                </template>
            </flux:breadcrumbs>

            <flux:breadcrumbs class="invisible absolute pointer-events-none" aria-hidden="true">
                <flux:breadcrumbs.item x-ref="measureHome" icon="house" href="/" />
                <flux:breadcrumbs.item x-ref="measureDropdown">
                    <flux:button icon="ellipsis" variant="ghost" size="sm" />
                </flux:breadcrumbs.item>
                @for($i = 0; $i <= $total - 2; $i++)
                    <flux:breadcrumbs.item x-ref="measureCollapsible{{ $i }}" href="{{ $items[$i]->url }}">
                        @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[$i]])
                    </flux:breadcrumbs.item>
                @endfor
                <flux:breadcrumbs.item x-ref="measureLast">
                    @include('vendor.breadcrumbs.title', ['breadcrumb' => $items[$total - 1]])
                </flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>
    @endif
@endunless
