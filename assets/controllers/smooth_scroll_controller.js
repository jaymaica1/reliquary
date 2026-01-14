import { Controller } from '@hotwired/stimulus';

/**
 * Smooth Scroll controller
 *
 * This controller handles smooth scrolling for anchor links
 */
export default class extends Controller {
    static targets = ['link'];

    connect() {
        // If no specific targets, find all anchor links in the element
        if (!this.hasLinkTarget) {
            this.element.querySelectorAll('a[href^="#"]').forEach(anchor => {
                const href = anchor.getAttribute('href');
                if (href !== '#') {
                    anchor.addEventListener('click', this.handleClick.bind(this));
                }
            });
        }
    }

    handleClick(event) {
        const href = event.currentTarget.getAttribute('href');
        if (href === '#' || !href.startsWith('#')) {
            return;
        }

        event.preventDefault();
        try {
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        } catch (e) {
            console.warn(`SmoothScroll: Invalid selector "${href}"`, e);
        }
    }

    // Action method for direct use with data-action
    scroll(event) {
        this.handleClick(event);
    }
}