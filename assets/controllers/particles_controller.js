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

    createParticles() {
        const particleCount = this.countValue;
        
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