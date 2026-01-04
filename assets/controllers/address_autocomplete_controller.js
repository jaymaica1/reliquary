import { Controller } from '@hotwired/stimulus';
import UserLocationService from '../services/user_location.js';

export default class extends Controller {
    static targets = ['input', 'results', 'lat', 'lng'];

    connect() {
        this.timeout = null;
    }

    onInput() {
        clearTimeout(this.timeout);
        const query = this.inputTarget.value;

        if (query.length < 3) {
            this.hideResults();
            return;
        }

        this.timeout = setTimeout(() => {
            this.search(query);
        }, 300);
    }

    async search(query) {
        try {
            const response = await fetch(`/api/address-autocomplete?query=${encodeURIComponent(query)}`);
            const data = await response.json();
            this.showResults(data.results);
        } catch (error) {
            console.error('Error fetching address suggestions:', error);
        }
    }

    showResults(results) {
        this.resultsTarget.innerHTML = '';
        if (results.length === 0) {
            this.hideResults();
            return;
        }

        results.forEach(result => {
            const div = document.createElement('div');
            div.className = 'autocomplete-result-item';
            div.textContent = result.text;
            div.addEventListener('click', () => this.selectResult(result));
            this.resultsTarget.appendChild(div);
        });

        this.resultsTarget.classList.remove('d-none');
    }

    hideResults() {
        this.resultsTarget.classList.add('d-none');
    }

    selectResult(result) {
        this.inputTarget.value = result.text;
        this.latTarget.value = result.lat;
        this.lngTarget.value = result.lon;
        this.hideResults();
        
        this.inputTarget.form.submit();
    }

    getCurrentLocation(event) {
        event.preventDefault();
        const icon = event.currentTarget.querySelector('i');
        icon.classList.add('fa-spin');

        UserLocationService.getCurrentPosition(
            (position) => {
                this.latTarget.value = position.coords.latitude;
                this.lngTarget.value = position.coords.longitude;
                this.inputTarget.value = 'Current Location';
                icon.classList.remove('fa-spin');
                this.inputTarget.form.submit();
            },
            (error) => {
                console.error('Error getting location:', error);
                icon.classList.remove('fa-spin');
                alert('Unable to get your location. Please check your browser settings.');
            }
        );
    }
}
