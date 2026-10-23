/* Theme ocs - namespaced helpers (SPEC-006 REQ-THM-023, SPEC-023 REQ-DSG-013). ES5, no globals except window.OCS.
   OCS.modal is the ONLY popup implementation; modules must use it. Closes on overlay click and Esc. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};
	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
	var current = null;

	function focusables(root) {
		return Array.prototype.slice.call(root.querySelectorAll(FOCUSABLE));
	}

	function onKeydown(e) {
		if (!current) {
			return;
		}

		if (e.key === 'Escape' || e.keyCode === 27) {
			e.preventDefault();
			OCS.modal.close();
			return;
		}

		if (e.key === 'Tab' || e.keyCode === 9) {
			var items = focusables(current.dialog);

			if (!items.length) {
				e.preventDefault();
				current.dialog.focus();
				return;
			}

			var first = items[0];
			var last = items[items.length - 1];

			if (e.shiftKey && (document.activeElement === first || document.activeElement === current.dialog)) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		}
	}

	OCS.modal = {
		/**
		 * @param {Object} opts
		 * @param {string|Element} opts.content HTML string or element placed in the dialog body
		 * @param {string} [opts.variant] 'center' (default) or 'side'
		 * @param {string} [opts.closeLabel] accessible name of the close button (supplied by the module's language file)
		 * @param {string} [opts.labelledBy] id of the element that titles the dialog
		 * @param {Function} [opts.onClose]
		 */
		open: function (opts) {
			opts = opts || {};

			if (current) {
				OCS.modal.close();
			}

			var overlay = document.createElement('div');
			overlay.className = 'ocs-modal ocs-modal-' + (opts.variant === 'side' ? 'side' : 'center');

			var dialog = document.createElement('div');
			dialog.className = 'ocs-modal-dialog';
			dialog.setAttribute('role', 'dialog');
			dialog.setAttribute('aria-modal', 'true');
			dialog.setAttribute('tabindex', '-1');

			if (opts.labelledBy) {
				dialog.setAttribute('aria-labelledby', opts.labelledBy);
			}

			var close = document.createElement('button');
			close.type = 'button';
			close.className = 'ocs-modal-close';
			close.innerHTML = '&times;';

			if (opts.closeLabel) {
				close.setAttribute('aria-label', opts.closeLabel);
			}

			var body = document.createElement('div');
			body.className = 'ocs-modal-body';

			if (typeof opts.content === 'string') {
				body.innerHTML = opts.content;
			} else if (opts.content && opts.content.nodeType === 1) {
				body.appendChild(opts.content);
			}

			dialog.appendChild(close);
			dialog.appendChild(body);
			overlay.appendChild(dialog);
			document.body.appendChild(overlay);

			overlay.addEventListener('mousedown', function (e) {
				if (e.target === overlay) {
					OCS.modal.close();
				}
			});
			close.addEventListener('click', function () {
				OCS.modal.close();
			});

			current = {overlay: overlay, dialog: dialog, onClose: opts.onClose, opener: document.activeElement};
			document.documentElement.classList.add('ocs-modal-open');
			document.addEventListener('keydown', onKeydown);

			var items = focusables(body);
			(items.length ? items[0] : dialog).focus();

			return dialog;
		},

		close: function () {
			if (!current) {
				return;
			}

			var c = current;
			current = null;

			document.removeEventListener('keydown', onKeydown);
			document.documentElement.classList.remove('ocs-modal-open');

			if (c.overlay.parentNode) {
				c.overlay.parentNode.removeChild(c.overlay);
			}

			if (c.opener && typeof c.opener.focus === 'function') {
				c.opener.focus();
			}

			if (typeof c.onClose === 'function') {
				c.onClose();
			}
		},

		isOpen: function () {
			return current !== null;
		}
	};
}(window, document));

/* Shell behaviour (SPEC-031): announcement dismissal, footer groups on mobile, mobile catalogue tab. */
(function (window, document) {
	'use strict';

	var KEY = 'ocs.announce.dismissed';

	function read() {
		try {
			return window.localStorage.getItem(KEY);
		} catch (e) {
			return null;
		}
	}

	function write(value) {
		try {
			window.localStorage.setItem(KEY, value);
		} catch (e) {
			/* storage unavailable: the bar simply reappears on the next page */
		}
	}

	function init() {
		var bar = document.getElementById('ocs-announce');

		if (bar) {
			if (read() === bar.getAttribute('data-id')) {
				bar.parentNode.removeChild(bar);
			} else {
				var close = bar.querySelector('.ocs-announce-close');

				if (close) {
					close.addEventListener('click', function () {
						write(bar.getAttribute('data-id'));
						bar.parentNode.removeChild(bar);
					});
				}
			}
		}

		if (window.innerWidth < 768) {
			var groups = document.querySelectorAll('details.ocs-fgroup');

			for (var i = 0; i < groups.length; i++) {
				groups[i].removeAttribute('open');
			}
		}

		var tab = document.getElementById('ocs-tab-catalog');

		if (tab) {
			tab.addEventListener('click', function () {
				var toggle = document.querySelector('#menu .navbar-toggle');

				if (toggle) {
					toggle.click();
					window.scrollTo(0, 0);
				} else {
					window.location.href = (document.querySelector('base') || {}).href || '/';
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}(window, document));

/* Listing: "show more" button (SPEC-034 REQ-LST-002). Works without JS through the normal pagination links. */
(function (window, document) {
	'use strict';

	function nextHref(root) {
		var active = root.querySelector('.pagination li.active');
		var next = active ? active.nextElementSibling : null;
		var link = next ? next.querySelector('a') : null;

		return link ? link.getAttribute('href') : '';
	}

	function init() {
		var button = document.getElementById('ocs-load-more');
		var grid = document.getElementById('ocs-grid');

		if (!button || !grid || !window.DOMParser || !window.fetch) {
			return;
		}

		var href = nextHref(document);
		var busy = false;

		if (!href) {
			return;
		}

		button.hidden = false;

		button.addEventListener('click', function () {
			if (busy || !href) {
				return;
			}

			busy = true;
			button.disabled = true;

			window.fetch(href, {credentials: 'same-origin'}).then(function (r) {
				return r.text();
			}).then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var cards = doc.querySelectorAll('#ocs-grid .product-layout');
				var first = grid.querySelector('.product-layout');
				var cls = first ? first.className : '';
				var known = {};
				var i;
				var items = grid.querySelectorAll('.product-layout');

				for (i = 0; i < items.length; i++) {
					known[items[i].getAttribute('data-product-id')] = true;
				}

				for (i = 0; i < cards.length; i++) {
					var id = cards[i].getAttribute('data-product-id');

					if (id && known[id]) {
						continue;
					}

					var node = document.importNode(cards[i], true);

					if (cls) {
						node.className = cls;
					}

					grid.appendChild(node);
				}

				var oldPager = document.querySelector('.pagination');
				var newPager = doc.querySelector('.pagination');

				if (oldPager && newPager) {
					oldPager.parentNode.replaceChild(document.importNode(newPager, true), oldPager);
				}

				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, '', href);
				}

				href = nextHref(document);
				button.hidden = !href;
				button.disabled = false;
				busy = false;
			}).catch(function () {
				button.disabled = false;
				busy = false;
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}(window, document));

/* Navigation (SPEC-027): mega menu open/close (hover, click, keyboard, touch) and side category menu memory. */
(function (window, document) {
	'use strict';

	var MEM = 'ocs.cmenu.open';

	function isMobile() {
		return window.innerWidth < 768;
	}

	function each(list, fn) {
		for (var i = 0; i < list.length; i++) {
			fn(list[i], i);
		}
	}

	function initMega() {
		var nav = document.querySelector('.ocs-mega');

		if (!nav) {
			return;
		}

		var hover = nav.getAttribute('data-behaviour') !== 'click';
		var openBtn = nav.querySelector('.ocs-mega-open');
		var items = nav.querySelectorAll('.ocs-mega-item.ocs-has-children');
		var timers = [];

		function setItem(item, open) {
			var btn = item.querySelector('.ocs-mega-expand');

			if (open) {
				item.classList.add('open');
			} else {
				item.classList.remove('open');
			}

			if (btn) {
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			}
		}

		function closeOthers(keep) {
			each(items, function (item) {
				if (item !== keep) {
					setItem(item, false);
				}
			});
		}

		function setPanel(open) {
			if (open) {
				nav.classList.add('is-open');
			} else {
				nav.classList.remove('is-open');
				closeOthers(null);
			}

			if (openBtn) {
				openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
			}
		}

		if (openBtn) {
			openBtn.addEventListener('click', function () {
				setPanel(!nav.classList.contains('is-open'));
			});
		}

		each(items, function (item, i) {
			var btn = item.querySelector('.ocs-mega-expand');

			if (btn) {
				btn.addEventListener('click', function () {
					var open = !item.classList.contains('open');

					closeOthers(item);
					setItem(item, open);
				});
			}

			item.addEventListener('mouseenter', function () {
				if (!hover || isMobile()) {
					return;
				}

				window.clearTimeout(timers[i]);
				timers[i] = window.setTimeout(function () {
					closeOthers(item);
					setItem(item, true);
				}, 120);
			});

			item.addEventListener('mouseleave', function () {
				if (!hover || isMobile()) {
					return;
				}

				window.clearTimeout(timers[i]);
				timers[i] = window.setTimeout(function () {
					setItem(item, false);
				}, 200);
			});

			item.addEventListener('focusin', function () {
				if (!isMobile()) {
					closeOthers(item);
					setItem(item, true);
				}
			});
		});

		document.addEventListener('click', function (e) {
			if (!isMobile() && !nav.contains(e.target)) {
				setPanel(false);
			}
		});

		document.addEventListener('keydown', function (e) {
			if ((e.key === 'Escape' || e.keyCode === 27) && nav.classList.contains('is-open') && !isMobile()) {
				setPanel(false);

				if (openBtn) {
					openBtn.focus();
				}
			}
		});
	}

	function readMem() {
		try {
			return JSON.parse(window.sessionStorage.getItem(MEM) || '[]');
		} catch (e) {
			return [];
		}
	}

	function writeMem(ids) {
		try {
			window.sessionStorage.setItem(MEM, JSON.stringify(ids));
		} catch (e) {
			/* storage unavailable: branches simply start closed */
		}
	}

	function initSideMenu() {
		var menus = document.querySelectorAll('.ocs-cmenu');

		each(menus, function (menu) {
			var remembered = readMem();

			each(menu.querySelectorAll('.ocs-cmenu-item'), function (item) {
				if (remembered.indexOf(item.getAttribute('data-id')) !== -1 && item.querySelector('.ocs-cmenu-toggle')) {
					item.classList.add('open');
					item.querySelector('.ocs-cmenu-toggle').setAttribute('aria-expanded', 'true');
				}
			});

			each(menu.querySelectorAll('.ocs-cmenu-toggle'), function (btn) {
				btn.addEventListener('click', function () {
					var item = btn.parentNode;
					var open = !item.classList.contains('open');
					var ids = readMem();
					var id = item.getAttribute('data-id');
					var at = ids.indexOf(id);

					if (open) {
						item.classList.add('open');

						if (at === -1) {
							ids.push(id);
						}
					} else {
						item.classList.remove('open');

						if (at !== -1) {
							ids.splice(at, 1);
						}
					}

					btn.setAttribute('aria-expanded', open ? 'true' : 'false');
					writeMem(ids);
				});
			});
		});
	}

	function init() {
		initMega();
		initSideMenu();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}(window, document));

/* Request popups (SPEC-025): quick order, pre-order and back-in-stock forms in OCS.modal.
   A button carries data-form (form URL) and data-product-id; data-collect="1" also sends the product page fields
   (quantity, options) like core's cart.add. The server re-validates everything. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};

	function field(form, name) {
		return form.querySelector('[data-error-for="' + name + '"]');
	}

	function collect(data) {
		var box = document.getElementById('product');

		if (!box) {
			return;
		}

		var inputs = box.querySelectorAll('input, select, textarea');

		for (var i = 0; i < inputs.length; i++) {
			var el = inputs[i];

			if (!el.name || el.disabled || el.name === 'product_id') {
				continue;
			}

			if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
				continue;
			}

			data.append(el.name, el.value);
		}
	}

	function bind(root, collectFields) {
		var form = root.querySelector('.ocs-req-form');
		var done = root.querySelector('.ocs-req-done');

		if (!form) {
			return;
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			var data = new FormData(form);
			var errs = form.querySelectorAll('[data-error-for]');
			var btn = form.querySelector('button[type="submit"]');
			var i;

			for (i = 0; i < errs.length; i++) {
				errs[i].textContent = '';
			}

			if (collectFields) {
				collect(data);
			}

			btn.disabled = true;

			window.fetch(form.getAttribute('action'), {method: 'POST', body: data, credentials: 'same-origin'}).then(function (r) {
				return r.json();
			}).then(function (json) {
				btn.disabled = false;

				if (json.success) {
					form.hidden = true;
					done.textContent = json.success;
					done.hidden = false;

					return;
				}

				if (json.errors) {
					for (var name in json.errors) {
						if (Object.prototype.hasOwnProperty.call(json.errors, name) && field(form, name)) {
							field(form, name).textContent = json.errors[name];
						}
					}

					return;
				}

				field(form, '_form').textContent = json.error || '';
			}).catch(function () {
				btn.disabled = false;
				field(form, '_form').textContent = form.getAttribute('data-failed') || '';
			});
		});
	}

	OCS.req = {
		open: function (button) {
			var url = button.getAttribute('data-form');
			var id = button.getAttribute('data-product-id');
			var collectFields = button.getAttribute('data-collect') === '1';

			if (!url || !id || !window.fetch || !OCS.modal) {
				return;
			}

			button.disabled = true;

			window.fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'product_id=' + encodeURIComponent(id), {credentials: 'same-origin'}).then(function (r) {
				return r.text();
			}).then(function (html) {
				button.disabled = false;

				if (!html) {
					return;
				}

				var holder = document.createElement('div');

				holder.innerHTML = html;

				var root = holder.querySelector('.ocs-req');

				OCS.modal.open({content: holder, closeLabel: root ? root.getAttribute('data-close') : '', labelledBy: 'ocs-req-title'});
				bind(holder, collectFields);
			}).catch(function () {
				button.disabled = false;
			});
		}
	};

	document.addEventListener('click', function (e) {
		var el = e.target;

		while (el && el !== document) {
			if (el.classList && el.classList.contains('ocs-req-btn')) {
				e.preventDefault();
				OCS.req.open(el);

				return;
			}

			el = el.parentNode;
		}
	});
}(window, document));

/* Cart panel (SPEC-026): opens in OCS.modal (side variant) after an add and from the header cart button, edits quantities
   in place. Active only when the header carries data-ocs-cart (module on); otherwise core cart behaviour is untouched.
   Prices and totals always come from the server (panel / update / remove endpoints). */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};
	var base = null; // read from <body data-ocs-cart> once the DOM exists (theme.js loads in <head>)
	var dialog = null;
	var timers = {};

	function endpoint(action, query) {
		return base.replace('/panel', '/' + action) + (query || '');
	}

	function joiner(url) {
		return url.indexOf('?') === -1 ? '?' : '&';
	}

	function updateHeader(json) {
		var button = document.querySelector('#cart > button');

		if (button && json.total_text) {
			button.innerHTML = '<span id="cart-total"><i class="fa fa-shopping-cart"></i> ' + json.total_text + '</span>';
		}
	}

	function render(json) {
		updateHeader(json);

		if (!dialog || !json.html) {
			return;
		}

		var body = dialog.querySelector('.ocs-modal-body');

		body.innerHTML = json.html;
		bind(body);
	}

	function request(url, data) {
		var options = {credentials: 'same-origin'};

		if (data) {
			var form = new FormData();

			for (var k in data) {
				if (Object.prototype.hasOwnProperty.call(data, k)) {
					form.append(k, data[k]);
				}
			}

			options.method = 'POST';
			options.body = form;
		}

		return window.fetch(url, options).then(function (r) {
			return r.json();
		});
	}

	function mutate(action, key, quantity) {
		var data = {key: key};

		if (quantity !== null) {
			data.quantity = quantity;
		}

		if (dialog) {
			dialog.classList.add('ocs-cart-busy');
		}

		request(endpoint(action), data).then(function (json) {
			if (dialog) {
				dialog.classList.remove('ocs-cart-busy');
			}

			render(json);
		}).catch(function () {
			if (dialog) {
				dialog.classList.remove('ocs-cart-busy');
			}
		});
	}

	function bind(root) {
		var lines = root.querySelectorAll('.ocs-cart-line');
		var i;

		for (i = 0; i < lines.length; i++) {
			(function (line) {
				var key = line.getAttribute('data-key');
				var input = line.querySelector('.ocs-cart-qty');

				line.querySelector('.ocs-cart-minus').addEventListener('click', function () {
					mutate('update', key, Math.max(0, (parseInt(input.value, 10) || 1) - 1));
				});

				line.querySelector('.ocs-cart-plus').addEventListener('click', function () {
					mutate('update', key, (parseInt(input.value, 10) || 0) + 1);
				});

				line.querySelector('.ocs-cart-remove').addEventListener('click', function () {
					mutate('remove', key, null);
				});

				input.addEventListener('input', function () {
					window.clearTimeout(timers[key]);
					timers[key] = window.setTimeout(function () {
						var q = parseInt(input.value, 10);

						if (!isNaN(q) && q >= 0) {
							mutate('update', key, q);
						}
					}, 600);
				});
			}(lines[i]));
		}

		var more = root.querySelectorAll('.ocs-cart-continue');

		for (i = 0; i < more.length; i++) {
			more[i].addEventListener('click', function () {
				OCS.modal.close();
			});
		}
	}

	OCS.cart = {
		open: function (added) {
			if (!base || !window.fetch || !OCS.modal) {
				return;
			}

			var url = endpoint('panel');

			request(url + (added ? joiner(url) + 'added=' + encodeURIComponent(added) : '')).then(function (json) {
				if (json.disabled || !json.html) {
					return;
				}

				updateHeader(json);

				var holder = document.createElement('div');

				holder.innerHTML = json.html;

				var root = holder.querySelector('.ocs-cart');

				dialog = OCS.modal.open({
					content: holder,
					variant: 'side',
					closeLabel: root ? root.getAttribute('data-close') : '',
					labelledBy: 'ocs-cart-title',
					onClose: function () {
						dialog = null;
					}
				});

				bind(dialog.querySelector('.ocs-modal-body'));
			});
		},

		refreshCounter: function () {
			if (base && window.fetch) {
				var url = endpoint('panel');

				request(url + joiner(url) + 'counter=1').then(updateHeader).catch(function () {});
			}
		}
	};

	function start() {
	base = document.body.getAttribute('data-ocs-cart');

	if (!base) {
		return;
	}

	// core cart.add / the product page button post to checkout/cart/add: open the panel when it succeeded
	if (window.jQuery) {
		window.jQuery(document).ajaxSuccess(function (e, xhr, settings) {
			if (!settings || !settings.url || settings.url.indexOf('checkout/cart/add') === -1) {
				return;
			}

			var json;

			try {
				json = JSON.parse(xhr.responseText);
			} catch (err) {
				return;
			}

			if (json && json.success) {
				var match = /product_id=(\d+)/.exec(typeof settings.data === 'string' ? settings.data : '');

				window.setTimeout(function () {
					window.jQuery('.alert-success').remove();
					OCS.cart.open(match ? match[1] : '');
				}, 150);
			}
		});
	}

	// the header cart button opens the panel instead of core's dropdown
	document.addEventListener('click', function (e) {
		var el = e.target;

		while (el && el !== document) {
			if (el.parentNode && el.parentNode.id === 'cart' && el.tagName === 'BUTTON') {
				e.preventDefault();
				e.stopPropagation();
				OCS.cart.open('');

				return;
			}

			el = el.parentNode;
		}
	}, true);

	// other tabs: refresh the counter when the tab becomes visible again
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) {
			OCS.cart.refreshCounter();
		}
	});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
}(window, document));

/* AJAX search (SPEC-028): suggestions under the header search box. Active only when #search carries data-ocs-search (module on).
   Everything from the server is plain text and is rendered with textContent. Recent queries live in localStorage. */
(function (window, document) {
	'use strict';

	var RECENT = 'ocs.search.recent';
	var box;
	var input;
	var dd;
	var url;
	var minLen;
	var pageUrl;
	var strings;
	var timer = null;
	var seq = 0;
	var active = -1;

	function readRecent() {
		try {
			var list = JSON.parse(window.localStorage.getItem(RECENT) || '[]');

			return Array.isArray(list) ? list : [];
		} catch (e) {
			return [];
		}
	}

	function writeRecent(list) {
		try {
			window.localStorage.setItem(RECENT, JSON.stringify(list.slice(0, 10)));
		} catch (e) {
			/* storage unavailable: no history, the rest works */
		}
	}

	function remember(q) {
		q = (q || '').replace(/\s+/g, ' ').trim();

		if (q.length < 2) {
			return;
		}

		var list = readRecent().filter(function (x) {
			return x !== q;
		});

		list.unshift(q);
		writeRecent(list);
	}

	function el(tag, cls, text) {
		var e = document.createElement(tag);

		if (cls) {
			e.className = cls;
		}

		if (text) {
			e.textContent = text;
		}

		return e;
	}

	function link(href, cls, text, query) {
		var a = el('a', cls, text);

		a.setAttribute('href', href);
		a.setAttribute('role', 'option');

		if (query) {
			a.addEventListener('click', function () {
				remember(query);
			});
		}

		return a;
	}

	function heading(text) {
		return el('div', 'ocs-search-h', text);
	}

	function open(show) {
		dd.hidden = !show;
		input.setAttribute('aria-expanded', show ? 'true' : 'false');
		active = -1;
	}

	function items() {
		return dd.querySelectorAll('.ocs-search-item');
	}

	function highlight(index) {
		var list = items();

		for (var i = 0; i < list.length; i++) {
			list[i].classList.toggle('active', i === index);
		}

		active = index;

		if (list[index]) {
			list[index].scrollIntoView({block: 'nearest'});
		}
	}

	function chips(title, list, query) {
		var wrap = el('div', 'ocs-search-chips');

		wrap.appendChild(heading(title));

		list.forEach(function (item) {
			wrap.appendChild(link(item.href, 'ocs-search-item ocs-chip', item.name, query ? item.name : ''));
		});

		return wrap;
	}

	function renderRecent() {
		var recent = readRecent();

		dd.innerHTML = '';

		if (!recent.length) {
			return false;
		}

		var head = el('div', 'ocs-search-h');

		head.appendChild(document.createTextNode(strings.recent + ' '));

		var clear = el('button', 'ocs-search-clear', strings.clear);

		clear.type = 'button';
		clear.addEventListener('click', function () {
			writeRecent([]);
			dd.innerHTML = '';
			open(false);
		});
		head.appendChild(clear);
		dd.appendChild(head);

		var wrap = el('div', 'ocs-search-chips');

		recent.forEach(function (q) {
			wrap.appendChild(link(pageUrl + (pageUrl.indexOf('?') === -1 ? '?' : '&') + 'search=' + encodeURIComponent(q), 'ocs-search-item ocs-chip', q, q));
		});

		dd.appendChild(wrap);

		return true;
	}

	function renderPopular(list, showHeading) {
		if (!list || !list.length) {
			return false;
		}

		dd.appendChild(chips(showHeading, list, false));

		return true;
	}

	function renderResults(json, q) {
		dd.innerHTML = '';

		var any = false;

		if (json.products && json.products.length) {
			any = true;
			dd.appendChild(heading(strings.products));

			json.products.forEach(function (p) {
				var a = link(p.href, 'ocs-search-item ocs-search-product');
				var img = el('img');

				img.src = p.thumb;
				img.alt = '';
				img.width = 48;
				img.height = 48;
				img.loading = 'lazy';
				a.appendChild(img);

				var info = el('span', 'ocs-search-info');

				info.appendChild(el('span', 'ocs-search-name', p.name));

				if (p.model) {
					info.appendChild(el('small', 'ocs-search-model', p.model));
				}

				a.appendChild(info);

				if (p.price) {
					var price = el('span', 'ocs-search-price');

					if (p.special) {
						price.appendChild(el('strong', '', p.special));
						price.appendChild(el('s', '', p.price));

						if (p.discount) {
							price.appendChild(el('span', 'ocs-badge ocs-badge-discount-inline', '-' + p.discount + '%'));
						}
					} else {
						price.appendChild(el('strong', '', p.price));
					}

					a.appendChild(price);
				}

				a.addEventListener('click', function () {
					remember(q);
				});
				dd.appendChild(a);
			});
		}

		if (json.categories && json.categories.length) {
			any = true;
			dd.appendChild(chips(strings.categories, json.categories, false));
		}

		if (json.brands && json.brands.length) {
			any = true;
			dd.appendChild(chips(strings.brands, json.brands, false));
		}

		if (!any) {
			dd.appendChild(el('div', 'ocs-search-empty', strings.empty));
			renderPopular(json.popular, strings.popular);
		}

		if (any && json.all) {
			dd.appendChild(link(json.all, 'ocs-search-item ocs-search-all', strings.all, q));
		}
	}

	function showError() {
		dd.innerHTML = '';
		dd.appendChild(el('div', 'ocs-search-empty', strings.error));
		open(true);
	}

	function query(q) {
		var id = ++seq;

		window.fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q), {credentials: 'same-origin'}).then(function (r) {
			if (!r.ok) {
				throw new Error('http');
			}

			return r.json();
		}).then(function (json) {
			if (id !== seq || input.value.trim() !== q) {
				return;
			}

			if (json.disabled) {
				return;
			}

			renderResults(json, q);
			open(true);
		}).catch(function () {
			if (id === seq) {
				showError();
			}
		});
	}

	function onInput() {
		window.clearTimeout(timer);

		var q = input.value.trim();

		if (q === '') {
			seq++;
			showIdle();

			return;
		}

		if (q.length < minLen) {
			seq++;
			open(false);

			return;
		}

		timer = window.setTimeout(function () {
			query(q);
		}, 250);
	}

	function showIdle() {
		var hadRecent = renderRecent();

		window.fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'q=', {credentials: 'same-origin'}).then(function (r) {
			return r.json();
		}).then(function (json) {
			if (input.value.trim() !== '') {
				return;
			}

			var had = hadRecent;

			if (json.popular && json.popular.length) {
				dd.appendChild(chips(strings.popular, json.popular, false));
				had = true;
			}

			open(had);
		}).catch(function () {
			open(hadRecent);
		});
	}

	function init() {
		box = document.querySelector('#search[data-ocs-search]');

		if (!box || !window.fetch) {
			return;
		}

		input = box.querySelector('input[name="search"]');
		dd = box.querySelector('.ocs-search-dd');
		url = box.getAttribute('data-ocs-search');
		pageUrl = box.getAttribute('data-page') || '';
		minLen = parseInt(box.getAttribute('data-min'), 10) || 2;

		try {
			strings = JSON.parse(box.getAttribute('data-strings'));
		} catch (e) {
			strings = {};
		}

		input.addEventListener('input', onInput);
		input.addEventListener('focus', function () {
			if (input.value.trim() === '') {
				showIdle();
			} else if (dd.children.length) {
				open(true);
			}
		});

		// capture phase on document: runs before core's Enter handler (jQuery, bound on the input) and can take over
		document.addEventListener('keydown', function (e) {
			if (e.target !== input) {
				return;
			}

			var list = items();
			var key = e.key || '';

			if (key === 'ArrowDown' || key === 'ArrowUp') {
				if (dd.hidden || !list.length) {
					return;
				}

				e.preventDefault();
				highlight(key === 'ArrowDown' ? (active + 1) % list.length : (active - 1 + list.length) % list.length);
			} else if (key === 'Enter') {
				if (!dd.hidden && active > -1 && list[active]) {
					e.preventDefault();
					e.stopPropagation();
					remember(input.value);
					window.location.href = list[active].getAttribute('href');
				}
			} else if (key === 'Escape') {
				open(false);
			}
		}, true);

		// the search button / Enter (core navigates): remember the query
		document.addEventListener('click', function (e) {
			var t = e.target;

			while (t && t !== document) {
				if (t.parentNode && t.parentNode.className && String(t.parentNode.className).indexOf('input-group-btn') !== -1 && box.contains(t)) {
					remember(input.value);

					return;
				}

				t = t.parentNode;
			}

			if (!box.contains(e.target)) {
				open(false);
			}
		}, true);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}(window, document));

/* Carousel (SPEC-033 REQ-HOM-050/051): CSS scroll-snap track + prev/next buttons, optional dots and autoplay. No library.
   Markup: .ocs-carousel[data-autoplay=ms][data-dots=1] > .ocs-carousel-track > .ocs-carousel-item ... + buttons .ocs-carousel-prev/next.
   Autoplay pauses on hover/focus and is off under prefers-reduced-motion. Without JS the track is still a swipeable scroller. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};

	function init(root) {
		var track = root.querySelector('.ocs-carousel-track');
		var prev = root.querySelector('.ocs-carousel-prev');
		var next = root.querySelector('.ocs-carousel-next');
		var dotsBox = root.querySelector('.ocs-carousel-dots');
		var items = track ? track.children : [];
		var timer = null;
		var paused = false;
		var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		if (!track || !items.length) {
			return;
		}

		function step() {
			var first = items[0];
			var gap = parseFloat(window.getComputedStyle(track).columnGap || window.getComputedStyle(track).gap) || 0;

			return first.getBoundingClientRect().width + gap;
		}

		function index() {
			return Math.round(track.scrollLeft / step());
		}

		function go(i) {
			var max = Math.max(0, items.length - Math.max(1, Math.round(track.clientWidth / step())));

			i = i < 0 ? max : (i > max ? 0 : i);
			track.scrollTo({left: i * step(), behavior: reduced ? 'auto' : 'smooth'});
		}

		function update() {
			var i = index();
			var max = items.length - Math.max(1, Math.round(track.clientWidth / step()));

			if (prev) {
				prev.hidden = items.length <= 1 || max <= 0;
			}

			if (next) {
				next.hidden = items.length <= 1 || max <= 0;
			}

			if (dotsBox) {
				var dots = dotsBox.children;

				for (var d = 0; d < dots.length; d++) {
					dots[d].setAttribute('aria-current', d === i ? 'true' : 'false');
				}
			}
		}

		if (dotsBox && items.length > 1) {
			for (var n = 0; n < items.length; n++) {
				(function (k) {
					var b = document.createElement('button');

					b.type = 'button';
					b.setAttribute('aria-label', (dotsBox.getAttribute('data-label') || '') + ' ' + (k + 1));
					b.addEventListener('click', function () {
						go(k);
					});
					dotsBox.appendChild(b);
				}(n));
			}
		}

		if (prev) {
			prev.addEventListener('click', function () {
				go(index() - 1);
			});
		}

		if (next) {
			next.addEventListener('click', function () {
				go(index() + 1);
			});
		}

		track.addEventListener('scroll', function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(update, 80);
		}, {passive: true});

		root.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft') {
				go(index() - 1);
			} else if (e.key === 'ArrowRight') {
				go(index() + 1);
			}
		});

		var every = parseInt(root.getAttribute('data-autoplay'), 10) || 0;

		if (every > 0 && !reduced && items.length > 1) {
			var play = window.setInterval(function () {
				if (!paused && !document.hidden) {
					go(index() + 1);
				}
			}, every);

			root.addEventListener('mouseenter', function () { paused = true; });
			root.addEventListener('mouseleave', function () { paused = false; });
			root.addEventListener('focusin', function () { paused = true; });
			root.addEventListener('focusout', function () { paused = false; });
			root.setAttribute('data-playing', String(play));
		}

		window.addEventListener('resize', update);
		update();
	}

	OCS.carousel = {init: init};

	function boot() {
		var roots = document.querySelectorAll('.ocs-carousel');

		for (var i = 0; i < roots.length; i++) {
			init(roots[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}(window, document));

/* Newsletter form (SPEC-033 REQ-HOM-030): posts the block form as JSON and shows the answer in place. */
(function (window, document) {
	'use strict';

	function bind(form) {
		var box = form.querySelector('.ocs-news-msg');

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			var btn = form.querySelector('button[type="submit"]');

			box.textContent = '';
			box.className = 'ocs-news-msg';
			btn.disabled = true;

			window.fetch(form.getAttribute('action'), {method: 'POST', body: new FormData(form), credentials: 'same-origin'}).then(function (r) {
				return r.json();
			}).then(function (json) {
				btn.disabled = false;

				if (json.success) {
					box.textContent = json.success;
					box.className = 'ocs-news-msg ocs-news-ok';
					form.reset();
				} else {
					var errors = json.errors ? Object.keys(json.errors).map(function (k) { return json.errors[k]; }) : [];

					box.textContent = errors.length ? errors.join(' ') : (json.error || '');
					box.className = 'ocs-news-msg ocs-news-err';
				}
			}).catch(function () {
				btn.disabled = false;
				box.textContent = form.getAttribute('data-failed') || '';
				box.className = 'ocs-news-msg ocs-news-err';
			});
		});
	}

	function boot() {
		var forms = document.querySelectorAll('.ocs-news-form');

		for (var i = 0; i < forms.length; i++) {
			bind(forms[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}(window, document));

/* Product section bar (SPEC-035 REQ-PRD-001): tab anchors open the Bootstrap tab, the others scroll; the current section is highlighted. */
(function (window, document) {
	'use strict';

	function boot() {
		var bar = document.getElementById('ocs-sections');

		if (!bar) {
			return;
		}

		var links = [].slice.call(bar.querySelectorAll('a'));
		var header = document.querySelector('header');
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		function mark(href) {
			links.forEach(function (a) {
				a.classList.toggle('is-current', a.getAttribute('href') === href);
			});
		}

		function offset() {
			return (header ? header.offsetHeight : 0) + bar.offsetHeight + 8;
		}

		bar.style.setProperty('--ocs-hdr', (header ? header.offsetHeight : 0) + 'px');

		links.forEach(function (a) {
			a.addEventListener('click', function (e) {
				var href = a.getAttribute('href');
				var target = document.querySelector(href);

				if (!target) {
					return;
				}

				e.preventDefault();

				if (a.getAttribute('data-ocs-tab') && window.jQuery) {
					window.jQuery('.nav-tabs a[href="' + href + '"]').tab('show');
					target = document.querySelector('.nav-tabs');
				}

				window.scrollTo({top: target.getBoundingClientRect().top + window.pageYOffset - offset(), behavior: reduce ? 'auto' : 'smooth'});
				mark(href);
			});
		});

		if (window.jQuery) {
			window.jQuery(document).on('shown.bs.tab', '.nav-tabs a[data-toggle="tab"]', function () {
				mark(this.getAttribute('href'));
			});
		}

		if ('IntersectionObserver' in window) {
			var io = new window.IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					if (en.isIntersecting) {
						mark('#' + en.target.id);
					}
				});
			}, {rootMargin: '-20% 0px -60% 0px'});

			['ocs-accessories', 'ocs-similar'].forEach(function (id) {
				var el = document.getElementById(id);

				if (el) {
					io.observe(el);
				}
			});
		}

		mark('#tab-description');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}(window, document));

/* City selector (SPEC-032 REQ-CTY-002): header prompt, popup with search, cookie, delivery block refreshed without reload. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};

	function getCookie(name) {
		var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));

		return m ? decodeURIComponent(m[1]) : '';
	}

	function setCookie(name, value) {
		document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=31536000; SameSite=Lax';
	}

	function refreshBlock(cityId) {
		var wrap = document.getElementById('ocs-delivery-wrap');
		var box = wrap && wrap.querySelector('.ocs-delivery');

		if (!box) {
			return;
		}

		var url = box.getAttribute('data-url');

		window.fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'city_id=' + encodeURIComponent(cityId), {credentials: 'same-origin'}).then(function (r) {
			return r.text();
		}).then(function (html) {
			wrap.innerHTML = html;
		});
	}

	function choose(cityId, label) {
		setCookie('ocs_city', cityId);
		setCookie('ocs_city_ok', '1');

		var prompt = document.getElementById('ocs-city-prompt');

		if (prompt) {
			prompt.hidden = true;
		}

		document.querySelectorAll('.ocs-city-strip .ocs-city-btn').forEach(function (b) {
			b.setAttribute('data-city', cityId);

			if (label) {
				b.innerHTML = '<i class="fa fa-map-marker"></i> ' + label;
			}
		});

		OCS.modal.close();
		refreshBlock(cityId);
	}

	function openSelect(url) {
		window.fetch(url, {credentials: 'same-origin'}).then(function (r) {
			return r.text();
		}).then(function (html) {
			if (!html) {
				return;
			}

			var holder = document.createElement('div');

			holder.innerHTML = html;

			var root = holder.querySelector('.ocs-city-select');

			OCS.modal.open({content: holder, closeLabel: root ? root.getAttribute('data-close') : '', labelledBy: 'ocs-city-title'});

			var search = holder.querySelector('.ocs-city-search');
			var items = [].slice.call(holder.querySelectorAll('.ocs-city-list li'));

			search.addEventListener('input', function () {
				var q = search.value.trim().toLowerCase();

				items.forEach(function (li) {
					li.hidden = q !== '' && li.textContent.toLowerCase().indexOf(q) === -1;
				});
			});

			holder.addEventListener('click', function (e) {
				var b = e.target.closest ? e.target.closest('.ocs-city-choice') : null;

				if (b) {
					choose(b.getAttribute('data-city'), b.textContent);
				}
			});
		});
	}

	function boot() {
		var prompt = document.getElementById('ocs-city-prompt');
		var btn = document.querySelector('.ocs-city-strip .ocs-city-btn');

		if (prompt && !getCookie('ocs_city_ok')) {
			prompt.hidden = false;
		}

		document.addEventListener('click', function (e) {
			var el = e.target.closest ? e.target.closest('[data-ocs-city-open],[data-ocs-city-yes]') : null;

			if (!el) {
				return;
			}

			if (el.hasAttribute('data-ocs-city-yes')) {
				choose(btn ? btn.getAttribute('data-city') : '', '');

				return;
			}

			var url = el.getAttribute('data-select') || (btn && btn.getAttribute('data-select')) || (document.querySelector('.ocs-delivery') || {getAttribute: function () { return ''; }}).getAttribute('data-select');

			if (url) {
				e.preventDefault();
				openSelect(url);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}(window, document));
