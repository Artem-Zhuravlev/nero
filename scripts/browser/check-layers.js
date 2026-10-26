/* SPEC-042 REQ-Z-003: stacking check for the storefront. Paste into the browser console (or run through a browser tool) on a page
   where the popups/menus under test are open. It reports every open panel that something else covers, whether a modal covers the
   header and the bottom bar, and overlapping decorations on product cards. Returns {ok, problems[], checked[]}. */
(function () {
	var problems = [];
	var checked = [];

	function visible(el) {
		var r = el.getBoundingClientRect();
		var cs = getComputedStyle(el);

		return r.width > 4 && r.height > 4 && cs.visibility !== 'hidden' && cs.display !== 'none' && parseFloat(cs.opacity) > 0.05;
	}

	function name(el) {
		return el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
	}

	function points(r) {
		var x0 = Math.max(r.left, 0) + 6;
		var x1 = Math.min(r.right, innerWidth) - 6;
		var y0 = Math.max(r.top, 0) + 6;
		var y1 = Math.min(r.bottom, innerHeight) - 6;

		if (x1 <= x0 || y1 <= y0) {
			return [];
		}

		return [[(x0 + x1) / 2, (y0 + y1) / 2], [x0, y0], [x1, y0], [x0, y1], [x1, y1]];
	}

	// 1. open panels must be on top of whatever lies under them
	var panels = ['.dropdown-menu', '.ocs-mega-panel', '.ocs-search-dd', '.ocs-city-pop', '.ocs-home-fly', '.ocs-sticky-buy', '.ocs-tabbar', '.ocs-modal-dialog'];

	panels.forEach(function (sel) {
		Array.prototype.forEach.call(document.querySelectorAll(sel), function (el) {
			if (!visible(el)) {
				return;
			}

			// the full-screen mobile catalogue and modals deliberately cover (or hide) the bottom bar
			if (sel === '.ocs-tabbar' && (document.querySelector('.ocs-mega .navbar-collapse.in') || document.querySelector('.ocs-modal'))) {
				return;
			}

			checked.push(sel);

			points(el.getBoundingClientRect()).forEach(function (p) {
				var top = document.elementFromPoint(p[0], p[1]);

				if (top && top !== el && !el.contains(top) && !top.contains(el)) {
					problems.push('panel ' + name(el) + ' is covered at ' + Math.round(p[0]) + ',' + Math.round(p[1]) + ' by ' + name(top));
				}
			});
		});
	});

	// 2. a modal covers the header and the bottom bar
	var modal = document.querySelector('.ocs-modal');

	if (modal && visible(modal)) {
		['header', '.ocs-tabbar'].forEach(function (sel) {
			var el = document.querySelector(sel);

			if (el && visible(el)) {
				var r = el.getBoundingClientRect();
				var top = document.elementFromPoint(Math.min(Math.max(r.left + r.width / 2, 1), innerWidth - 1), Math.min(Math.max(r.top + r.height / 2, 1), innerHeight - 1));

				checked.push('modal over ' + sel);

				if (top && !modal.contains(top)) {
					problems.push('modal does not cover ' + sel + ' (' + name(top) + ' is on top)');
				}
			}
		});
	}

	// 3. decorations on product cards must not overlap each other
	Array.prototype.forEach.call(document.querySelectorAll('.product-thumb > .image'), function (img) {
		var items = Array.prototype.filter.call(img.querySelectorAll('.ocs-slot > *, .ocs-code, .ocs-badge-discount'), visible);

		for (var i = 0; i < items.length; i++) {
			for (var j = i + 1; j < items.length; j++) {
				var a = items[i].getBoundingClientRect();
				var b = items[j].getBoundingClientRect();

				if (a.left < b.right - 1 && b.left < a.right - 1 && a.top < b.bottom - 1 && b.top < a.bottom - 1) {
					problems.push('card decorations overlap: ' + name(items[i]) + ' and ' + name(items[j]));
				}
			}
		}
	});

	// 4. a third-party fixed element with a huge z-index (like a coupon popup) must not cover the open menu or a modal (REQ-Z-010)
	var probe = document.createElement('div');

	probe.style.cssText = 'position:fixed;inset:0;z-index:5000000;background:transparent;';
	document.body.appendChild(probe);

	['.ocs-mega .navbar-collapse.in', '.ocs-modal'].forEach(function (sel) {
		var el = document.querySelector(sel);

		if (el && visible(el)) {
			checked.push('third-party probe vs ' + sel);

			var top = document.elementFromPoint(innerWidth / 2, innerHeight / 2);

			if (top === probe) {
				problems.push('a z-index 5000000 element covers ' + sel);
			}
		}
	});

	document.body.removeChild(probe);

	checked.push('cards');

	return {ok: problems.length === 0, problems: problems.slice(0, 40), checked: checked.length};
}());
