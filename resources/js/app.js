import Alpine from 'alpinejs';
import { registerRadioPlayer } from './player';

window.Alpine = Alpine;

Alpine.data('rocksHero', (slides = []) => ({
    active: 0,
    slides,
    interval: null,
    init() {
        if (this.slides.length < 2) {
            return;
        }

        this.interval = setInterval(() => this.next(), 3000);
    },
    next() {
        this.active = (this.active + 1) % this.slides.length;
    },
    go(index) {
        this.active = index;
    },
}));

Alpine.data('rocksNav', () => ({
    open: false,
    searchOpen: false,
    sticky: false,
    init() {
        this.sticky = window.scrollY > 100;
        window.addEventListener('scroll', () => {
            this.sticky = window.scrollY > 100;
        }, { passive: true });
    },
}));

Alpine.data('galleryLightbox', (images = []) => ({
    images,
    active: 0,
    open: false,
    touchStartX: 0,
    get current() {
        return this.images[this.active] ?? { src: '', caption: '' };
    },
    show(index) {
        this.active = index;
        this.open = true;
        document.body.classList.add('lb-disable-scrolling');
        this.preloadAround();
    },
    close() {
        this.open = false;
        document.body.classList.remove('lb-disable-scrolling');
    },
    next() {
        if (!this.images.length) {
            return;
        }

        this.active = (this.active + 1) % this.images.length;
        this.preloadAround();
    },
    prev() {
        if (!this.images.length) {
            return;
        }

        this.active = (this.active - 1 + this.images.length) % this.images.length;
        this.preloadAround();
    },
    preloadAround() {
        [this.active - 1, this.active + 1].forEach((index) => {
            const image = this.images[(index + this.images.length) % this.images.length];
            if (image?.src) {
                const preload = new Image();
                preload.src = image.src;
            }
        });
    },
    swipeEnd(event) {
        const delta = event.changedTouches[0].clientX - this.touchStartX;
        if (Math.abs(delta) < 40) {
            return;
        }

        delta < 0 ? this.next() : this.prev();
    },
}));

registerRadioPlayer(Alpine);

Alpine.start();
