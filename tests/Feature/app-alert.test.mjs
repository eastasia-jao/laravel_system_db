import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';
test('popup queues messages, renders them as text, and waits for dismissal', async () => {
    const listeners = {}, dialogListeners = {};
    const title = {}, message = {}, button = { focus() {} };
    let opens = 0;
    const dialog = { dataset: {}, open: false, setAttribute() {}, querySelector: s => ({ h2: title, div: message, button })[s], addEventListener: (name, fn) => { dialogListeners[name] = fn; }, showModal() { this.open = true; opens++; }, close() { this.open = false; dialogListeners.close(); } };
    const context = { window: { addEventListener: (name, fn) => { listeners[name] = fn; } }, document: { readyState: 'loading', addEventListener: (name, fn) => { listeners[name] = fn; }, createElement: () => dialog, body: { appendChild() {} } } };
    vm.runInNewContext(fs.readFileSync('public/js/app-alert.js', 'utf8'), context);
    let acknowledged = false;
    const first = context.window.AppAlert.show('<img src=x onerror=bad()>', 'error').then(() => { acknowledged = true; });
    context.window.AppAlert.show('Saved', 'success', { title: 'Your export is ready', buttonLabel: 'Done' });
    listeners.DOMContentLoaded();
    assert.equal(opens, 1);
    assert.equal(message.textContent, '<img src=x onerror=bad()>');
    assert.equal(acknowledged, false);
    assert.equal(title.textContent, 'Something needs attention');
    assert.equal(button.textContent, 'Got it');
    dialogListeners.close(); await first;
    assert.equal(acknowledged, true);
    assert.equal(opens, 2);
    assert.equal(message.textContent, 'Saved');
    assert.equal(dialog.dataset.type, 'success');
    assert.equal(title.textContent, 'Your export is ready');
    assert.equal(button.textContent, 'Done');
});

test('dismisses queued alerts when a page is restored from the back-forward cache', async () => {
    const listeners = {}, dialogListeners = {};
    const title = {}, message = {}, button = { focus() {} };
    const dialog = { dataset: {}, open: false, setAttribute() {}, querySelector: s => ({ h2: title, div: message, button })[s], addEventListener: (name, fn) => { dialogListeners[name] = fn; }, showModal() { this.open = true; }, close() { this.open = false; dialogListeners.close(); } };
    const context = { window: { addEventListener: (name, fn) => { listeners[name] = fn; } }, document: { readyState: 'loading', addEventListener: (name, fn) => { listeners[name] = fn; }, createElement: () => dialog, body: { appendChild() {} } } };
    vm.runInNewContext(fs.readFileSync('public/js/app-alert.js', 'utf8'), context);

    let activeDismissed = false;
    let queuedDismissed = false;
    const active = context.window.AppAlert.show('Active').then(() => { activeDismissed = true; });
    const queued = context.window.AppAlert.show('Queued').then(() => { queuedDismissed = true; });
    listeners.DOMContentLoaded();
    listeners.pageshow({ persisted: false });
    assert.equal(dialog.open, true);

    listeners.pageshow({ persisted: true });
    await Promise.all([active, queued]);
    assert.equal(dialog.open, false);
    assert.equal(activeDismissed, true);
    assert.equal(queuedDismissed, true);

    context.window.AppAlert.show('Fresh');
    assert.equal(dialog.open, true);
});
