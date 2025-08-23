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
                anchor.addEventListener('click', this.handleClick.bind(this));
            });
        }
    }

    handleClick(event) {
        event.preventDefault();
        const target = document.querySelector(event.currentTarget.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }

    // Action method for direct use with data-action
    scroll(event) {
        this.handleClick(event);
    }
}