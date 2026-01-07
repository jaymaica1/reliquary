import { Controller } from '@hotwired/stimulus';

/**
 * Admin Base controller
 *
 * This controller manages the admin panel functionality including:
 * - Sidebar toggle
 * - Responsive sidebar behavior
 * - Navigation handling for sidebar links
 */
export default class extends Controller {
    static targets = ['sidebar', 'mainContent', 'toggleButton'];

    connect() {
        this.handleResize = this.handleResize.bind(this);
        window.addEventListener('resize', this.handleResize);
        this.handleResize(); // Initialize on page load
        this.setupNavigation();
    }

    disconnect() {
        window.removeEventListener('resize', this.handleResize);
    }

    toggleSidebar() {
        if (window.innerWidth < 768) {
            this.sidebarTarget.classList.toggle('show');
        } else {
            this.sidebarTarget.classList.toggle('sidebar-collapsed');
            this.mainContentTarget.classList.toggle('expanded');
        }
    }

    handleResize() {
        if (window.innerWidth >= 768) {
            this.sidebarTarget.classList.remove('show');
        } else {
            this.sidebarTarget.classList.remove('sidebar-collapsed');
            this.mainContentTarget.classList.remove('expanded');
        }
    }

    setupNavigation() {
        // Handle sidebar navigation links
        this.element.querySelectorAll('#sidebar .nav-link[data-href]').forEach(btn => {
            btn.addEventListener('click', this.handleNavClick.bind(this));
            btn.addEventListener('keydown', this.handleNavKeydown.bind(this));
        });
    }

    handleNavClick(event) {
        const href = event.currentTarget.getAttribute('data-href');
        if (href) {
            window.location.href = href;
        }
    }

    handleNavKeydown(event) {
        if ((event.key === 'Enter' || event.key === ' ') && event.currentTarget.getAttribute('data-href')) {
            event.preventDefault();
            window.location.href = event.currentTarget.getAttribute('data-href');
        }
    }

    confirm(event) {
        const message = event.currentTarget.dataset.adminBaseConfirmValue || 'Are you sure?';
        if (!window.confirm(message)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return false;
        }
    }
}