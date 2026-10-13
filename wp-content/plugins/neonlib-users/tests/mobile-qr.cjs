const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const assets = path.join(__dirname, '../assets/js');
class Element {
    constructor(tag = 'div') { this.tag = tag; this.attrs = {}; this.children = []; this.dataset = {}; this.firstChild = {nodeValue: '--:--'}; }
    setAttribute(key, value) { this.attrs[key] = value; }
    appendChild(child) { this.children.push(child); }
    replaceChildren(...children) { this.children = children; }
}
function fixture(loadLibrary) {
    const nodes = {};
    for (const name of ['pairing', 'ready', 'status', 'summary', 'countdown', 'qr']) nodes[name] = new Element();
    nodes.qr.dataset.payload = JSON.stringify({type: 'neonlib.mobile-transfer', version: 1, sessionId: 'a'.repeat(32), pairingToken: 'b'.repeat(64), apiBaseUrl: 'https://neonlib-api.neonbreak.com'});
    const panel = {dataset: {status: 'WAITING', expires: new Date(Date.now() + 900000).toISOString()}, querySelector: (selector) => nodes[selector.split('-').at(-1)]};
    const timers = [];
    const context = vm.createContext({document: {documentElement: {lang: 'en'}, querySelectorAll: () => [panel], createElementNS: (_, tag) => new Element(tag)}, window: {addEventListener() {}}, setTimeout: (fn) => {timers.push(fn); return 1;}, clearTimeout() {}, setInterval: () => 1, clearInterval() {}});
    if (loadLibrary) vm.runInContext(fs.readFileSync(path.join(assets, 'qrcodegen.js'), 'utf8'), context);
    vm.runInContext(fs.readFileSync(path.join(assets, 'mobile-transfer.js'), 'utf8'), context);
    return {nodes, context, timers};
}
const loaded = fixture(true);
const svg = loaded.nodes.qr.children[0];
assert.equal(svg.tag, 'svg');
assert.equal(loaded.nodes.qr.hidden, false);
assert.equal(svg.children[0].attrs.fill, '#fff');
assert.equal(svg.children[1].attrs.fill, '#000');
const qr = loaded.context.qrcodegen.QrCode.encodeText(loaded.nodes.qr.dataset.payload, loaded.context.qrcodegen.QrCode.Ecc.MEDIUM);
let expected = '';
for (let y = 0; y < qr.size; y++) for (let x = 0; x < qr.size; x++) if (qr.getModule(x, y)) expected += `M${x + 4},${y + 4}h1v1h-1z`;
assert.equal(svg.children[1].attrs.d, expected);
assert.equal(svg.attrs.viewBox, `0 0 ${qr.size + 8} ${qr.size + 8}`);
const delayed = fixture(false);
assert.equal(delayed.nodes.qr.hidden, false);
assert.match(delayed.nodes.qr.textContent, /QR code could not load/);
vm.runInContext(fs.readFileSync(path.join(assets, 'qrcodegen.js'), 'utf8'), delayed.context);
delayed.timers[0]();
assert.equal(delayed.nodes.qr.children[0].tag, 'svg');
console.log('PASS: SVG matches QR modules and quiet zone; unavailable generator stays visible and recovers after loading.');
