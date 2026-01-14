import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['banner', 'settings', 'category'];

    connect() {
        const consent = this.getCookie('cookie_consent');
        if (!consent) {
            this.bannerTarget.classList.remove('d-none');
        } else {
            // Apply existing consent
            try {
                const preferences = JSON.parse(consent);
                this.applyConsent(preferences);
            } catch (e) {
                this.bannerTarget.classList.remove('d-none');
            }
        }
    }

    applyConsent(preferences) {
        // This can be used to initialize things that depend on consent
        window.cookieConsent = preferences;
        
        // Dispatch event for other controllers to react
        this.dispatch('consent-updated', { detail: { preferences } });
    }

    acceptAll() {
        this.saveConsent({
            essential: true,
            analytics: true,
            preferences: true
        });
    }

    acceptEssential() {
        this.saveConsent({
            essential: true,
            analytics: false,
            preferences: false
        });
    }

    savePreferences() {
        const preferences = {
            essential: true,
            analytics: false,
            preferences: false
        };

        this.categoryTargets.forEach(checkbox => {
            preferences[checkbox.value] = checkbox.checked;
        });

        this.saveConsent(preferences);
    }

    saveConsent(preferences) {
        this.setCookie('cookie_consent', JSON.stringify(preferences), 365);
        this.bannerTarget.classList.add('d-none');
        
        // Log consent event to server anonymously
        this.logConsentToServer(preferences);

        this.applyConsent(preferences);
    }

    async logConsentToServer(preferences) {
        try {
            await fetch('/gdpr/log-consent', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    preferences: preferences,
                    action: 'update'
                })
            });
        } catch (e) {
            console.error('Failed to log consent to server', e);
        }
    }

    toggleSettings() {
        this.settingsTarget.classList.toggle('d-none');
    }

    showBanner(event) {
        if (event) event.preventDefault();
        this.bannerTarget.classList.remove('d-none');
        this.settingsTarget.classList.remove('d-none');
        
        // Pre-fill checkboxes based on current consent
        const consent = this.getCookie('cookie_consent');
        if (consent) {
            try {
                const preferences = JSON.parse(consent);
                this.categoryTargets.forEach(checkbox => {
                    checkbox.checked = preferences[checkbox.value] || false;
                });
            } catch (e) {}
        }

        this.bannerTarget.scrollIntoView({ behavior: 'smooth' });
    }

    setCookie(name, value, days) {
        let expires = "";
        if (days) {
            let date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/; SameSite=Lax";
    }

    getCookie(name) {
        let nameEQ = name + "=";
        let ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
        }
        return null;
    }
}
