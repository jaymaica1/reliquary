import { Controller } from '@hotwired/stimulus';

/**
 * Particles controller
 *
 * This controller creates and manages floating particles animation
 */
export default class extends Controller {
    static values = {
        count: { type: Number, default: 1350 }
    }

    connect() {
        this.createParticles();
    }

    getResponsiveParticleCount() {
        const viewport = window.innerWidth * window.innerHeight;
        const baseCount = this.countValue;
        
        // Define breakpoints based on common screen resolutions
        // Mobile (up to 768px width): reduce to 20% of particles
        if (window.innerWidth <= 768) {
            return Math.floor(baseCount * 0.2);
        }
        // Tablet (769px to 1024px width): reduce to 40% of particles
        else if (window.innerWidth <= 1024) {
            return Math.floor(baseCount * 0.4);
        }
        // Small desktop (1025px to 1366px width): reduce to 60% of particles
        else if (window.innerWidth <= 1366) {
            return Math.floor(baseCount * 0.6);
        }
        // Large desktop (1367px to 1920px width): reduce to 80% of particles
        else if (window.innerWidth <= 1920) {
            return Math.floor(baseCount * 0.8);
        }
        // Ultra-wide/4K screens: use full particle count
        else {
            return baseCount;
        }
    }

    createParticles() {
        const particleCount = this.getResponsiveParticleCount();
        
        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.width = Math.random() * 6 + 4 + 'px';
            particle.style.height = particle.style.width;
            particle.style.left = Math.random() * 100 + '%';
            
            // Use negative delays for some particles to make them visible immediately
            // Half particles start with negative delays (already in progress)
            // Half particles start with positive delays (future animations)
            if (i < particleCount / 2) {
                particle.style.animationDelay = '-' + (Math.random() * 30) + 's';
            } else {
                particle.style.animationDelay = Math.random() * 10 + 's';
            }
            particle.style.animationDuration = (Math.random() * 20 + 20) + 's';
            this.element.appendChild(particle);
        }
    }

    disconnect() {
        // Clean up particles when controller is disconnected
        this.element.innerHTML = '';
    }
}