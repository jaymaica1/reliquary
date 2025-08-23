import { Controller } from '@hotwired/stimulus';

/**
 * Navbar Scroll controller
 *
 * This controller manages the navbar scroll effect, adding a 'scrolled' class
 * when the user scrolls past a certain threshold
 */
export default class extends Controller {
    static values = { 
        threshold: { type: Number, default: 50 }
    }

    connect() {
        this.handleScroll = this.handleScroll.bind(this);
        window.addEventListener('scroll', this.handleScroll);
    }

    disconnect() {
        window.removeEventListener('scroll', this.handleScroll);
    }

    handleScroll() {
        if (window.scrollY > this.thresholdValue) {
            this.element.classList.add('scrolled');
        } else {
            this.element.classList.remove('scrolled');
        }
    }
}