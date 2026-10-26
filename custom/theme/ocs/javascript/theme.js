document.documentElement.className += ' ocs-js'; // markers for CSS: forms and buttons that need JS are shown only when it runs (SPEC-049)

/* Theme ocs - namespaced helpers (SPEC-006 REQ-THM-023, SPEC-023 REQ-DSG-013). ES5, no globals except window.OCS.
   OCS.modal is the ONLY popup implementation; modules must use it. Closes on overlay click and Esc. */

/* Same-origin endpoints (SPEC-025 REQ-QOR-032): URLs are built from config_url; opened under another host, they would be cross-origin and
   fail silently, so requests to the configured origin go to the page's own origin. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};
	var nativeFetch = window.fetch;

	function fix(url) {
		try {
			var target = new window.URL(url, document.baseURI);
			var base = new window.URL(document.baseURI);

			if (target.origin === base.origin && target.origin !== window.location.origin) {
				return window.location.origin + target.pathname + target.search + target.hash;
			}
		} catch (e) { /* not a URL: leave it to the browser */ }

		return url;
	}

	OCS.sameOrigin = fix;

	/* inline SVG icon from the sprite (SPEC-042); name = sprite id without i- */
	OCS.icon = function (name) {
		return '<svg class="ocs-i" aria-hidden="true"><use href="#i-' + name + '"/></svg>';
	};

	/* SeoPro 301-redirects every non-POST request with product_id (its AJAX detection never fires), so popup bodies are POSTed. */
	OCS.productBody = function (id) {
		var body = new window.FormData();

		body.append('product_id', id);

		return body;
	};

	if (nativeFetch && window.URL) {
		window.fetch = function (input, init) {
			return nativeFetch.call(this, typeof input === 'string' ? fix(input) : input, init);
		};
	}

	document.addEventListener('DOMContentLoaded', function () {
		if (window.jQuery && window.jQuery.ajaxPrefilter) {
			window.jQuery.ajaxPrefilter(function (options) {
				if (options.url) {
					options.url = fix(options.url);
				}
			});
		}
	});
}(window, document));

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
			dialog.className = 'ocs-modal-dialog' + (opts.className ? ' ' + opts.className : '');
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

		// footer groups: collapsed on phones, always open from 768 px (SPEC-047 REQ-FTR-003)
		var wide = window.matchMedia ? window.matchMedia('(min-width: 768px)') : null;

		function syncGroups() {
			var groups = document.querySelectorAll('details.ocs-fgroup');
			var open = wide ? wide.matches : window.innerWidth >= 768;

			for (var i = 0; i < groups.length; i++) {
				if (open) {
					groups[i].setAttribute('open', '');
				} else {
					groups[i].removeAttribute('open');
				}
			}
		}

		syncGroups();

		if (wide && wide.addEventListener) {
			wide.addEventListener('change', syncGroups);
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
		var collapse = nav.querySelector('.navbar-collapse');

		// the full-screen mobile catalogue locks the page scroll and closes on Escape (SPEC-043 REQ-MNU-002)
		if (collapse && window.jQuery) {
			// the header is a size container (a stacking context): while open the panel goes to the top layer (Popover API), else --z-menu
			var topLayer = typeof collapse.showPopover === 'function';

			window.jQuery(collapse).on('shown.bs.collapse', function () {
				document.documentElement.classList.add('ocs-menu-open');

				if (topLayer && isMobile()) {
					collapse.setAttribute('popover', 'manual');

					try {
						collapse.showPopover();
					} catch (e) {
						collapse.removeAttribute('popover');
					}
				}
			}).on('hide.bs.collapse', function () {
				if (topLayer && collapse.hasAttribute('popover')) {
					try {
						collapse.hidePopover();
					} catch (e) { /* already closed */ }

					collapse.removeAttribute('popover');
				}
			}).on('hidden.bs.collapse', function () {
				document.documentElement.classList.remove('ocs-menu-open');
			});

			document.addEventListener('keydown', function (e) {
				var close = nav.querySelector('.ocs-mega-close');

				if (e.key === 'Escape' && isMobile() && collapse.classList.contains('in') && close) {
					close.click();
				}
			});
		}
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

	// the product fields (quantity, options) are read when the popup opens
	function collect(data, box) {
		box = box || document.getElementById('product');

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

	function bind(root, snapshot) {
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

			if (snapshot) {
				snapshot.forEach(function (value, key) {
					data.append(key, value);
				});
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
			var snapshot = null;

			if (button.getAttribute('data-collect') === '1') {
				snapshot = new window.FormData();
				collect(snapshot, button.closest('[data-ocs-product]'));
			}

			if (!url || !id || !window.fetch || !OCS.modal) {
				return;
			}

			button.disabled = true;

			window.fetch(url, {method: 'POST', body: OCS.productBody(id), credentials: 'same-origin'}).then(function (r) {
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
				bind(holder, snapshot);

				// SPEC-036 REQ-CRD-003: a chosen instalment plan goes into the quick-order comment (the visitor can edit it)
				if (button.classList.contains('ocs-quick-btn') && OCS.credit && OCS.credit.note) {
					var comment = holder.querySelector('textarea[name="comment"]');

					if (comment && !comment.value) {
						comment.value = OCS.credit.note;
					}
				}
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
			button.innerHTML = '<span id="cart-total">' + OCS.icon('cart') + ' ' + json.total_text + '</span>';
		}
	}

	function render(json) {
		updateHeader(json);

		if (json.ids && OCS.card) {
			OCS.card.setCart(json.ids);
		}

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

/* Carousels on Swiper (SPEC-037 REQ-SWP-001..005). Swiper 3.4.2 ships with OpenCart core and is loaded by the theme header.
   Markup contract: .ocs-carousel[data-autoplay=ms] > .ocs-carousel-track > .ocs-carousel-item ... + buttons .ocs-carousel-prev/next
   and an optional .ocs-carousel-dots[data-label]. The swiper-* classes are added here, so without Swiper (or JS) the track stays a
   plain swipeable scroller. Autoplay pauses on hover/focus and is off under prefers-reduced-motion. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};
	var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function init(root) {
		var track = root.querySelector('.ocs-carousel-track');
		var prev = root.querySelector('.ocs-carousel-prev');
		var next = root.querySelector('.ocs-carousel-next');
		var dots = root.querySelector('.ocs-carousel-dots');

		if (!track || !track.children.length || typeof window.Swiper !== 'function' || root.swiper) {
			return;
		}

		var every = reduced ? 0 : (parseInt(root.getAttribute('data-autoplay'), 10) || 0);
		var single = root.classList.contains('ocs-hero') && !root.classList.contains('ocs-hero-multi');
		var items = track.children;

		root.classList.add('swiper-container');
		track.classList.add('swiper-wrapper');

		for (var i = 0; i < items.length; i++) {
			items[i].classList.add('swiper-slide');
		}

		var options = {
			slidesPerView: single ? 1 : 'auto',
			spaceBetween: single ? 0 : 16,
			freeMode: !single,
			autoplay: every > 0 && items.length > 1 ? every : 0,
			autoplayDisableOnInteraction: false,
			nextButton: next || null,
			prevButton: prev || null,
			pagination: dots && items.length > 1 ? dots : null,
			paginationClickable: true,
			a11y: true,
			prevSlideMessage: prev ? prev.getAttribute('aria-label') : '',
			nextSlideMessage: next ? next.getAttribute('aria-label') : '',
			paginationBulletMessage: (dots ? dots.getAttribute('data-label') : '') + ' {{index}}',
			watchSlidesVisibility: true
		};

		var swiper = new window.Swiper(root, options);

		function buttons() {
			// Swiper 3 has no "hide when everything fits": the arrows follow the measured widths
			var fits = swiper.virtualSize <= swiper.size + 1;

			[prev, next].forEach(function (b) {
				if (b) {
					b.hidden = fits || items.length <= 1;
				}
			});

			if (dots) {
				dots.hidden = items.length <= 1;
			}
		}

		function refresh() {
			swiper.update(true);
			buttons();
		}

		window.addEventListener('resize', refresh);
		window.addEventListener('load', refresh);

		if (options.autoplay) {
			root.addEventListener('mouseenter', function () { swiper.stopAutoplay(); });
			root.addEventListener('mouseleave', function () { swiper.startAutoplay(); });
			root.addEventListener('focusin', function () { swiper.stopAutoplay(); });
			root.addEventListener('focusout', function () { swiper.startAutoplay(); });
		}

		// keyboard users tab through the links inside the slides: bring the focused slide into view
		root.addEventListener('focusin', function (e) {
			var slide = e.target.closest ? e.target.closest('.swiper-slide') : null;

			root.scrollLeft = 0;

			if (slide) {
				swiper.slideTo([].indexOf.call(items, slide));
			}
		});

		root.swiper = swiper;
		root.ocsRefresh = refresh;
		buttons();
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

/* YouTube slides (SPEC-048 REQ-VID-002, 004): a poster button; the player (youtube-nocookie iframe) is created on the click only and removed when the slide is left. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};

	function id(button) {
		var v = button.getAttribute('data-ocs-video') || '';

		return /^[A-Za-z0-9_-]{11}$/.test(v) ? v : '';
	}

	OCS.video = {
		play: function (button) {
			var v = id(button);

			if (!v || button.classList.contains('is-playing')) {
				return;
			}

			var frame = document.createElement('iframe');

			frame.className = 'ocs-vid-frame';
			frame.src = 'https://www.youtube-nocookie.com/embed/' + v + '?autoplay=1&rel=0';
			frame.title = button.getAttribute('aria-label') || 'YouTube';
			frame.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen');
			frame.setAttribute('allowfullscreen', '');
			frame.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups');
			frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
			button.classList.add('is-playing');
			button.appendChild(frame);
		},

		stop: function (root) {
			var frames = (root || document).querySelectorAll('.ocs-vid.is-playing');

			for (var i = 0; i < frames.length; i++) {
				var f = frames[i].querySelector('iframe');

				if (f) {
					frames[i].removeChild(f);
				}

				frames[i].classList.remove('is-playing');
			}
		}
	};

	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.ocs-vid[data-ocs-video]') : null;

		if (b && !b.classList.contains('is-playing')) {
			e.preventDefault();
			OCS.video.play(b);
		}
	});
}(window, document));

/* Product gallery on two linked Swipers (SPEC-037 REQ-SWP-004): the main image swipes, the thumbnail strip follows and selects.
   The links keep the core hooks (.thumbnails > a.thumbnail), so the Magnific zoom popup of the product page still works. */
(function (window, document) {
	'use strict';

	function boot() {
		var box = document.querySelector('[data-ocs-gallery]');

		if (!box || typeof window.Swiper !== 'function') {
			return;
		}

		var mainEl = box.querySelector('.ocs-gal-main');
		var thumbEl = box.querySelector('.ocs-gal-thumbs');
		var thumbs = null;
		var main = new window.Swiper(mainEl, {
			spaceBetween: 8,
			pagination: box.querySelector('.ocs-gal-dots'),
			paginationClickable: true,
			onSlideChangeStart: function (s) {
				mark(s.activeIndex);

				if (window.OCS && window.OCS.video) {
					window.OCS.video.stop(mainEl);
				}
			}
		});

		function mark(i) {
			if (!thumbs) {
				return;
			}

			[].forEach.call(thumbEl.querySelectorAll('.swiper-slide'), function (el, n) {
				el.classList.toggle('is-current', n === i);
			});

			thumbs.slideTo(Math.max(0, i - 1));
		}

		if (thumbEl) {
			// wide galleries put the strip beside the photo (vertical), narrow ones under it; the CSS container query uses the same width
			thumbs = new window.Swiper(thumbEl, {direction: box.getBoundingClientRect().width >= 512 ? 'vertical' : 'horizontal', slidesPerView: 'auto', spaceBetween: 8, freeMode: true, slideToClickedSlide: false});

			[].forEach.call(thumbEl.querySelectorAll('.swiper-slide'), function (el, n) {
				el.setAttribute('tabindex', '0');
				el.setAttribute('role', 'button');
				el.addEventListener('click', function () {
					main.slideTo(n);
				});
				el.addEventListener('keydown', function (e) {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						main.slideTo(n);
					}
				});
			});

			mark(0);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}(window, document));

/* Card actions (SPEC-044): buy / wishlist / compare without scrolling the page, active states that survive a reload, an "in cart" button.
   Wishlist and compare live in cookies (ocs_wish, ocs_cmp: comma-separated product ids); the server copies them into the session
   (ocs_bookmarks events). The core cart/wishlist/compare globals are replaced here: they scroll the page to the top and know nothing
   about states. Server data comes from <body data-ocs-bk*>; without it core behaviour stays. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};
	var KEY = {wish: 'ocs_wish', cmp: 'ocs_cmp'};
	var MAX = {wish: 100, cmp: 4};
	var cfg = null;
	var cart = [];
	var queued = false;

	function readCookie(name) {
		var parts = ('; ' + document.cookie).split('; ' + name + '=');

		return parts.length === 2 ? parts.pop().split(';').shift() : '';
	}

	function writeCookie(name, value) {
		document.cookie = name + '=' + value + '; path=/; max-age=' + (cfg.days * 86400) + '; SameSite=Lax' + (window.location.protocol === 'https:' ? '; Secure' : '');
	}

	function list(type) {
		var out = [];

		readCookie(KEY[type]).split(',').forEach(function (x) {
			var n = parseInt(x, 10);

			if (n > 0 && out.indexOf(n) === -1) {
				out.push(n);
			}
		});

		return out.slice(-MAX[type]);
	}

	function save(type, ids) {
		writeCookie(KEY[type], ids.join(','));
	}

	function idOf(button, fn) {
		var m = new RegExp(fn + "\\('?(\\d+)").exec(button.getAttribute('onclick') || '');

		return m ? parseInt(m[1], 10) : 0;
	}

	function setTitle(b, text) {
		if (!text) {
			return;
		}

		if (b.hasAttribute('data-original-title')) {
			b.setAttribute('data-original-title', text);
		}

		b.setAttribute('title', text);
		b.setAttribute('aria-label', text);
	}

	function markToggle(b, on, onText, offText) {
		b.classList.toggle('is-active', on);
		b.setAttribute('aria-pressed', on ? 'true' : 'false');
		setTitle(b, on ? onText : offText);
	}

	function markCart(b, id, on) {
		var iconOnly = b.classList.contains('ocs-cart-btn');

		if (on && !b.classList.contains('is-in-cart')) {
			b._ocsHtml = b.innerHTML;
			b._ocsTitle = b.getAttribute('title');
			b.classList.add('is-in-cart');
			b.innerHTML = OCS.icon('check') + (iconOnly ? '' : ' ' + cfg.t.in_cart);
			setTitle(b, cfg.t.in_cart);

			if (window.jQuery && b.id === 'button-cart') {
				window.jQuery(b).data('resetText', b.innerHTML);
			}
		} else if (!on && b.classList.contains('is-in-cart')) {
			b.classList.remove('is-in-cart');
			b.innerHTML = b._ocsHtml || b.innerHTML;

			if (b._ocsTitle) {
				b.setAttribute('title', b._ocsTitle);
				b.setAttribute('aria-label', b._ocsTitle);
			}

			if (window.jQuery && b.id === 'button-cart') {
				window.jQuery(b).data('resetText', b.innerHTML);
			}
		}
	}

	function refresh() {
		queued = false;

		var wish = list('wish');
		var cmp = list('cmp');
		var all = document.querySelectorAll('[onclick*="wishlist.add("], [onclick*="compare.add("], [onclick*="cart.add("], #button-cart, [data-ocs-buy]');
		var i;

		for (i = 0; i < all.length; i++) {
			var b = all[i];
			var id;

			if (b.id === 'button-cart' || b.hasAttribute('data-ocs-buy')) {
				var hidden = document.querySelector('#product input[name="product_id"]');

				id = hidden ? parseInt(hidden.value, 10) : 0;
				markCart(b, id, cart.indexOf(id) !== -1);
			} else if ((id = idOf(b, 'wishlist\\.add'))) {
				markToggle(b, wish.indexOf(id) !== -1, cfg.t.wish_on, cfg.t.wish_off);
			} else if ((id = idOf(b, 'compare\\.add'))) {
				markToggle(b, cmp.indexOf(id) !== -1, cfg.t.cmp_on, cfg.t.cmp_off);
			} else if ((id = idOf(b, 'cart\\.add'))) {
				markCart(b, id, cart.indexOf(id) !== -1);
			}
		}
	}

	function later() {
		if (!queued) {
			queued = true;
			window.setTimeout(refresh, 30);
		}
	}

	function counter(type) {
		var el = type === 'wish' ? document.querySelector('#wishlist-total .ocs-sr') : document.getElementById('compare-total');
		var n = list(type).length;

		if (el) {
			el.textContent = el.textContent.replace(/\d+/, String(n));

			if (el.parentNode) {
				el.parentNode.setAttribute('title', el.textContent);
			}
		}
	}

	function toggle(type, id) {
		id = parseInt(id, 10);

		var ids = list(type);
		var at = ids.indexOf(id);
		var action = at === -1 ? 'add' : 'remove';

		if (at === -1) {
			ids.push(id);
			ids = ids.slice(-MAX[type]);
		} else {
			ids.splice(at, 1);
		}

		save(type, ids);
		refresh();
		counter(type);

		// the account list of a logged-in customer follows (the answer is not needed)
		if (type === 'wish' && cfg.sync && window.fetch) {
			var body = new window.FormData();

			body.append('type', type);
			body.append('action', action);
			body.append('product_id', id);
			window.fetch(cfg.sync, {method: 'POST', body: body, credentials: 'same-origin', keepalive: true}).catch(function () {});
		}
	}

	function cartAdd(id, quantity) {
		var $ = window.jQuery;

		$.ajax({
			url: 'index.php?route=checkout/cart/add',
			type: 'post',
			data: 'product_id=' + id + '&quantity=' + (typeof quantity !== 'undefined' ? quantity : 1),
			dataType: 'json',
			beforeSend: function () {
				$('#cart > button').button('loading');
			},
			complete: function () {
				$('#cart > button').button('reset');
			},
			success: function (json) {
				$('.alert-dismissible, .text-danger').remove();

				if (json.redirect) {
					window.location = json.redirect;
				}

				if (json.success) {
					window.setTimeout(function () {
						$('#cart > button').html('<span id="cart-total">' + OCS.icon('cart') + ' ' + json.total + '</span>');
					}, 100);

					$('#cart > ul').load('index.php?route=common/cart/info ul li');
				}
			},
			error: function (xhr, ajaxOptions, thrownError) {
				window.alert(thrownError + '\r\n' + xhr.statusText + '\r\n' + xhr.responseText);
			}
		});
	}

	OCS.card = {
		refresh: refresh,
		toggle: toggle,
		setCart: function (ids) {
			cart = (ids || []).map(function (x) {
				return parseInt(x, 10);
			});
			refresh();
		}
	};

	function boot() {
		var body = document.body;

		if (!body.getAttribute('data-ocs-bk') || !window.jQuery) {
			return;
		}

		try {
			cfg = {sync: body.getAttribute('data-ocs-bk'), cart: body.getAttribute('data-ocs-bk-cart'), days: parseInt(body.getAttribute('data-ocs-bk-days'), 10) || 90, t: JSON.parse(body.getAttribute('data-ocs-bk-t') || '{}')};
		} catch (e) {
			return;
		}

		cart = (body.getAttribute('data-ocs-bk-ids') || '').split(',').map(function (x) {
			return parseInt(x, 10);
		}).filter(function (x) {
			return x > 0;
		});

		if (window.wishlist) {
			window.wishlist.add = function (id) { toggle('wish', id); };
		}

		if (window.compare) {
			window.compare.add = function (id) { toggle('cmp', id); };
		}

		if (window.cart) {
			window.cart.add = cartAdd;
		}

		// every successful add (card, module, product page, wishlist table) marks the button
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

			var m = /product_id=(\d+)/.exec(typeof settings.data === 'string' ? settings.data : '');

			if (json && json.success && m && cart.indexOf(parseInt(m[1], 10)) === -1) {
				cart.push(parseInt(m[1], 10));
				// Bootstrap's button('reset') runs after the response and would put the old label back
				window.setTimeout(refresh, 150);
			}
		});

		document.addEventListener('click', function (e) {
			var t = e.target.closest ? e.target.closest('.is-in-cart') : null;

			// a product that is in the cart opens the cart instead of adding again
			if (t) {
				e.preventDefault();
				e.stopPropagation();

				if (document.body.getAttribute('data-ocs-cart') && OCS.cart && OCS.cart.open) {
					OCS.cart.open('');
				} else {
					window.location = cfg.cart;
				}

				return;
			}

			// wishlist table / compare page: the link removes on the server, the cookie follows now
			var link = e.target.closest ? e.target.closest('a[data-ocs-wish-remove], #product-compare a[href*="remove="]') : null;

			if (link) {
				var wish = link.hasAttribute('data-ocs-wish-remove');
				var id = parseInt(wish ? link.getAttribute('data-ocs-wish-remove') : (/remove=(\d+)/.exec(link.getAttribute('href')) || [0, 0])[1], 10);

				if (id) {
					var type = wish ? 'wish' : 'cmp';
					var ids = list(type);

					if (ids.indexOf(id) !== -1) {
						ids.splice(ids.indexOf(id), 1);
						save(type, ids);
					}
				}
			}
		}, true);

		refresh();

		if (window.MutationObserver) {
			new window.MutationObserver(function (records) {
				for (var i = 0; i < records.length; i++) {
					if (records[i].addedNodes.length) {
						later();

						return;
					}
				}
			}).observe(document.body, {childList: true, subtree: true});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}(window, document));

/* Popups for the review and question forms (SPEC-049 REQ-REV-001): the form box of the page is moved into OCS.modal and back on close, so ids and the
   core review script keep working. Without JS the boxes stay in the section and are visible. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};
	var active = null;

	function restore() {
		if (!active) {
			return;
		}

		while (active.holder.firstChild) {
			active.box.appendChild(active.holder.firstChild);
		}

		active = null;
	}

	OCS.popup = {
		open: function (id) {
			var box = document.getElementById(id);

			if (!box || !OCS.modal || active) {
				return;
			}

			var holder = document.createElement('div');
			var title = box.querySelector('.ocs-form-title');

			holder.className = 'ocs-pop';
			active = {box: box, holder: holder};

			while (box.firstChild) {
				holder.appendChild(box.firstChild);
			}

			OCS.modal.open({content: holder, className: 'ocs-pop-dialog', closeLabel: box.getAttribute('data-close') || '', labelledBy: title ? title.id : '', onClose: restore});
		},

		close: function () {
			if (active && OCS.modal) {
				OCS.modal.close();
			}
		}
	};

	document.addEventListener('click', function (e) {
		var t = e.target.closest ? e.target.closest('[data-ocs-popup], [data-ocs-popup-close], a[href="#form-review"]') : null;

		if (!t) {
			return;
		}

		e.preventDefault();

		if (t.hasAttribute('data-ocs-popup-close')) {
			OCS.popup.close();
		} else {
			OCS.popup.open(t.getAttribute('data-ocs-popup') || 'ocs-review-box');
		}
	});
}(window, document));

/* Copy buttons (SPEC-049 REQ-COD-001): the product code goes to the clipboard; the icon turns into a check and a live region announces it. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};

	function copy(text) {
		// the Clipboard API needs a secure context and a focused page; any refusal falls back to the textarea trick
		if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
			return window.navigator.clipboard.writeText(text).catch(function () {
				return legacy(text);
			});
		}

		return legacy(text);
	}

	function legacy(text) {
		return new window.Promise(function (resolve, reject) {
			var area = document.createElement('textarea');

			area.value = text;
			area.setAttribute('readonly', '');
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild(area);
			area.select();

			try {
				if (document.execCommand('copy')) {
					resolve();
				} else {
					reject(new Error('copy'));
				}
			} catch (err) {
				reject(err);
			}

			document.body.removeChild(area);
		});
	}

	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('[data-ocs-copy]') : null;

		if (!b) {
			return;
		}

		e.preventDefault();

		copy(b.getAttribute('data-ocs-copy')).then(function () {
			var live = b.parentNode.querySelector('[role="status"]');
			var html = b.innerHTML;

			b.innerHTML = OCS.icon('check');
			b.classList.add('is-done');

			if (live) {
				live.textContent = b.getAttribute('data-done') || '';
			}

			window.setTimeout(function () {
				b.innerHTML = html;
				b.classList.remove('is-done');

				if (live) {
					live.textContent = '';
				}
			}, 1500);
		}).catch(function () { /* the code stays selectable */ });
	});
}(window, document));

/* Product lightbox (SPEC-048 REQ-LBX-001..004): the gallery images open in a white dialog on OCS.modal: large image, arrows, counter, thumbnails,
   the product price and a buy button that forwards to the page's own button. The last item is the video (poster, player on click).
   Keyboard: arrows and Esc; touch: swipe. Without JS the image links open the big picture. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};
	var t = {};

	function node(tag, cls, html) {
		var n = document.createElement(tag);

		if (cls) {
			n.className = cls;
		}

		if (html) {
			n.innerHTML = html;
		}

		return n;
	}

	function label(text) {
		return String(text || '').replace(/[&<>"]/g, function (c) {
			return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c];
		});
	}

	function open(items, start) {
		if (!items.length || !OCS.modal) {
			return;
		}

		var index = Math.max(0, Math.min(start || 0, items.length - 1));
		var root = node('div', 'ocs-lb');
		var stage = node('div', 'ocs-lb-stage');
		var slide = node('div', 'ocs-lb-slide');
		var prev = node('button', 'ocs-lb-nav ocs-lb-prev', OCS.icon('chevron-left'));
		var next = node('button', 'ocs-lb-nav ocs-lb-next', OCS.icon('chevron-right'));
		var count = node('span', 'ocs-lb-count');
		var strip = node('div', 'ocs-lb-thumbs');
		var foot = node('div', 'ocs-lb-foot');
		var thumbs = [];
		var x0 = null;

		prev.type = next.type = 'button';
		prev.setAttribute('aria-label', t.prev || '');
		next.setAttribute('aria-label', t.next || '');
		count.setAttribute('aria-live', 'polite');
		stage.appendChild(prev);
		stage.appendChild(slide);
		stage.appendChild(next);
		stage.appendChild(count);
		root.appendChild(stage);

		items.forEach(function (item, i) {
			var b = node('button', 'ocs-lb-thumb' + (item.type === 'video' ? ' is-video' : ''));

			b.type = 'button';
			b.setAttribute('aria-label', (item.type === 'video' ? t.video : item.alt) || String(i + 1));
			b.innerHTML = '<img src="' + label(item.thumb) + '" alt="" loading="lazy" />' + (item.type === 'video' ? '<span class="ocs-vid-play">' + OCS.icon('play') + '</span>' : '');
			b.addEventListener('click', function () {
				show(i);
			});
			strip.appendChild(b);
			thumbs.push(b);
		});

		foot.appendChild(strip);

		// summary: the price and the page's buy button (the label and the "in cart" state are copied)
		var source = document.querySelector('.ocs-side-card [data-ocs-buy]') || document.querySelector('[data-ocs-buy]');
		var price = document.querySelector('.ocs-side-price');

		if (source) {
			var buy = node('div', 'ocs-lb-buy');

			if (price) {
				buy.appendChild(node('p', 'ocs-lb-price', price.innerHTML));
			}

			var btn = node('button', 'btn btn-primary' + (source.classList.contains('is-in-cart') ? ' is-in-cart' : ''), source.innerHTML);

			btn.type = 'button';
			btn.setAttribute('data-ocs-buy-lb', '');
			btn.addEventListener('click', function () {
				if (btn.classList.contains('is-in-cart')) {
					return; // the in-cart handler of OCS.card opens the cart
				}

				OCS.modal.close();
				source.click();
			});
			buy.appendChild(btn);
			foot.appendChild(buy);
		}

		root.appendChild(foot);

		function preload(i) {
			if (items[i] && items[i].type === 'img') {
				new window.Image().src = items[i].src;
			}
		}

		function show(i) {
			index = (i + items.length) % items.length;

			var item = items[index];

			OCS.video.stop(slide);
			slide.innerHTML = '';

			if (item.type === 'video') {
				var v = node('button', 'ocs-vid', '<img src="' + label(item.poster) + '" alt="" /><span class="ocs-vid-play">' + OCS.icon('play') + '</span>');

				v.type = 'button';
				v.setAttribute('data-ocs-video', item.id);
				v.setAttribute('aria-label', t.play || t.video || '');
				slide.appendChild(v);
			} else {
				var img = node('img', 'ocs-lb-img');

				img.src = item.src;
				img.alt = item.alt || '';
				slide.appendChild(img);
			}

			thumbs.forEach(function (b, n) {
				b.classList.toggle('is-current', n === index);

				if (n === index) {
					b.setAttribute('aria-current', 'true');

					if (b.scrollIntoView) {
						b.scrollIntoView({block: 'nearest', inline: 'nearest'});
					}
				} else {
					b.removeAttribute('aria-current');
				}
			});

			count.textContent = (t.counter || '%s / %s').replace('%s', String(index + 1)).replace('%s', String(items.length));
			preload(index + 1);
			preload(index - 1);
		}

		prev.addEventListener('click', function () {
			show(index - 1);
		});
		next.addEventListener('click', function () {
			show(index + 1);
		});
		prev.hidden = next.hidden = count.hidden = items.length < 2;

		function onKey(e) {
			if (e.key === 'ArrowLeft') {
				show(index - 1);
			} else if (e.key === 'ArrowRight') {
				show(index + 1);
			}
		}

		stage.addEventListener('pointerdown', function (e) {
			x0 = e.clientX;
		});
		stage.addEventListener('pointerup', function (e) {
			if (x0 !== null && Math.abs(e.clientX - x0) > 50 && items.length > 1) {
				show(index + (e.clientX < x0 ? 1 : -1));
			}

			x0 = null;
		});

		document.addEventListener('keydown', onKey);

		OCS.modal.open({content: root, className: 'ocs-lb-dialog', closeLabel: t.close || '', onClose: function () {
			document.removeEventListener('keydown', onKey);
			OCS.video.stop(slide);
		}});
		root.parentNode.setAttribute('aria-label', t.gallery || '');
		show(index);
	}

	OCS.lightbox = {open: open};

	function boot() {
		var box = document.querySelector('[data-ocs-gallery]');

		if (!box) {
			return;
		}

		try {
			t = JSON.parse(box.getAttribute('data-ocs-lb') || '{}');
		} catch (e) {
			t = {};
		}

		function collect() {
			var items = [];

			[].forEach.call(box.querySelectorAll('.ocs-gal-main .swiper-slide'), function (s, n) {
				var a = s.querySelector('a.thumbnail');
				var v = s.querySelector('.ocs-vid[data-ocs-video]');
				var th = box.querySelectorAll('.ocs-gal-thumbs .swiper-slide img')[n];

				if (a) {
					items.push({type: 'img', src: a.getAttribute('href'), thumb: th ? th.getAttribute('src') : a.querySelector('img').getAttribute('src'), alt: a.getAttribute('title') || ''});
				} else if (v) {
					var img = v.querySelector('img');

					items.push({type: 'video', id: v.getAttribute('data-ocs-video'), poster: img ? img.getAttribute('src') : '', thumb: img ? img.getAttribute('src') : ''});
				}
			});

			return items;
		}

		// capture phase: runs before the Magnific handler the core page may have bound to the same links
		box.addEventListener('click', function (e) {
			var a = e.target.closest ? e.target.closest('.ocs-gal-main a.thumbnail') : null;

			if (!a) {
				return;
			}

			e.preventDefault();
			e.stopPropagation();

			var items = collect();
			var n = 0;

			[].forEach.call(box.querySelectorAll('.ocs-gal-main .swiper-slide'), function (s, i) {
				if (s.contains(a)) {
					n = i;
				}
			});

			open(items, n);
		}, true);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}(window, document));

/* Option-level stock (SPEC-037 REQ-OPT-001): a chosen option value that is out of stock swaps "Add to cart" for the pre-order / notify buttons. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};

	function sync(box) {
		var out = !!box.querySelector('option[data-out]:checked, input[data-out]:checked');
		var only = box.querySelectorAll('.ocs-out-only');
		var buy = box.querySelectorAll('#button-cart, .ocs-quick-btn');
		var i;

		for (i = 0; i < only.length; i++) {
			only[i].hidden = !out;
		}

		for (i = 0; i < buy.length; i++) {
			buy[i].hidden = out;
		}
	}

	OCS.out = {sync: sync};

	document.addEventListener('change', function (e) {
		var box = e.target.closest ? e.target.closest('#product') : null;

		if (box) {
			sync(box);
		}
	});

	document.addEventListener('DOMContentLoaded', function () {
		var box = document.getElementById('product');

		if (box) {
			sync(box);
		}
	});
}(window, document));

/* Sticky offsets (SPEC-038): --head-h follows the real height of the sticky header, so toolbars and sidebars stick right under it. */
(function (window, document) {
	'use strict';

	function boot() {
		var head = document.querySelector('header.ocs-head');

		if (!head) {
			return;
		}

		function set() {
			// a header that scrolls away (phones) leaves no offset for the sticky toolbars
			document.documentElement.style.setProperty('--head-h', window.getComputedStyle(head).position === 'sticky' ? head.offsetHeight + 'px' : '0px');
		}

		set();

		if (window.ResizeObserver) {
			new window.ResizeObserver(set).observe(head);
		} else {
			window.addEventListener('resize', set);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}(window, document));

/* Header action counters (SPEC-039 REQ-VIS-002): core and the cart panel write the counts as text ("Wish List (3)", "3 item(s) - 10.00");
   the badges and the cart label mirror them, so nothing of the core markup has to change. */
(function (window, document) {
	'use strict';

	function digits(text) {
		var m = /(\d+)/.exec(text || '');

		return m ? m[1] : '0';
	}

	function boot() {
		var wish = document.getElementById('wishlist-total');
		var compare = document.getElementById('compare-total');
		var cartWrap = document.querySelector('.ocs-head-cart');

		function mirrorLink(src, link) {
			if (!src || !link) {
				return;
			}

			function set() {
				var n = digits(src.textContent);

				link.setAttribute('data-count', n === '0' ? '0' : (parseInt(n, 10) > 99 ? '99+' : n));
			}

			set();

			if (window.MutationObserver) {
				new window.MutationObserver(set).observe(src, {childList: true, characterData: true, subtree: true});
			}
		}

		mirrorLink(wish && wish.querySelector('.ocs-sr'), wish);
		mirrorLink(compare, document.getElementById('ocs-compare-link'));

		var btn = cartWrap && cartWrap.querySelector('#cart > button');

		if (!btn) {
			return;
		}

		var obs = null;

		function render() {
			var n = digits((btn.querySelector('#cart-total') || btn).textContent);

			var badge = parseInt(n, 10) > 99 ? '99+' : n;

			cartWrap.setAttribute('data-count', n);
			cartWrap.setAttribute('data-badge', badge);

			// attr() in the badge's content reads the element the ::after belongs to: the button, not the wrapper
			btn.setAttribute('data-count', n);
			btn.setAttribute('data-badge', badge);

			if (!btn.querySelector('.ocs-act-label')) {
				var label = document.createElement('small');

				label.className = 'ocs-act-label';
				label.textContent = cartWrap.getAttribute('data-label') || '';

				if (obs) {
					obs.disconnect();
				}

				btn.appendChild(label);

				if (obs) {
					obs.observe(btn, {childList: true, subtree: true, characterData: true});
				}
			}
		}

		render();

		if (window.MutationObserver) {
			obs = new window.MutationObserver(render);
			obs.observe(btn, {childList: true, subtree: true, characterData: true});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}(window, document));

/* Tabs of a product shelf (SPEC-039 REQ-VIS-003): the chips switch server-rendered panels; a carousel that was hidden is measured again when it appears. */
(function (document) {
	'use strict';

	document.addEventListener('click', function (e) {
		var chip = e.target.closest ? e.target.closest('[data-ocs-tab]') : null;

		if (!chip) {
			return;
		}

		var box = chip.closest('.ocs-pcarousel');
		var index = chip.getAttribute('data-ocs-tab');

		[].forEach.call(box.querySelectorAll('[data-ocs-tab]'), function (c) {
			var on = c === chip;

			c.classList.toggle('is-active', on);
			c.setAttribute('aria-selected', on ? 'true' : 'false');
		});

		[].forEach.call(box.querySelectorAll('[data-ocs-panel]'), function (p) {
			var on = p.getAttribute('data-ocs-panel') === index;

			p.hidden = !on;

			if (on) {
				var car = p.querySelector('.ocs-carousel');

				if (car && car.ocsRefresh) {
					car.ocsRefresh();
				}
			}
		});
	});
}(document));

/* Sticky product summary (SPEC-039 REQ-VIS-006): shown after the buy button has scrolled above the viewport; its button repeats the page's own. */
(function (window, document) {
	'use strict';

	function boot() {
		var bar = document.getElementById('ocs-sticky-buy');
		var anchor = document.getElementById('product');
		var buys = document.querySelectorAll('[data-ocs-buy]');

		if (!anchor) {
			return;
		}

		// the top bar appears once the page's own button has scrolled away (it is hidden by CSS while the pinned side card shows, SPEC-047)
		if (bar && window.IntersectionObserver) {
			bar.hidden = false;

			new window.IntersectionObserver(function (entries) {
				var e = entries[0];
				var on = !e.isIntersecting && e.boundingClientRect.bottom < 0;

				bar.classList.toggle('is-on', on);
				bar.setAttribute('aria-hidden', on ? 'false' : 'true');
			}).observe(anchor);
		}

		[].forEach.call(buys, function (buy) {
			buy.addEventListener('click', function () {
				var buttons = anchor.querySelectorAll('#button-cart, .ocs-out-only, .ocs-req-btn');
				var target = null;

				[].forEach.call(buttons, function (b) {
					if (!target && !b.hidden && b.offsetParent !== null) {
						target = b;
					}
				});

				if (target) {
					target.click();
				} else {
					anchor.scrollIntoView({behavior: 'smooth', block: 'center'});
				}
			});
		});
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

/* Product section bar (SPEC-035 REQ-PRD-001, SPEC-046 REQ-SEC-003): an anchor menu over the stacked sections; a click scrolls under the sticky header,
   the section in view is highlighted. Without JS the items are plain anchors. */
(function (window, document) {
	'use strict';

	function boot() {
		var bar = document.getElementById('ocs-sections');

		if (!bar) {
			return;
		}

		var links = [].slice.call(bar.querySelectorAll('a'));
		var header = document.querySelector('header.ocs-head') || document.querySelector('header');
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var locked = false;
		var unlock = null;

		function headH() {
			return header && window.getComputedStyle(header).position === 'sticky' ? header.offsetHeight : 0;
		}

		function mark(href) {
			links.forEach(function (a) {
				a.classList.toggle('is-current', a.getAttribute('href') === href);
			});
		}

		bar.style.setProperty('--ocs-hdr', headH() + 'px');

		links.forEach(function (a) {
			a.addEventListener('click', function (e) {
				var href = a.getAttribute('href');
				var target = href && href.charAt(0) === '#' ? document.querySelector(href) : null;

				if (!target) {
					return;
				}

				e.preventDefault();
				locked = true;
				window.clearTimeout(unlock);
				unlock = window.setTimeout(function () {
					locked = false;
				}, 900);
				window.scrollTo({top: target.getBoundingClientRect().top + window.pageYOffset - headH() - bar.offsetHeight - 8, behavior: reduce ? 'auto' : 'smooth'});
				mark(href);

				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, '', href);
				}
			});
		});

		if ('IntersectionObserver' in window) {
			var io = new window.IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					if (en.isIntersecting && !locked) {
						mark('#' + en.target.id);
					}
				});
			}, {rootMargin: '-20% 0px -60% 0px'});

			links.forEach(function (a) {
				var el = document.querySelector(a.getAttribute('href'));

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

		document.querySelectorAll('.ocs-city-top').forEach(function (b) {
			b.setAttribute('data-city', cityId);

			if (label) {
				var icon = b.querySelector('svg');

				while (b.lastChild && b.lastChild !== icon) {
					b.removeChild(b.lastChild);
				}

				b.appendChild(document.createTextNode(' ' + label));
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
		var btn = document.querySelector('.ocs-city-top');

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

/* Instalment block (SPEC-036): payments are recalculated when options change; the chosen plan becomes the quick-order note. Display only. */
(function (window, document) {
	'use strict';

	var OCS = window.OCS = window.OCS || {};

	function payment(price, months, rate, fee) {
		var amount = price * (1 + fee / 100);
		var r = rate / 1200;

		return r <= 0 ? amount / months : amount * r / (1 - Math.pow(1 + r, -months));
	}

	function money(cfg, amount) {
		var v = Math.round(amount * cfg.cur.value * Math.pow(10, cfg.cur.dec)) / Math.pow(10, cfg.cur.dec);
		var parts = v.toFixed(cfg.cur.dec).split('.');

		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, cfg.cur.tp);

		return cfg.cur.left + parts.join(cfg.cur.dp) + cfg.cur.right;
	}

	function boot() {
		var box = document.querySelector('[data-ocs-credit]');

		if (!box) {
			return;
		}

		var cfg;

		try {
			cfg = JSON.parse(box.getAttribute('data-ocs-credit'));
		} catch (e) {
			return;
		}

		var product = document.getElementById('product');
		var items = box.querySelectorAll('li[data-plan]');
		var selected = null;

		function price() {
			var total = cfg.price;

			if (product) {
				[].forEach.call(product.querySelectorAll('select, input[type="radio"]:checked, input[type="checkbox"]:checked'), function (el) {
					var ids = el.tagName === 'SELECT' ? [el.value] : [el.value];

					ids.forEach(function (id) {
						if (cfg.deltas[id]) {
							total += cfg.deltas[id];
						}
					});
				});
			}

			return total;
		}

		function note() {
			OCS.credit = OCS.credit || {};
			OCS.credit.note = '';

			if (selected === null) {
				return;
			}

			var plan = cfg.plans[selected];
			var pay = money(cfg, payment(price(), plan.months, plan.rate, plan.fee));

			OCS.credit.note = cfg.tpl.replace('%bank%', plan.bank).replace('%months%', plan.months).replace('%pay%', pay);
		}

		function refresh() {
			var p = price();
			var any = false;

			[].forEach.call(items, function (li) {
				var i = parseInt(li.getAttribute('data-plan'), 10);
				var plan = cfg.plans[i];
				var fits = p > 0 && p >= plan.min && (plan.max <= 0 || p <= plan.max);

				li.hidden = !fits;
				any = any || fits;

				if (fits) {
					li.querySelector('.ocs-credit-pay').textContent = money(cfg, payment(p, plan.months, plan.rate, plan.fee));
				} else if (selected === i) {
					selected = null;
					li.querySelector('input').checked = false;
				}
			});

			box.hidden = !any;
			note();
		}

		box.addEventListener('change', function (e) {
			if (e.target && e.target.name === 'ocs-credit-plan') {
				selected = parseInt(e.target.value, 10);
				note();
			}
		});

		if (product) {
			product.addEventListener('change', refresh);
		}

		var details = box.querySelector('[data-ocs-credit-details]');
		var terms = box.querySelector('.ocs-credit-terms');

		if (details && terms && OCS.modal) {
			details.addEventListener('click', function () {
				var holder = document.createElement('div');

				[].forEach.call(terms.childNodes, function (n) {
					holder.appendChild(n.cloneNode(true));
				});

				OCS.modal.open({content: holder, closeLabel: terms.getAttribute('data-close'), labelledBy: 'ocs-credit-title'});
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}(window, document));
