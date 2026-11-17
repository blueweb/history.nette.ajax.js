// Run with node tests/HistoryTest.js
const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

let extension;
let onPopstate;
const ajaxCalls = [];
const window = { location: { href: 'https://example.test/first', host: 'example.test' } };
const history = {
	state: { href: 'https://example.test/first' },
	pushState() {},
	replaceState(state) { this.state = state; },
};
window.history = history;
const $ = function (target) {
	if (target === window) {
		return { on(name, callback) { assert.strictEqual(name, 'popstate.nette'); onPopstate = callback; } };
	}
	return { each() {} };
};
$.proxy = (callback, context) => callback.bind(context);
$.nette = {
	ext(name, value, defaults) {
		if (arguments.length === 1) return null;
		if (name === 'history') extension = Object.assign(value, defaults);
	},
	ajax(options) { ajaxCalls.push(options); },
};

vm.runInNewContext(fs.readFileSync(require.resolve('../client-side/history.ajax.js'), 'utf8'), {
	jQuery: $, window, history, document: { title: 'Title' },
});
extension.cache = false;
extension.init();
onPopstate({ originalEvent: { state: null } });
assert.strictEqual(ajaxCalls.length, 0, 'Missing native history state must be ignored');
onPopstate({ originalEvent: { state: { href: 'https://example.test/second' } } });
assert.strictEqual(ajaxCalls.length, 1, 'Native history state must be handled');
assert.strictEqual(ajaxCalls[0].url, 'https://example.test/second');
console.log('Popstate backport checks passed.');
