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
