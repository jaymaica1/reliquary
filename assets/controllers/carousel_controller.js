import { Controller } from '@hotwired/stimulus';

/**
 * Carousel controller
 *
 * This controller handles touch and mouse support for carousel scrolling
 */
export default class extends Controller {
    connect() {
        this.isDown = false;
        this.startX = 0;
        this.scrollLeft = 0;
        
        // Set initial cursor
        this.element.style.cursor = 'grab';
        
        // Bind event handlers to maintain correct context
        this.handleMouseDown = this.handleMouseDown.bind(this);
        this.handleMouseLeave = this.handleMouseLeave.bind(this);
        this.handleMouseUp = this.handleMouseUp.bind(this);
        this.handleMouseMove = this.handleMouseMove.bind(this);
        
        // Add event listeners
        this.element.addEventListener('mousedown', this.handleMouseDown);
        this.element.addEventListener('mouseleave', this.handleMouseLeave);
        this.element.addEventListener('mouseup', this.handleMouseUp);
        this.element.addEventListener('mousemove', this.handleMouseMove);
    }
    
    disconnect() {
        // Clean up event listeners
        this.element.removeEventListener('mousedown', this.handleMouseDown);
        this.element.removeEventListener('mouseleave', this.handleMouseLeave);
        this.element.removeEventListener('mouseup', this.handleMouseUp);
        this.element.removeEventListener('mousemove', this.handleMouseMove);
    }
    
    handleMouseDown(e) {
        this.isDown = true;
        this.startX = e.pageX - this.element.offsetLeft;
        this.scrollLeft = this.element.scrollLeft;
        this.element.style.cursor = 'grabbing';
    }
    
    handleMouseLeave() {
        this.isDown = false;
        this.element.style.cursor = 'grab';
    }
    
    handleMouseUp() {
        this.isDown = false;
        this.element.style.cursor = 'grab';
    }
    
    handleMouseMove(e) {
        if (!this.isDown) return;
        e.preventDefault();
        const x = e.pageX - this.element.offsetLeft;
        const walk = (x - this.startX) * 2;
        this.element.scrollLeft = this.scrollLeft - walk;
    }
}