/*! UIkit 3.23.10 | https://www.getuikit.com | (c) 2014 - 2025 YOOtheme | MIT License */

(function (factory) {
	typeof define === 'function' && define.amd ? define('uikittest', factory) :
		factory();
})((function () { 'use strict';
	
	const hyphenateRe = /\B([A-Z])/g;
	
	const hyphenate = memoize((str) => str.replace(hyphenateRe, '-$1').toLowerCase());
	
	const ucfirst = memoize((str) => str.charAt(0).toUpperCase() + str.slice(1));
	
	function startsWith(str, search) {
		return str?.startsWith?.(search);
	}
	
	const { isArray, from: toArray } = Array;
	
	function isFunction(obj) {
		return typeof obj === 'function';
	}
	
	function isObject(obj) {
		return obj !== null && typeof obj === 'object';
	}
	
	function isWindow(obj) {
		return isObject(obj) && obj === obj.window;
	}
	
	function isDocument(obj) {
		return nodeType(obj) === 9;
	}
	
	function isNode(obj) {
		return nodeType(obj) >= 1;
	}
	
	function nodeType(obj) {
		return !isWindow(obj) && isObject(obj) && obj.nodeType;
	}
	
	function isString(value) {
		return typeof value === 'string';
	}
	
	function isNumber(value) {
		return typeof value === 'number';
	}
	
	function isNumeric(value) {
		return isNumber(value) || (isString(value) && !isNaN(value - parseFloat(value)));
	}
	
	function isUndefined(value) {
		return value === void 0;
	}
	
	function toFloat(value) {
		return parseFloat(value) || 0;
	}
	
	function toNode(element) {
		return element && toNodes(element)[0];
	}
	
	function toNodes(element) {
		return isNode(element) ? [element] : Array.from(element || []).filter(isNode);
	}
	
	function sumBy(array, iteratee) {
		return array.reduce(
			(sum, item) => sum + toFloat(isFunction(iteratee) ? iteratee(item) : item[iteratee]),
			0,
		);
	}
	
	function memoize(fn) {
		const cache = Object.create(null);
		return (key, ...args) => cache[key] || (cache[key] = fn(key, ...args));
	}
	
	function addClass(element, ...classes) {
		for (const node of toNodes(element)) {
			const add = toClasses(classes).filter((cls) => !hasClass(node, cls));
			if (add.length) {
				node.classList.add(...add);
			}
		}
	}
	
	function removeClass(element, ...classes) {
		for (const node of toNodes(element)) {
			const remove = toClasses(classes).filter((cls) => hasClass(node, cls));
			if (remove.length) {
				node.classList.remove(...remove);
			}
		}
	}
	
	function hasClass(element, cls) {
		[cls] = toClasses(cls);
		return toNodes(element).some((node) => node.classList.contains(cls));
	}
	
	function toClasses(str) {
		return str
			? isArray(str)
				? str.map(toClasses).flat()
				: String(str).split(' ').filter(Boolean)
			: [];
	}
	
	function attr(element, name, value) {
		if (isObject(name)) {
			for (const key in name) {
				attr(element, key, name[key]);
			}
			return;
		}
		
		if (isUndefined(value)) {
			return toNode(element)?.getAttribute(name);
		} else {
			for (const el of toNodes(element)) {
				if (isFunction(value)) {
					value = value.call(el, attr(el, name));
				}
				
				if (value === null) {
					removeAttr(el, name);
				} else {
					el.setAttribute(name, value);
				}
			}
		}
	}
	
	function removeAttr(element, name) {
		toNodes(element).forEach((element) => element.removeAttribute(name));
	}
	
	function parent(element) {
		return toNode(element)?.parentElement;
	}
	
	function filter(element, selector) {
		return toNodes(element).filter((element) => matches(element, selector));
	}
	
	function matches(element, selector) {
		return toNodes(element).some((element) => element.matches(selector));
	}
	
	function children(element, selector) {
		element = toNode(element);
		const children = element ? toArray(element.children) : [];
		return selector ? filter(children, selector) : children;
	}
	
	function index(element, ref) {
		return children(parent(element)).indexOf(element);
	}
	
	function find(selector, context) {
		return toNode(_query(selector, toNode(context), 'querySelector'));
	}
	
	function findAll(selector, context) {
		return toNodes(_query(selector, toNode(context), 'querySelectorAll'));
	}
	
	const addStarRe = /([!>+~-])(?=\s+[!>+~-]|\s*$)/g;
	// This will fail for nested, comma separated selectors (e.g `a:has(b:not(c),d)`)
	const splitSelectorRe = /(\([^)]*\)|[^,])+/g;
	
	const parseSelector = memoize((selector) => {
		let isContextSelector = false;
		
		if (!selector || !isString(selector)) {
			return {};
		}
		
		const selectors = [];
		
		for (let sel of selector.match(splitSelectorRe)) {
			sel = sel.trim().replace(addStarRe, '$1 *');
			isContextSelector ||= ['!', '+', '~', '-', '>'].includes(sel[0]);
			selectors.push(sel);
		}
		
		return {
			selector: selectors.join(','),
			selectors,
			isContextSelector,
		};
	});
	const positionRe = /(\([^)]*\)|\S)*/;
	const parsePositionSelector = memoize((selector) => {
		selector = selector.slice(1).trim();
		const [position] = selector.match(positionRe);
		return [position, selector.slice(position.length + 1)];
	});
	
	function _query(selector, context = document, queryFn) {
		const parsed = parseSelector(selector);
		
		if (!parsed.isContextSelector) {
			return parsed.selector ? _doQuery(context, queryFn, parsed.selector) : selector;
		}
		
		selector = '';
		const isSingle = parsed.selectors.length === 1;
		for (let sel of parsed.selectors) {
			let positionSel;
			let ctx = context;
			
			if (sel[0] === '!') {
				[positionSel, sel] = parsePositionSelector(sel);
				ctx = context.parentElement?.closest(positionSel);
				if (!sel && isSingle) {
					return ctx;
				}
			}
			
			if (ctx && sel[0] === '-') {
				[positionSel, sel] = parsePositionSelector(sel);
				ctx = ctx.previousElementSibling;
				ctx = matches(ctx, positionSel) ? ctx : null;
				if (!sel && isSingle) {
					return ctx;
				}
			}
			
			if (!ctx) {
				continue;
			}
			
			if (isSingle) {
				if (sel[0] === '~' || sel[0] === '+') {
					sel = `:scope > :nth-child(${index(ctx) + 1}) ${sel}`;
					ctx = ctx.parentElement;
				} else if (sel[0] === '>') {
					sel = `:scope ${sel}`;
				}
				
				return _doQuery(ctx, queryFn, sel);
			}
			
			selector += `${selector ? ',' : ''}${domPath(ctx)} ${sel}`;
		}
		
		if (!isDocument(context)) {
			context = context.ownerDocument;
		}
		
		return _doQuery(context, queryFn, selector);
	}
	
	function _doQuery(context, queryFn, selector) {
		try {
			return context[queryFn](selector);
		} catch (e) {
			return null;
		}
	}
	
	function domPath(element) {
		const names = [];
		while (element.parentNode) {
			const id = attr(element, 'id');
			if (id) {
				names.unshift(`#${escape(id)}`);
				break;
			} else {
				let { tagName } = element;
				if (tagName !== 'HTML') {
					tagName += `:nth-child(${index(element) + 1})`;
				}
				names.unshift(tagName);
				element = element.parentNode;
			}
		}
		return names.join(' > ');
	}
	
	function escape(css) {
		return isString(css) ? CSS.escape(css) : '';
	}
	
	function on(...args) {
		let [targets, types, selector, listener, useCapture = false] = getArgs(args);
		
		if (listener.length > 1) {
			listener = detail(listener);
		}
		
		if (useCapture?.self) {
			listener = selfFilter(listener);
		}
		
		if (selector) {
			listener = delegate(selector, listener);
		}
		
		for (const type of types) {
			for (const target of targets) {
				target.addEventListener(type, listener, useCapture);
			}
		}
		
		return () => off(targets, types, listener, useCapture);
	}
	
	function off(...args) {
		let [targets, types, , listener, useCapture = false] = getArgs(args);
		for (const type of types) {
			for (const target of targets) {
				target.removeEventListener(type, listener, useCapture);
			}
		}
	}
	
	function getArgs(args) {
		// Event targets
		args[0] = toEventTargets(args[0]);
		
		// Event types
		if (isString(args[1])) {
			args[1] = args[1].split(' ');
		}
		
		// Delegate?
		if (isFunction(args[2])) {
			args.splice(2, 0, false);
		}
		
		return args;
	}
	
	function delegate(selector, listener) {
		return (e) => {
			const current =
				selector[0] === '>'
					? findAll(selector, e.currentTarget)
						.reverse()
						.find((element) => element.contains(e.target))
					: e.target.closest(selector);
			
			if (current) {
				e.current = current;
				listener.call(this, e);
				delete e.current;
			}
		};
	}
	
	function detail(listener) {
		return (e) => (isArray(e.detail) ? listener(e, ...e.detail) : listener(e));
	}
	
	function selfFilter(listener) {
		return function (e) {
			if (e.target === e.currentTarget || e.target === e.current) {
				return listener.call(null, e);
			}
		};
	}
	
	function isEventTarget(target) {
		return target && 'addEventListener' in target;
	}
	
	function toEventTarget(target) {
		return isEventTarget(target) ? target : toNode(target);
	}
	
	function toEventTargets(target) {
		return isArray(target)
			? target.map(toEventTarget).filter(Boolean)
			: isString(target)
				? findAll(target)
				: isEventTarget(target)
					? [target]
					: toNodes(target);
	}
	
	const cssNumber = {
		'animation-iteration-count': true,
		'column-count': true,
		'fill-opacity': true,
		'flex-grow': true,
		'flex-shrink': true,
		'font-weight': true,
		'line-height': true,
		opacity: true,
		order: true,
		orphans: true,
		'stroke-dasharray': true,
		'stroke-dashoffset': true,
		widows: true,
		'z-index': true,
		zoom: true,
	};
	
	function css(element, property, value, priority) {
		const elements = toNodes(element);
		for (const element of elements) {
			if (isString(property)) {
				property = propName(property);
				
				if (isUndefined(value)) {
					return getComputedStyle(element).getPropertyValue(property);
				} else {
					element.style.setProperty(
						property,
						isNumeric(value) && !cssNumber[property] && !isCustomProperty(property)
							? `${value}px`
							: value || isNumber(value)
								? value
								: '',
						priority,
					);
				}
			} else if (isArray(property)) {
				const props = {};
				for (const prop of property) {
					props[prop] = css(element, prop);
				}
				return props;
			} else if (isObject(property)) {
				for (const prop in property) {
					css(element, prop, property[prop], value);
				}
			}
		}
		return elements[0];
	}
	
	// https://drafts.csswg.org/cssom/#dom-cssstyledeclaration-setproperty
	const propName = memoize((name) => {
		if (isCustomProperty(name)) {
			return name;
		}
		
		name = hyphenate(name);
		
		const { style } = document.documentElement;
		
		if (name in style) {
			return name;
		}
		
		for (const prefix of ['webkit', 'moz']) {
			const prefixedName = `-${prefix}-${name}`;
			if (prefixedName in style) {
				return prefixedName;
			}
		}
	});
	
	function isCustomProperty(name) {
		return startsWith(name, '--');
	}
	
	const prepend = applyFn('prepend');
	
	function applyFn(fn) {
		return function (ref, element) {
			const nodes = toNodes(isString(element) ? fragment(element) : element);
			$(ref)?.[fn](...nodes);
			return unwrapSingle(nodes);
		};
	}
	
	const singleTagRe = /^<(\w+)\s*\/?>(?:<\/\1>)?$/;
	
	function fragment(html) {
		const matches = singleTagRe.exec(html);
		if (matches) {
			return document.createElement(matches[1]);
		}
		
		const container = document.createElement('template');
		container.innerHTML = html.trim();
		
		return unwrapSingle(container.content.childNodes);
	}
	
	function unwrapSingle(nodes) {
		return nodes.length > 1 ? nodes : nodes[0];
	}
	
	function $(selector, context) {
		return isHtml(selector) ? toNode(fragment(selector)) : find(selector, context);
	}
	
	function $$(selector, context) {
		return isHtml(selector) ? toNodes(fragment(selector)) : findAll(selector, context);
	}
	
	function isHtml(str) {
		return isString(str) && startsWith(str.trim(), '<');
	}
	
	const dirs = {
		width: ['left', 'right'],
		height: ['top', 'bottom'],
	};
	
	dimension('height');
	dimension('width');
	
	function dimension(prop) {
		const propName = ucfirst(prop);
		return (element, value) => {
			if (isUndefined(value)) {
				if (isWindow(element)) {
					return element[`inner${propName}`];
				}
				
				if (isDocument(element)) {
					const doc = element.documentElement;
					return Math.max(doc[`offset${propName}`], doc[`scroll${propName}`]);
				}
				
				element = toNode(element);
				
				value = css(element, prop);
				value = value === 'auto' ? element[`offset${propName}`] : toFloat(value) || 0;
				
				return value - boxModelAdjust(element, prop);
			} else {
				return css(
					element,
					prop,
					!value && value !== 0 ? '' : +value + boxModelAdjust(element, prop) + 'px',
				);
			}
		};
	}
	
	function boxModelAdjust(element, prop, sizing = 'border-box') {
		return css(element, 'boxSizing') === sizing
			? sumBy(
				dirs[prop],
				(prop) => toFloat(css(element, `padding-${prop}`)) +
					toFloat(css(element, `border-${prop}-width`)),
			)
			: 0;
	}
	
	/* global ["accordion","alert","align","animation","article","background","badge","base","breadcrumb","button","card","close","column","comment","container","countdown","cover","description-list","divider","dotnav","drop","dropbar","dropdown","dropnav","filter","flex","form","grid-masonry","grid-parallax","grid","heading","height-expand","height-viewport","height","icon","iconnav","image","label","leader","lightbox","link","list","margin","marker","modal","nav","navbar","notification","offcanvas","overlay","padding","pagination","parallax","placeholder","position","progress","scroll","scrollspy","search","section","slidenav","slider","slideshow","sortable","spinner","sticky-navbar","sticky-parallax","sticky","subnav","svg","switcher","tab","table","text","thumbnav","tile","toggle","tooltip","totop","transition","upload","utility","video","visibility","width"] */
	
	const tests = ["index", "accordion","alert","align","animation","article","background","badge","base","breadcrumb","button","card","close","column","comment","container","countdown","cover","description-list","divider","dotnav","drop","dropbar","dropdown","dropnav","filter","flex","form","grid-masonry","grid-parallax","grid","heading","height-expand","height-viewport","height","icon","iconnav","image","label","leader","lightbox","link","list","margin","marker","modal","nav","navbar","notification","offcanvas","overlay","padding","pagination","parallax","placeholder","position","progress","scroll","scrollspy","search","section","slidenav","slider","slideshow","sortable","spinner","sticky-navbar","sticky-parallax","sticky","subnav","svg","switcher","tab","table","text","thumbnav","tile","toggle","tooltip","totop","transition","upload","utility","video","visibility","width"];
	const storage = window.sessionStorage;
	const key = '_uikit_style';
	const keyinverse = '_uikit_inverse';
	
	// try to load themes.json
	const request = new XMLHttpRequest();
	request.open('GET', testsUrl + 'themes.json', false);
	request.send(null);

	const themes = request.status === 200 ? JSON.parse(request.responseText) : {};
	const styles = {
		theme: { css: cssUrl },
		core: { css: adminThemeUrl + 'uikit/dist/css/uikit.css' },
		...themes,
	};
	
	const component = location.pathname
		.split('/')
		.pop()
		.replace(/.html$/, '');
	
	const variations = {
		'': 'Default',
		light: 'Dark',
		dark: 'Light',
	};
	
	if (getParam('style') && getParam('style').match(/\.(json|css)$/)) {
		styles.custom = getParam('style');
	}
	
	storage[key] = storage[key] || 'core';
	storage[keyinverse] = storage[keyinverse] || '';
	
	const dir = storage._uikit_dir || 'ltr';
	
	// set dir
	document.dir = dir;
	
	const style = styles[storage[key]] || styles.theme;
	
	// add style
	document.writeln(
		`<link rel="stylesheet" href="${
			dir !== 'rtl' ? style.css : style.css.replace('.css', '-rtl.css')
		}">`,
	);
	
	document.writeln(
		`<style>html:not(:has(body :first-child [aria-label="Component switcher"])) {padding-top: 80px}</style>`,
	);
	
	// add javascript
	/*
	document.writeln('<script src="../dist/js/uikit.js"></script>');
	document.writeln(
		`<script src="${style.icons ? style.icons : '../dist/js/uikit-icons.js'}"></script>`,
	);
	 */
	
	on(window, 'load', () => setTimeout(
			() => requestAnimationFrame(() => {
				const $container = prepend(
					document.body,
					` <div id="test-selects" class="uk-container"> <select class="uk-select uk-form-width-small" style="margin: 20px 20px 20px 0" aria-label="Component switcher"> <option value="index.html">Component</option> ${tests
						.map(
							(name) => `<option value="${name}.html">${name
								.split('-')
								.map(ucfirst)
								.join(' ')}</option>`,
						)
						.join('')} </select> <select class="uk-select uk-form-width-small" style="margin: 20px" aria-label="Theme switcher"> ${Object.keys(styles)
						.map((style) => `<option value="${style}">${ucfirst(style)}</option>`)
						.join('')} </select> <select class="uk-select uk-form-width-small" style="margin: 20px" aria-label="Inverse switcher"> ${Object.keys(variations)
						.map((name) => `<option value="${name}">${variations[name]}</option>`)
						.join('')} </select> <label style="margin: 20px"> <input type="checkbox" class="uk-checkbox"/> <span style="margin: 5px">RTL</span> </label> </div> `,
				);
				
				const [$tests, $styles, $inverse, $rtl] = $container.children;
				
				// Tests
				// ------------------------------
				
				on($tests, 'change', () => {
					if ($tests.value) {
						location.href = './?test_uikit=' + `${$tests.value}${
							styles.custom ? `&style=${getParam('style')}` : ''
						}`;
					}
				});
				$tests.value = `${component || 'index'}.html`;
				
				// Styles
				// ------------------------------
				
				on($styles, 'change', () => {
					storage[key] = $styles.value;
					location.reload();
				});
				$styles.value = storage[key];
				
				// Variations
				// ------------------------------
				
				$inverse.value = storage[keyinverse];
				
				if ($inverse.value) {
					/*
					removeClass(
						$$('*'),
						'uk-card-default',
						'uk-card-muted',
						'uk-card-primary',
						'uk-card-secondary',
						'uk-tile-default',
						'uk-tile-muted',
						'uk-tile-primary',
						'uk-tile-secondary',
						'uk-section-default',
						'uk-section-muted',
						'uk-section-primary',
						'uk-section-secondary',
						'uk-overlay-default',
						'uk-overlay-primary',
					);
					 */
					
					addClass($$('.uk-navbar-container'), 'uk-navbar-transparent');
					
					css(
						document.documentElement,
						'background',
						$inverse.value === 'dark' ? '#fff' : '#222',
					);
					addClass(document.body, `uk-${$inverse.value}`);
				}
				
				on($inverse, 'change', () => {
					storage[keyinverse] = $inverse.value;
					location.reload();
				});
				
				// RTL
				// ------------------------------
				
				on($rtl, 'change', ({ target }) => {
					storage._uikit_dir = target.checked ? 'rtl' : 'ltr';
					location.reload();
				});
				$rtl.firstElementChild.checked = dir === 'rtl';
			}),
			100,
		),
	);
	
	function getParam(name) {
		const match = new RegExp(`[?&]${name}=([^&]*)`).exec(window.location.search);
		return match && decodeURIComponent(match[1].replace(/\+/g, ' '));
	}
	
}));
