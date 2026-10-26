/* SPEC-043 REQ-BTN-001, REQ-BTN-002, REQ-SPC-001: button metrics check. Paste into the browser console (or run through a browser tool) on a
   storefront page. Reports buttons whose content is off-centre by more than 1 px, icon-to-text gaps under 6 px, touch targets under the
   minimum, inline padding smaller than block padding, and horizontal page overflow. Returns {ok, problems[], checked}. */
(function () {
	var MIN_GAP = 6;
	var MAX_OFFSET = 1;
	var minTarget = innerWidth < 768 ? 44 : 40;
	var problems = [];
	var checked = 0;
	var seen = {};

	var selector = 'button, a.btn, .btn, input[type="submit"], input[type="button"], .ocs-chip, .ocs-link-btn, .ocs-tabbar > a, .ocs-act-btn, [role="button"]';

	function visible(el) {
		var r = el.getBoundingClientRect();
		var cs = getComputedStyle(el);

		return r.width > 6 && r.height > 6 && cs.visibility !== 'hidden' && cs.display !== 'none' && parseFloat(cs.opacity) > 0.05;
	}

	function label(el) {
		var cls = typeof el.className === 'string' ? el.className.trim().split(/\s+/).slice(0, 3).join('.') : '';

		return el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (cls ? '.' + cls : '');
	}

	function textRects(el) {
		var out = [];
		var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
		var node;

		while ((node = walker.nextNode())) {
			if (!node.textContent.trim() || node.parentElement.closest('svg, script, style, [hidden]') || getComputedStyle(node.parentElement).clipPath !== 'none') {
				continue;
			}

			var range = document.createRange();

			range.selectNodeContents(node);

			var r = range.getBoundingClientRect();

			if (r.width > 0 && r.height > 0 && getComputedStyle(node.parentElement).visibility !== 'hidden') {
				out.push(r);
			}
		}

		return out;
	}

	function union(rects) {
		return rects.reduce(function (u, r) {
			return {left: Math.min(u.left, r.left), top: Math.min(u.top, r.top), right: Math.max(u.right, r.right), bottom: Math.max(u.bottom, r.bottom)};
		}, {left: Infinity, top: Infinity, right: -Infinity, bottom: -Infinity});
	}

	function report(el, msg) {
		var key = label(el) + msg.split(' ')[0];

		if (!seen[key]) {
			seen[key] = true;
			problems.push(label(el) + ': ' + msg);
		}
	}

	Array.prototype.forEach.call(document.querySelectorAll(selector), function (el) {
		if (!visible(el) || el.closest('.ocs-modal [data-ocs-skip], #cart .dropdown-menu')) {
			return;
		}

		var texts = textRects(el);
		var icons = Array.prototype.filter.call(el.querySelectorAll('svg'), visible).map(function (s) {
			return s.getBoundingClientRect();
		});

		if (!texts.length && !icons.length) {
			return;
		}

		// an icon drawn as a ::before mask (the header cart button) cannot be measured: skip centring and gap checks for it
		var pseudoIcon = getComputedStyle(el, '::before').content !== 'none' && getComputedStyle(el, '::before').content !== 'normal';

		checked++;

		var box = el.getBoundingClientRect();
		var cs = getComputedStyle(el);
		var content = union(texts.concat(icons));
		var offsetY = (content.top + content.bottom) / 2 - (box.top + box.bottom) / 2;
		var offsetX = (content.left + content.right) / 2 - (box.left + box.right) / 2;
		var centred = cs.justifyContent === 'center' || cs.justifyItems === 'center' || (cs.display.indexOf('flex') < 0 && cs.display.indexOf('grid') < 0 && cs.textAlign === 'center');

		// vertical centring of the content block (text + icons)
		if (!pseudoIcon && Math.abs(offsetY) > MAX_OFFSET && box.height < 120) {
			report(el, 'content is ' + offsetY.toFixed(1) + 'px off vertical centre (line-height ' + cs.lineHeight + ', padding ' + cs.paddingTop + '/' + cs.paddingBottom + ')');
		}

		if (!pseudoIcon && centred && Math.abs(offsetX) > 2 && box.width < 300) {
			report(el, 'content is ' + offsetX.toFixed(1) + 'px off horizontal centre');
		}

		// icon-to-text gap, horizontal or stacked
		if (icons.length && texts.length) {
			var t = union(texts);

			icons.forEach(function (i) {
				var gapX = t.left >= i.right - 1 ? t.left - i.right : i.left >= t.right - 1 ? i.left - t.right : null;
				var gapY = t.top >= i.bottom - 1 ? t.top - i.bottom : i.top >= t.bottom - 1 ? i.top - t.bottom : null;
				var gap = gapX !== null ? gapX : gapY;

				if (gap !== null && gap < MIN_GAP - 0.5) {
					report(el, 'icon-to-text gap is ' + gap.toFixed(1) + 'px (min ' + MIN_GAP + ')');
				}
			});
		}

		// touch target and padding proportions for real buttons
		var isRealButton = el.matches('button, .btn, input[type="submit"], input[type="button"], .ocs-tabbar > a') && !el.matches('.btn-xs, .btn-sm, .btn-link, .close, [data-ocs-compact]') && !el.closest('#top, .pagination, .ocs-qv-dots, .swiper-pagination');

		if (isRealButton && texts.length) {
			if (box.height < minTarget - 0.5) {
				report(el, 'touch target ' + box.height.toFixed(0) + 'px high (min ' + minTarget + ')');
			}

			if (parseFloat(cs.paddingLeft) + 0.5 < parseFloat(cs.paddingTop) && box.width > 60) {
				report(el, 'inline padding ' + cs.paddingLeft + ' is smaller than block padding ' + cs.paddingTop);
			}
		}
	});

	if (document.documentElement.scrollWidth > innerWidth + 1) {
		problems.push('page scrolls horizontally: scrollWidth ' + document.documentElement.scrollWidth + ' > ' + innerWidth);
	}

	return {ok: problems.length === 0, checked: checked, width: innerWidth, problems: problems.slice(0, 60)};
}());
