import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['provider', 'model', 'pricing', 'pricingContainer'];
    static values = {
        modelsByProvider: Object,
        currentModel: String
    };

    connect() {
        this.updateModels();
    }

    updateModels() {
        const provider = this.providerTarget.value;
        const models = this.modelsByProviderValue[provider] || {};
        const modelSelect = this.modelTarget;
        
        modelSelect.innerHTML = '';
        
        for (const [id, data] of Object.entries(models)) {
            const option = document.createElement('option');
            option.value = id;
            option.text = data.name;
            if (id === this.currentModelValue) {
                option.selected = true;
            }
            modelSelect.appendChild(option);
        }

        this.updatePricing();
    }

    updatePricing() {
        const provider = this.providerTarget.value;
        const modelId = this.modelTarget.value;
        const models = this.modelsByProviderValue[provider] || {};
        const modelData = models[modelId];

        if (modelData && modelData.pricing) {
            this.pricingTarget.textContent = modelData.pricing;
            this.pricingContainerTarget.style.display = 'block';
        } else {
            this.pricingContainerTarget.style.display = 'none';
        }
    }
}
