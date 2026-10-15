import test from 'node:test';
import assert from 'node:assert/strict';
import { validateHubIdentifiers } from '../../public/js/store-hub-import.js';
import { csvText } from '../../public/js/transaction-bulk.js';

test('head office CSV validates identifiers with a blank database ID column', () => {
    const csv = csvText([
        ['ID', 'Item ID', 'Name', 'Description', 'Barcode', 'Stock'],
        ['', '000012345678901234567890', 'Brush', 'Paint brush', '00007661234567891234567890', 8],
    ], [1, 4]);
    assert.doesNotThrow(() => validateHubIdentifiers(csv));
});

test('branch CSV still validates identifiers with the database ID column present', () => {
    const csv = csvText([
        ['ID', 'Item ID', 'Name', 'Description', 'Barcode', 'Stock'],
        [10, '00001', 'Brush', 'Paint brush', '007661234567891234567890', 8],
    ], [1, 4]);
    assert.doesNotThrow(() => validateHubIdentifiers(csv));
});

test('scientific notation is rejected using the detected barcode column', () => {
    assert.throws(
        () => validateHubIdentifiers('Item ID,Name,Barcode\r\nSKU-1,Brush,7.66e+11'),
        /Row 2 Barcode: Identifier 7\.66e\+11 uses scientific notation/
    );
});
