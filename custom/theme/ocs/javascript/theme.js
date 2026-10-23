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
