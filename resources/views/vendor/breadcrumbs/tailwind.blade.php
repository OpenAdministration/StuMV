@unless ($breadcrumbs->isEmpty())
    @php
        $items = collect($breadcrumbs)->values();
        $total = $items->count();
    @endphp

    {{--
        Collapses everything between home and the current (last) item into
        a "..." dropdown once it no longer fits the available width, instead
        of a fixed item-count threshold. Home always stays; every other item,
        including the current page as a last resort, is collapsible -
        starting from the one closest to home - so there's no fixed-width
        floor that can still overflow on a narrow enough viewport or a long
        enough trail. This also applies with a single breadcrumb item (e.g.
        just the community name on the community dashboard): there are no
        collapsible items in between, but the item itself still needs to
        collapse into the dropdown if even that alone doesn't fit.

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
        x-bind:class="{ 'stumv-breadcrumbs-last-collapsed': lastCollapsed }"
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

        {{--
            The dropdown item is never actually the DOM's last child (the
            current-page item's own template tag always follows it, even
            when empty), so Flux's own group-last separator-hiding never
            applies to it - hide it explicitly instead, once the dropdown
            really is the last thing shown. Targeted via :has() rather
            than an extra wrapper element around the item: wrapping it
            would make it trivially "last child" of that 1-item wrapper,
            permanently hiding its separator instead of only when needed.
        --}}
        <style>
            .stumv-breadcrumbs-last-collapsed [data-flux-breadcrumbs-item]:has([data-flux-dropdown]) > svg {
                display: none;
            }
        </style>
    </div>
@endunless
