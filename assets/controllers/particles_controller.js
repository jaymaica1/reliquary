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
        const baseCount = this.countValue;
        
        if (window.innerWidth <= 768) {
            return Math.floor(baseCount * 0.2);
        }
        else if (window.innerWidth <= 1024) {
            return Math.floor(baseCount * 0.4);
        }
        else if (window.innerWidth <= 1366) {
            return Math.floor(baseCount * 0.6);
        }
        else if (window.innerWidth <= 1920) {
            return Math.floor(baseCount * 0.8);
        }
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
            particle.style.left = 'calc(' + (Math.random() * 130) + '% - 100px)';
            
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
        this.element.innerHTML = '';
    }
}