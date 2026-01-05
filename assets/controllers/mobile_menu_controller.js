import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = ["menu", "toggle"]

  connect() {
    // Close menu when clicking outside
    this.boundCloseOnOutsideClick = this.closeOnOutsideClick.bind(this)
    
    // Close menu when pressing escape key
    this.boundCloseOnEscape = this.closeOnEscape.bind(this)
    
    // Close menu when clicking on menu links
    this.boundCloseOnLinkClick = this.closeOnLinkClick.bind(this)
    
    // Add event listeners for menu links
    this.menuTarget.querySelectorAll('a').forEach(link => {
      if (!link.classList.contains('dropdown-trigger')) {
        link.addEventListener('click', this.boundCloseOnLinkClick)
      }
    })
  }

  disconnect() {
    // Clean up event listeners
    document.removeEventListener('click', this.boundCloseOnOutsideClick)
    document.removeEventListener('keydown', this.boundCloseOnEscape)
    
    this.menuTarget.querySelectorAll('a').forEach(link => {
      if (!link.classList.contains('dropdown-trigger')) {
        link.removeEventListener('click', this.boundCloseOnLinkClick)
      }
    })
  }

  toggle() {
    const isActive = this.menuTarget.classList.contains('active')
    
    if (isActive) {
      this.close()
    } else {
      this.open()
    }
  }

  open() {
    this.menuTarget.classList.add('active')
    this.toggleTarget.classList.add('active')
    
    // Prevent body scrolling when menu is open
    document.body.style.overflow = 'hidden'
    
    // Add event listeners
    setTimeout(() => {
      document.addEventListener('click', this.boundCloseOnOutsideClick)
      document.addEventListener('keydown', this.boundCloseOnEscape)
    }, 100)
  }

  close() {
    this.menuTarget.classList.remove('active')
    this.toggleTarget.classList.remove('active')
    
    // Restore body scrolling
    document.body.style.overflow = ''
    
    // Remove event listeners
    document.removeEventListener('click', this.boundCloseOnOutsideClick)
    document.removeEventListener('keydown', this.boundCloseOnEscape)
  }

  closeOnOutsideClick(event) {
    // Don't close if clicking on the toggle button or menu
    if (!this.element.contains(event.target)) {
      this.close()
    }
  }

  closeOnEscape(event) {
    if (event.key === 'Escape') {
      this.close()
    }
  }

  closeOnLinkClick() {
    // Close menu when a navigation link is clicked
    this.close()
  }

  toggleDropdown(event) {
    if (window.innerWidth <= 768) {
      event.preventDefault()
      const dropdownContent = event.currentTarget.nextElementSibling
      dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block'
      dropdownContent.style.opacity = '1'
      dropdownContent.style.visibility = 'visible'
      dropdownContent.style.position = 'static'
      dropdownContent.style.transform = 'none'
    }
  }
}