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
