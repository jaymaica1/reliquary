import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import UserLocationService from '../services/user_location.js';

// Fix for marker icon issues
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png'
});

export default class extends Controller {
    static targets = ['container'];
    static values = {
        relics: Array,
        radius: Number,
        userLocation: { type: Object, default: null },
        translationTitle: String,
        translationMessage: String,
        translationButton: String
    };

    connect() {
        if (!this.hasContainerTarget) {
            console.error('Map container target not found');
            return;
        }

        // Check for consent before initializing third-party map
        if (!this.hasPreferenceConsent()) {
            this.showConsentRequiredMessage();
            
            // Listen for consent updates
            document.addEventListener('cookie-consent:consent-updated', (event) => {
                if (event.detail.preferences.preferences) {
                    this.clearConsentMessage();
                    this.initialize();
                }
            });
            return;
        }

        this.initialize();
    }

    initialize() {
        // Load Leaflet CSS
        if (!document.querySelector('link[href*="leaflet.css"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            link.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
            link.crossOrigin = '';
            document.head.appendChild(link);
        }

        // Initialize the map
        this.initializeMap();

        // If we have user location from the backend, use it
        if (this.hasUserLocationValue) {
            this.centerMapOnLocation(
                this.userLocationValue.latitude,
                this.userLocationValue.longitude
            );
        } else {
            // Only request browser geolocation if we don't have it from the backend
            this.requestUserLocation();
        }
    }

    hasPreferenceConsent() {
        if (window.cookieConsent) {
            return window.cookieConsent.preferences === true;
        }
        
        // Fallback to cookie check if window object not yet populated
        const nameEQ = "cookie_consent=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) {
                try {
                    const preferences = JSON.parse(decodeURIComponent(c.substring(nameEQ.length, c.length)));
                    return preferences.preferences === true;
                } catch (e) {
                    return false;
                }
            }
        }
        return false;
    }

    showConsentRequiredMessage() {
        this.containerTarget.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center p-5 bg-light border rounded text-center" style="min-height: 300px;">
                <i class="fas fa-map-marked-alt fa-3x mb-3 text-secondary"></i>
                <h5>${this.translationTitleValue || 'Map requires consent'}</h5>
                <p>${this.translationMessageValue || 'To view the interactive map, please enable "Preferences" in the cookie settings.'}</p>
                <button class="btn btn-primary btn-sm" data-action="click->cookie-consent#showBanner">
                    ${this.translationButtonValue || 'Adjust Settings'}
                </button>
            </div>
        `;
    }

    clearConsentMessage() {
        this.containerTarget.innerHTML = '';
    }
    
    // Method to center map on a location
    centerMapOnLocation(latitude, longitude) {
        if (this.map) {
            if (this.radiusValue) {
                const center = L.latLng(latitude, longitude);
                const circle = L.circle(center, {
                    radius: this.radiusValue * 1000
                });
                this.map.fitBounds(circle.getBounds(), { padding: [20, 20] });
            } else {
                this.map.setView([latitude, longitude], 13);
            }
        }
    }

    initializeMap() {
        // Default view centered on Vatican City if no relics with coordinates
        let defaultLat = 41.9022;
        let defaultLng = 12.4539;
        let defaultZoom = 13;

        // Create the map
        this.map = L.map(this.containerTarget).setView([defaultLat, defaultLng], defaultZoom);

        // Add the OpenStreetMap tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(this.map);

        // Add a circle to represent the search radius
        if (this.hasRelicsValue && this.relicsValue.length > 0) {
            this.addRelicMarkers();
        } else {
            // Add a message if no relics found
            const noRelicsMessage = L.control({ position: 'bottomleft' });
            noRelicsMessage.onAdd = function() {
                const div = L.DomUtil.create('div', 'no-relics-message');
                div.innerHTML = '<div class="alert alert-info">No relics found within the search radius.</div>';
                return div;
            };
            noRelicsMessage.addTo(this.map);
        }
    }

    addRelicMarkers() {
        // Find relics with valid coordinates
        const relicsWithCoords = this.relicsValue.filter(relic => 
            relic.latitude !== null && relic.longitude !== null
        );

        // Create a bounds object to fit markers and radius
        const bounds = L.latLngBounds();

        // Add markers for each relic
        relicsWithCoords.forEach(relic => {
            const marker = L.marker([relic.latitude, relic.longitude]).addTo(this.map);

            // Add popup with relic info
            marker.bindPopup(`
                <strong>${relic.saint}</strong><br>
                ${relic.address || ''}<br>
                ${relic.location || ''}<br>
                <a href="/relic/${relic.id}" class="btn btn-sm btn-primary mt-2">View Details</a>
            `);

            // Extend bounds to include this marker
            bounds.extend([relic.latitude, relic.longitude]);
        });

        // Use user location for the circle center if available, otherwise use bounds center
        let center;
        if (this.hasUserLocationValue) {
            center = L.latLng(this.userLocationValue.latitude, this.userLocationValue.longitude);
        } else if (relicsWithCoords.length > 0) {
            center = bounds.getCenter();
        }

        if (center) {
            // Add a circle to show the search radius
            const circle = L.circle(center, {
                radius: this.radiusValue * 1000, // Convert km to meters
                color: 'blue',
                fillColor: '#30f',
                fillOpacity: 0.1
            }).addTo(this.map);

            // Extend bounds to include the circle
            bounds.extend(circle.getBounds());
        }

        // Fit the map to show all markers and the circle
        if (bounds.isValid()) {
            this.map.fitBounds(bounds, { padding: [20, 20] });
        }
    }

    requestUserLocation() {
        // Double check consent before using browser geolocation
        if (!this.hasPreferenceConsent()) {
            return;
        }

        // Use the UserLocationService to get the user's location
        UserLocationService.getCurrentPosition(
            // Success callback
            (position) => {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;

                // Center the map on the user's location
                this.centerMapOnLocation(userLat, userLng);
            },
            // Error callback is handled by the service
            null,
            // Options
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0,
                // Skip storage if we already have location from the backend
                skipStorage: this.hasUserLocationValue
            }
        ).catch(error => {
            // Additional error handling if needed
            console.log('Error getting user location:', error);
        });
    }
}
