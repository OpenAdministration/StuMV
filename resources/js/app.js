import './bootstrap';

import 'cropperjs';

document.addEventListener('alpine:init', () => {
    Alpine.data('breadcrumbs', () => ({
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
    }));

    // A boolean Livewire property mirrored into localStorage, so the user's
    // choice survives a page reload. Shared by every "show only mine/active"
    // switch in the app - wireProperty/persistKey/defaultValue differ per use.
    Alpine.data('persistedToggle', (wireProperty, persistKey, defaultValue) => ({
        value: Alpine.$persist(defaultValue).as(persistKey),
        init() {
            this.$wire.set(wireProperty, this.value);
            this.$watch('$wire.' + wireProperty, (value) => {
                this.value = value;
            });
        },
    }));

    Alpine.data('cropper', () => ({
        saving: false,
        initCropper() {
            const selection = this.$refs.selection;

            // Keep the crop box from being dragged/resized past the
            // image's own edges, into the empty canvas area (cropperjs
            // v2 no longer has a built-in equivalent of v1's viewMode).
            let clamping = false;

            selection.addEventListener('change', (event) => {
                if (clamping) {
                    return;
                }

                const image = this.$refs.canvas.querySelector('cropper-image');
                const canvasRect = this.$refs.canvas.getBoundingClientRect();
                const imageRect = image.getBoundingClientRect();

                if (imageRect.width === 0 || imageRect.height === 0) {
                    return;
                }

                const minX = imageRect.left - canvasRect.left;
                const minY = imageRect.top - canvasRect.top;
                const maxX = minX + imageRect.width;
                const maxY = minY + imageRect.height;

                let { x, y, width, height } = event.detail;

                // Shrink both dimensions by the same factor so the fixed
                // aspect ratio survives - shrinking width/height independently
                // would make cropperjs re-expand them right back to fit the
                // ratio, fighting this listener forever.
                const scale = Math.min(1, imageRect.width / width, imageRect.height / height);
                width *= scale;
                height *= scale;
                x = Math.min(Math.max(x, minX), maxX - width);
                y = Math.min(Math.max(y, minY), maxY - height);

                if (x !== event.detail.x || y !== event.detail.y || width !== event.detail.width || height !== event.detail.height) {
                    event.preventDefault();
                    clamping = true;
                    selection.$change(x, y, width, height);
                    clamping = false;
                }
            });
        },
        cropPicture() {
            if (this.saving) {
                return;
            }
            this.saving = true;

            const selection = this.$refs.selection;
            const image = this.$refs.canvas.querySelector('cropper-image');
            const imageRect = image.getBoundingClientRect();
            const canvasRect = this.$refs.canvas.getBoundingClientRect();

            // Converted from the displayed (scaled) size back to the
            // original (uploaded) image's own pixel coordinates - matches
            // Illuminate\Image\Image::crop()'s (width, height, x, y)
            // signature, applied server-side to the originally uploaded
            // file rather than a client-exported canvas.
            const scaleX = image.$image.naturalWidth / imageRect.width;
            const scaleY = image.$image.naturalHeight / imageRect.height;
            const offsetX = imageRect.left - canvasRect.left;
            const offsetY = imageRect.top - canvasRect.top;

            // Round the edges (not x/width and y/height independently) and
            // derive the size from their difference. Rounding x and width
            // separately can add up to 1px more than the natural image size
            // when the selection sits flush against the image's edge - and
            // Intervention Image pads that overshoot (in either direction,
            // i.e. also a negative x/y) with a solid background color
            // instead of clamping it, leaving a visible white sliver on the
            // cropped result. Subpixel drift between the getBoundingClientRect()
            // read during dragging and the one here is enough to round a
            // flush-left/top edge to -1, so clamp x1/y1 to 0 too.
            const naturalWidth = image.$image.naturalWidth;
            const naturalHeight = image.$image.naturalHeight;
            const x1 = Math.max(Math.round((selection.x - offsetX) * scaleX), 0);
            const y1 = Math.max(Math.round((selection.y - offsetY) * scaleY), 0);
            const x2 = Math.min(Math.round((selection.x - offsetX + selection.width) * scaleX), naturalWidth);
            const y2 = Math.min(Math.round((selection.y - offsetY + selection.height) * scaleY), naturalHeight);

            this.$wire.set('cropX', x1);
            this.$wire.set('cropY', y1);
            this.$wire.set('cropWidth', x2 - x1);
            this.$wire.set('cropHeight', y2 - y1);

            this.$wire.savePicture().finally(() => {
                this.saving = false;
            });
        },
    }));
});
