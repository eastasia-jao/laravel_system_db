import { test } from 'node:test';
import assert from 'node:assert/strict';
import { importRows, csvText, parseCsv, exportFilename, findReviewProductRow } from '../../public/js/transaction-bulk.js';
const products = [{ id: 1, item_id: '001', barcode: '009', name: 'Test' }];
test('imports 1500 rows with identifiers preserved', () => {
    const csv = csvText([['Item ID', 'Barcode', 'Actual Pull out Qty'], ...Array.from({ length: 1500 }, () => ['001', '009', 2])]);
    assert.equal(importRows(csv, products, 'stock_transfer').length, 1500);
});
test('rejects missing, conflicting, ambiguous and invalid quantity rows', () => {
    for (const row of [['missing', '', 2], ['001', 'wrong', 2], ['001', '009', -1], ['001', '009', 1.5]]) {
        assert.throws(() => importRows(csvText([['Item ID', 'Barcode', 'Quantity'], row]), products, 'restock'), /Row 2/);
    }
    assert.throws(() => importRows('Barcode,Quantity\n009,2', [...products, { id: 2, barcode: '009' }], 'return'), /ambiguous/);
});
test('returns require explicit conditions and skip blank worksheet quantities', () => {
    assert.throws(() => importRows('Item ID,Quantity\n001,2', products, 'return'), /Condition/);
    assert.deepEqual(importRows('Item ID,Quantity,Condition\n001,,\n001,2,damaged', products, 'return'), [{ product_id: 1, quantity: 2, condition: 'damaged' }]);
});
test('CSV handles commas, multiline descriptions, BOM and escaped quotes', () => {
    const rows = [['Name', 'Item ID'], ['A, "quoted"\nname', '001']];
    assert.deepEqual(parseCsv(csvText(rows)), rows);
    assert.throws(() => parseCsv('"unfinished'), /unclosed/);
    assert.match(csvText([['=1+2']]), /'=1\+2/);
});
test('Excel text identifiers preserve leading zeros and long barcodes for every transaction type', () => {
    const barcode = '007661234567891234567890';
    const catalog = [{ id: 1, item_id: '0001', barcode }];
    const csv = csvText([['Item ID', 'Barcode', 'Quantity', 'Condition'], ['0001', barcode, 2, 'good']], [0, 1]);
    assert.equal(parseCsv(csv)[1][1], `="${barcode}"`);
    for (const type of ['stock_transfer', 'branch_transfer', 'sponsor_workshop', 'restock', 'return']) {
        assert.equal(importRows(csv, catalog, type)[0].product_id, 1);
        assert.match(exportFilename(type, 'AC CUBAO', new Date('2026-09-10T12:34:56.789Z')), /^AC_CUBAO_Exp_.*_20260910_123456\.csv$/);
    }
    assert.match(exportFilename('stock_transfer', 'AC BRANCH', new Date('2026-09-10T12:34:56.789Z'), 'branch_to_ho'), /^AC_BRANCH_Exp_StockTrf_B2HO_20260910_123456\.csv$/);
    assert.throws(() => importRows('Barcode,Quantity\n7.66e+11,2', catalog, 'restock'), /Row 2:.*scientific notation/);
    assert.throws(() => csvText([['Barcode'], ['7.66e+11']], [0]), /scientific notation/);
});
test('reviewing a stock error locates the matching product item row', () => {
    const first = { querySelector: () => ({ value: '1 — Brush · 009 (stock: 4)' }) };
    const second = { querySelector: () => ({ value: '2 — Fredrix Stretched Canvas Roll Label 16x20 · 81702050227 (stock: 0)' }) };
    assert.equal(findReviewProductRow([first, second], 'Not enough stock for Fredrix Stretched Canvas Roll Label 16x20.'), second);
    assert.equal(findReviewProductRow([first, second], 'Select a valid target store.'), null);
});
