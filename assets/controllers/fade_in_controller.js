import { Controller } from '@hotwired/stimulus';

/**
 * Fade In controller
 *
 * This controller manages fade-in animations for sections using Intersection Observer
 */
export default class extends Controller {
    static values = {
        threshold: { type: Number, default: 0.1 },
        rootMargin: { type: String, default: '0px 0px -50px 0px' },
        excludeClasses: { type: String, default: 'hero' }
    }

    connect() {
        this.observerOptions = {
            threshold: this.thresholdValue,
            rootMargin: this.rootMarginValue
        };

        this.observer = new IntersectionObserver(this.handleIntersection.bind(this), this.observerOptions);
        this.initializeObserver();
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }

    initializeObserver() {
        const excludeClassList = this.excludeClassesValue.split(' ');
        
        // Observe all sections within this element that don't have excluded classes
        this.element.querySelectorAll('section').forEach(section => {
            const hasExcludedClass = excludeClassList.some(cls => section.classList.contains(cls));
            if (!hasExcludedClass) {
                section.style.opacity = '0';
                this.observer.observe(section);
            }
        });
    }

    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeInUp 1s ease forwards';
                this.observer.unobserve(entry.target);
            }
        });
    }
}