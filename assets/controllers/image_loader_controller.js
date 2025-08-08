import { Controller } from '@hotwired/stimulus';

/**
 * Image loader controller
 *
 * Shows a skeleton while an image loads. Hides the actual image until the
 * browser fires the `load` event on the <img> element, then reveals the image
 * and hides the skeleton. If the image is already cached/complete, it will
 * immediately show the image.
 */
export default class extends Controller {
    static targets = ['image', 'skeleton'];

    connect() {
        // If the image has already loaded (from cache) and is valid, show it now
        if (this.imageTarget.complete && this.imageTarget.naturalWidth !== 0) {
            this.showImage();
            this.hideSkeleton();
            return;
        }

        // Otherwise ensure the image is hidden while loading and skeleton is visible
        this.hideImage();
        this.showSkeleton();
    }

    // Stimulus action triggered by the <img> load event: data-action="load->image-loader#imageLoaded"
    imageLoaded() {
        this.showImage();
        this.hideSkeleton();
    }

    // Optional: handle error state if desired in the future
    imageError() {
        // Keep skeleton visible or swap to a fallback UI here if needed
        // For now, just keep the skeleton and ensure image stays hidden
        this.hideImage();
        this.showSkeleton();
    }

    hideSkeleton() {
        this.skeletonTarget.classList.add('d-none');
    }

    showSkeleton() {
        this.skeletonTarget.classList.remove('d-none');
    }

    showImage() {
        this.imageTarget.classList.remove('d-none');
    }

    hideImage() {
        this.imageTarget.classList.add('d-none');
    }
}