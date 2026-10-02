(() => {
    const queue = [];
    let dialog, message, title, button, active;
    function next() {
        if (!dialog || active || !queue.length) return;
        active = queue.shift();
        const titles = { success: 'All done', error: 'Something needs attention', warning: 'Before you continue', info: 'Good to know' };
        title.textContent = active.options.title || titles[active.type] || titles.info;
        button.textContent = active.options.buttonLabel || (active.type === 'success' ? 'Done' : 'Got it');
        dialog.dataset.type = active.type;
        dialog.dataset.longMessage = String(active.text.length > 240 || active.text.split('\n').length > 2);
        message.textContent = active.text;
        // Flush the closed state so queued alerts each replay their entrance.
        void dialog.offsetWidth;
        dialog.showModal();
        button.focus();
    }
    function initialize() {
        if (dialog) {
            next();
            return;
        }
        dialog = document.createElement('dialog');
        dialog.className = 'app-alert-dialog';
        dialog.setAttribute('aria-labelledby', 'app-alert-title');
        dialog.setAttribute('aria-describedby', 'app-alert-message');
        dialog.innerHTML = '<span class="app-alert-icon" aria-hidden="true"><svg class="app-alert-warning" viewBox="0 0 48 48"><path fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" d="M21 8 Q24 3 27 8 L44 37 Q46 42 40 42 H8 Q2 42 5 37 Z"/><path stroke="currentColor" stroke-width="3" stroke-linecap="round" d="M24 17 V27"/><circle fill="currentColor" cx="24" cy="34" r="1.8"/></svg><svg class="app-alert-success" viewBox="0 0 48 48"><circle fill="none" stroke="currentColor" stroke-width="2.5" cx="24" cy="24" r="19"/><path fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M15 24 L21 30 L33 18"/></svg><svg class="app-alert-info" viewBox="0 0 48 48"><circle fill="none" stroke="currentColor" stroke-width="2.5" cx="24" cy="24" r="19"/><path stroke="currentColor" stroke-width="3" stroke-linecap="round" d="M24 23 V33"/><circle fill="currentColor" cx="24" cy="15" r="1.8"/></svg></span><span class="app-alert-eyebrow">ART CARAVAN PH</span><h2 id="app-alert-title"></h2><div id="app-alert-message"></div><form method="dialog"><button autofocus>Got it</button></form>';
        document.body.appendChild(dialog);
        title = dialog.querySelector('h2'); message = dialog.querySelector('div'); button = dialog.querySelector('button');
        dialog.addEventListener('focusin', event => event.stopPropagation());
        dialog.addEventListener('close', () => {
            const finished = active; active = null; finished?.resolve(); next();
        });
        next();
    }

    window.AppAlert = {
        show(text, type = 'info', options = {}) {
            return new Promise(resolve => {
                queue.push({ text: String(text), type, options, resolve });
                initialize();
            });
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
