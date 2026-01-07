import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['output'];

    update(event) {
        this.outputTarget.innerText = event.target.value;
    }
}
