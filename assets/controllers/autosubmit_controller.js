import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    submit() {
        this.element.form ? this.element.form.submit() : this.element.submit();
    }
}
