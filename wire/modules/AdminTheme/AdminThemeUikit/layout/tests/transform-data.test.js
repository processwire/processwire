/* Run with: node wire/modules/AdminTheme/AdminThemeUikit/layout/tests/transform-data.test.js */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

for (const filename of ['jquery.layout.js', 'jquery.layout_and_plugins.js']) {
	const source = fs.readFileSync(path.join(__dirname, '../source/stable', filename), 'utf8');
	const match = source.match(/\$\.layout\.transformData = function \(hash, addKeys\) \{[\s\S]*?\n\};/);
	assert.ok(match, 'layout transform helper exists');
	const stateSource = filename === 'jquery.layout.js' ? fs.readFileSync(
		path.join(__dirname, '../source/stable/plugins/jquery.layout.state.js'), 'utf8') : source;
	const cookieMatch = stateSource.match(/loadCookie: function \(inst\) \{[\s\S]*?\n\t\}/);
	assert.ok(cookieMatch, 'state cookie helper exists');
	function run(script) {
		const context = vm.createContext({});
		vm.runInContext(`var $ = {
			layout: {},
			inArray: function(value, array) { return array.indexOf(value); },
			isPlainObject: function(value) { return Object.prototype.toString.call(value) === '[object Object]'; }
		};`, context);
		vm.runInContext(match[0], context);
		vm.runInContext('$.layout.state = {}; $.layout.state.loadCookie = ' + cookieMatch[0].replace('loadCookie: ', '') + ';', context);
		return JSON.parse(vm.runInContext('JSON.stringify((function() {' + script + '})())', context));
	}
	assert.deepEqual(run(`return $.layout.transformData({west__size: 240, east__isClosed: false});`), {
		west: {size: 240}, east: {isClosed: false}
	});
	assert.deepEqual(run(`return $.layout.transformData({west: {size: 240}, center__children__example__size: 100}, true);`), {
		panes: {}, center: {children: {example: {size: 100}}}, west: {size: 240}
	});
	for (const data of [
		{'constructor__prototype__layoutTestMarker': 'synthetic-marker'},
		{'west__constructor__prototype__layoutTestMarker': 'synthetic-marker'},
		{'prototype__layoutTestMarker': 'synthetic-marker'},
		JSON.parse('{"__proto__":{"layoutTestMarker":"synthetic-marker"}}'),
		{west: {'constructor__prototype__layoutTestMarker': 'synthetic-marker'}}
	]) {
		const literal = JSON.stringify(JSON.stringify(data));
		assert.deepEqual(run(`var result = $.layout.transformData(JSON.parse(${literal}));
			return {clean: ({}).layoutTestMarker === undefined,
				constructorUnchanged: result.constructor === Object,
				prototypeUnchanged: Object.getPrototypeOf(result) === Object.prototype};`), {
			clean: true, constructorUnchanged: true, prototypeUnchanged: true
		});
	}
	assert.deepEqual(run(`var input = Object.create({north__size: 999});
		input.west__size = 240; return $.layout.transformData(input);`), {west: {size: 240}});
	assert.deepEqual(run(`var result = $.layout.transformData({toString__size: 240});
		return {own: Object.prototype.hasOwnProperty.call(result, 'toString'),
			size: result.toString.size, inheritedClean: Object.prototype.toString.size === undefined};`), {
		own: true, size: 240, inheritedClean: true
	});
	for(const data of [
		{'constructor__prototype__layoutTestMarker': 'synthetic-marker', west__size: 240},
		JSON.parse('{"__proto__":{"layoutTestMarker":"synthetic-marker"},"west__size":240}'),
		{west: {size: 240, constructor: {prototype: {layoutTestMarker: 'synthetic-marker'}}}}
	]) {
		assert.deepEqual(run(`
			$.layout.state.readCookie = function() {return JSON.parse(${JSON.stringify(JSON.stringify(data))});};
			$.isEmptyObject = function(value) {return Object.keys(value).length === 0;};
			$.extend = function(deep, target, value) {
				function safe(object) {
					Object.keys(object).forEach(function(key) {
						if(key === '__proto__' || key.split('__').some(function(part) {
							return part === 'constructor' || part === 'prototype';
						})) throw new Error('Unsafe cookie reached deep copy');
						if(object[key] && typeof object[key] === 'object') safe(object[key]);
					});
				}
				safe(value); return JSON.parse(JSON.stringify(value));
			};
			var loaded;
			var instance = {state: {}, loadState: function(value) {loaded = value;}};
			$.layout.state.loadCookie(instance);
			return {size: loaded.west.size, clean: ({}).layoutTestMarker === undefined};
		`), {size: 240, clean: true}, 'cookie is normalized before any deep copy');
	}
	console.log('PASS: ' + filename + ' normal, nested, forbidden-path and inherited-property controls');
}
assert.equal(Object.prototype.layoutTestMarker, undefined, 'host prototype stays untouched');
