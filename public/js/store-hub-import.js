import { parseCsv, readIdentifier } from './transaction-bulk.js';

export function validateHubIdentifiers(text) {
    const rows = parseCsv(text);
    const errors = [];
    rows.slice(1).forEach((row, index) => {
        for (const [column, label] of [[1, 'Item ID'], [4, 'Barcode']]) {
            try { readIdentifier(row[column]); }
            catch (error) { errors.push(`Row ${index + 2} ${label}: ${error.message}`); }
        }
    });
    if (errors.length) throw new Error('Import stopped. Set Barcode and Item ID columns to Text and re-enter the original full codes before saving as CSV.\n' + errors.join('\n'));
}

export function setupHubImport() {
    const form = document.getElementById('storeHubImportForm');
    if (!form) return;
    const input = form.querySelector('[name="file"]');
    const alert = document.getElementById('storeHubImportError');
    document.getElementById('importProductModal').addEventListener('shown.bs.modal', () => {
        window.AppAlert.show('Keep Barcode and Item ID as Text. Set these columns to Text before entering or pasting the original full codes, then save as CSV.\n\nCSV does not retain cell formatting, so we check values for scientific notation. Changing cell format alone cannot restore missing digits or leading zeros.', 'info', { title: 'Before you import', buttonLabel: 'Continue to import' });
    });
    let checking = false;
    input.addEventListener('change', () => { alert.classList.add('d-none'); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        event.stopImmediatePropagation();
        if (checking || !input.files.length) return;
        checking = true;
        const file = input.files[0];
        try {
            validateHubIdentifiers(await file.text());
            if (input.files[0] !== file) return;
            alert.classList.add('d-none');
            form.dispatchEvent(new CustomEvent('hub-import-approved', { bubbles: true }));
        } catch (error) {
            window.AppAlert.show(error.message, 'error', { title: 'Your file needs a correction', buttonLabel: 'Back to import' });
        } finally { checking = false; }
    }, true);
}
