import { parseCsv, readIdentifier } from './transaction-bulk.js';

export function validateHubIdentifiers(text) {
    const rows = parseCsv(text);
    const headers = (rows.shift() || []).map(value => String(value ?? '').trim().toLowerCase());
    const identifierColumns = [
        [headers.indexOf('item id'), 'Item ID'],
        [headers.indexOf('barcode'), 'Barcode'],
    ].filter(([column]) => column >= 0);
    const errors = [];
    rows.forEach((row, index) => {
        for (const [column, label] of identifierColumns) {
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
    let checking = false;
    input.addEventListener('change', () => { alert.classList.add('d-none'); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        event.stopImmediatePropagation();
        if (checking || !input.files.length) return;
        checking = true;
        const file = input.files[0];
        try {
            if (!file.name.toLowerCase().endsWith('.xlsx')) {
                throw new Error('Choose an Excel workbook in .xlsx format.');
            }
            if (input.files[0] !== file) return;
            alert.classList.add('d-none');
            form.dispatchEvent(new CustomEvent('hub-import-approved', { bubbles: true }));
        } catch (error) {
            window.AppAlert.show(error.message, 'error', { title: 'Your file needs a correction', buttonLabel: 'Back to import' });
        } finally { checking = false; }
    }, true);
}
