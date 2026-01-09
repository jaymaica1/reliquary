import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['checkbox', 'selectAll', 'selectAllHeader', 'bulkForm', 'bulkImageForm', 'bulkActionInput'];
    static values = {
        confirmFeature: String,
        confirmUnfeature: String,
        confirmBulk: String,
        confirmBulkImage: String,
        noSelection: String,
        loadingText: String
    };

    toggleAll(event) {
        const checked = event.target.checked;
        this.checkboxTargets.forEach(checkbox => {
            checkbox.checked = checked;
        });
        
        // Keep both "select all" checkboxes in sync if they exist
        if (this.hasSelectAllTarget) this.selectAllTarget.checked = checked;
        if (this.hasSelectAllHeaderTarget) this.selectAllHeaderTarget.checked = checked;
    }

    submitBulkAction(event) {
        const action = event.params.action;
        const checkedBoxes = this.checkboxTargets.filter(cb => cb.checked);
        
        if (checkedBoxes.length === 0) {
            alert(this.noSelectionValue);
            return;
        }

        const actionText = action === 'feature' ? 'Feature' : 'Unfeature';
        const confirmMessage = this.confirmBulkValue
            .replace('%action%', actionText)
            .replace('%count%', checkedBoxes.length);

        if (confirm(confirmMessage)) {
            const bulkForm = this.bulkFormTarget;
            
            // Clean up previous hidden inputs if any (to avoid duplicates if user clicks back)
            bulkForm.querySelectorAll('input[type="hidden"][name="saint_ids[]"]').forEach(el => el.remove());

            checkedBoxes.forEach(cb => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'saint_ids[]';
                hiddenInput.value = cb.value;
                bulkForm.appendChild(hiddenInput);
            });

            this.bulkActionInputTarget.value = action;
            bulkForm.submit();
        }
    }

    submitBulkImageGeneration(event) {
        const checkedBoxes = this.checkboxTargets.filter(cb => cb.checked);
        
        if (checkedBoxes.length === 0) {
            alert(this.noSelectionValue);
            return;
        }

        const confirmMessage = this.confirmBulkImageValue.replace('%count%', checkedBoxes.length);

        if (confirm(confirmMessage)) {
            const btn = event.currentTarget;
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${this.loadingTextValue}`;

            const form = this.bulkImageFormTarget;
            form.innerHTML = ''; // clear previous

            checkedBoxes.forEach(cb => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'saint_ids[]';
                hiddenInput.value = cb.value;
                form.appendChild(hiddenInput);
            });

            form.submit();
        }
    }

    prepareSingleGeneration(event) {
        const form = event.currentTarget;
        const btn = form.querySelector('.generate-image-btn');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
}
