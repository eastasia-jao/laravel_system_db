import './bootstrap';

import Alpine from 'alpinejs';
import html2canvas from 'html2canvas';

window.Alpine = Alpine;
window.html2canvas = html2canvas;

Alpine.start();

const updateCharacterCounter = field => {
    const counter = field.parentElement?.querySelector('[data-character-count-current]');
    if (counter) counter.textContent = String(field.value.length);
};

document.querySelectorAll('[data-character-count]').forEach(updateCharacterCounter);
document.addEventListener('input', event => {
    if (event.target.matches?.('[data-character-count]')) updateCharacterCounter(event.target);
});
document.addEventListener('reset', event => {
    window.setTimeout(() => event.target.querySelectorAll?.('[data-character-count]').forEach(updateCharacterCounter));
});

const lockedForms = new WeakSet();
const lockedSubmitControls = new WeakMap();
const submissionIdFieldName = '_submission_id';

const isMutatingForm = form => form instanceof HTMLFormElement
    && !['GET', 'DIALOG'].includes(form.method.toUpperCase());

const newSubmissionId = () => {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
        const value = Math.floor(Math.random() * 16);
        return (character === 'x' ? value : (value & 0x3) | 0x8).toString(16);
    });
};

const ensureSubmissionId = form => {
    let field = form.querySelector(`input[name="${submissionIdFieldName}"]`);
    if (!field) {
        field = document.createElement('input');
        field.type = 'hidden';
        field.name = submissionIdFieldName;
        form.prepend(field);
    }
    if (!field.value) field.value = newSubmissionId();
};

const prepareSubmissionForm = form => {
    if (isMutatingForm(form)) ensureSubmissionId(form);
};

const lockSubmissionForm = (form, submitter = null) => {
    if (!isMutatingForm(form) || lockedForms.has(form)) return false;

    lockedForms.add(form);
    ensureSubmissionId(form);
    form.setAttribute('aria-busy', 'true');

    const controls = [...form.querySelectorAll(
        'button:not([type]), button[type="submit"], input[type="submit"], input[type="image"]'
    )];
    lockedSubmitControls.set(form, controls.map(control => [control, control.disabled]));

    if (submitter?.name && submitter.type !== 'image') {
        const submittedValue = document.createElement('input');
        submittedValue.type = 'hidden';
        submittedValue.name = submitter.name;
        submittedValue.value = submitter.value;
        submittedValue.dataset.submissionSubmitter = 'true';
        form.append(submittedValue);
    }

    controls.forEach(control => { control.disabled = true; });
    return true;
};

const unlockSubmissionForm = form => {
    if (!isMutatingForm(form)) return;

    lockedForms.delete(form);
    lockedSubmitControls.get(form)?.forEach(([control, wasDisabled]) => {
        control.disabled = wasDisabled;
    });
    lockedSubmitControls.delete(form);
    form.removeAttribute('aria-busy');
    form.querySelectorAll('[data-submission-submitter]').forEach(field => field.remove());
    const submissionId = form.querySelector(`input[name="${submissionIdFieldName}"]`);
    if (submissionId) submissionId.value = newSubmissionId();
};

document.querySelectorAll('form').forEach(prepareSubmissionForm);
new MutationObserver(records => {
    records.forEach(record => {
        record.addedNodes.forEach(node => {
            if (!(node instanceof Element)) return;
            if (node.matches('form')) prepareSubmissionForm(node);
            node.querySelectorAll('form').forEach(prepareSubmissionForm);
        });
    });
}).observe(document.documentElement, { childList: true, subtree: true });

document.addEventListener('submit', event => {
    const form = event.target;
    if (!isMutatingForm(form) || event.defaultPrevented) return;

    if (!lockSubmissionForm(form, event.submitter)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
});

document.addEventListener('reset', event => {
    window.setTimeout(() => unlockSubmissionForm(event.target));
});

window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    document.querySelectorAll('form').forEach(form => {
        unlockSubmissionForm(form);
        prepareSubmissionForm(form);
    });
});

const nativeFormSubmit = HTMLFormElement.prototype.submit;
HTMLFormElement.prototype.submit = function () {
    if (isMutatingForm(this) && !lockSubmissionForm(this)) return;
    nativeFormSubmit.call(this);
};
