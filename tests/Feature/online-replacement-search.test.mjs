import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

function picker() {
    const element = () => ({ value: '', listeners: {}, addEventListener(name, fn) { this.listeners[name] = fn; }, setCustomValidity() {}, reportValidity() {} });
    const search = element(), productId = element(), form = element(), help = element(), replacementPrice = element(), discountInput = element();
    const options = { children: [], replaceChildren() { this.children = []; }, append(value) { this.children.push(value); } };
    const modal = Object.assign(element(), { dataset: { originalProductId: '1' }, querySelector: selector => ({ form, '[data-replacement-search]': search, '[name="replacement_product_id"]': productId, datalist: options, '[data-replacement-stock]': help, '[data-replacement-price]': replacementPrice, '[name="replacement_discount_percentage"]': discountInput })[selector] });
    let pending;
    const requests = [];
    const source = fs.readFileSync('resources/views/hubs/reports/online.blade.php', 'utf8').split('<script>')[1].split('</script>')[0].replace(/@json\([^\n]+\)/, '"/products/search"');
    vm.runInNewContext(source, {
        document: { querySelectorAll: selector => selector === '[data-online-replacement-modal]' ? [modal] : [], createElement: element },
        URL, location: { origin: 'http://localhost' }, window: {},
        setTimeout(fn) { pending = fn; return 1; }, clearTimeout() { pending = null; },
        fetch: url => new Promise(resolve => requests.push({ url, resolve: results => resolve({ ok: true, json: async () => results }) })),
    });
    return { search, productId, options, help, requests, type(value) { search.value = value; search.listeners.input(); }, run() { return pending(); } };
}

test('Online replacement search shows in-stock matches even without Online allocation, like Wholesale', async () => {
    const p = picker();
    p.type('ITEM');
    const work = p.run();
    assert.equal(p.requests[0].url.searchParams.get('stock_channel'), 'online');
    p.requests[0].resolve([
        { id: 1, name: 'Original', stock: 5, channel_available_stock: 5 },
        { id: 2, item_id: 'ITEM-2', name: 'Replacement', stock: 5, channel_available_stock: 0 },
        { id: 3, name: 'Empty', stock: 0, channel_available_stock: 0 },
    ]);
    await work;
    assert.equal(p.options.children.length, 1);
    assert.match(p.options.children[0].value, /ITEM-2.*Replacement.*Online stock: 0/);
    p.type(p.options.children[0].value);
    assert.equal(p.productId.value, 2);
    p.type('');
    assert.equal(p.productId.value, '');
    assert.equal(p.options.children.length, 0);
});

test('outdated responses cannot overwrite a newer search or its empty result message', async () => {
    const p = picker();
    p.type('old');
    const old = p.run();
    p.type('new');
    const latest = p.run();
    p.requests[1].resolve([]);
    await latest;
    p.requests[0].resolve([{ id: 2, name: 'Old product', stock: 4 }]);
    await old;
    assert.equal(p.options.children.length, 0);
    assert.match(p.help.textContent, /No matching products/);
});
