/* Extension: ocs_novaposhta. Checkout component: city suggestions and a branch/postomat picker bound to the address fields. Owner: SPEC-085.
 * Vanilla JS, no dependencies. It attaches to forms by the selector groups from the server (default checkout, Simple Checkout, custom),
 * re-attaches when the form is re-rendered, and leaves the fields as plain text inputs if anything fails (REQ-NP-023, 041). */
(function (root) {
  'use strict';

  // ---- pure helpers (unit-tested in Node) ----

  function debounce(fn, ms) {
    var t = null;
    return function () {
      var args = arguments, self = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, args); }, ms);
    };
  }

  /** Splits text into [{t, hit}] parts so the first occurrence of the query can be highlighted with text nodes only. */
  function splitMatch(text, query) {
    text = String(text);
    var q = String(query || '').trim().toLowerCase();
    var i = q ? text.toLowerCase().indexOf(q) : -1;
    if (i < 0) { return [{ t: text, hit: false }]; }
    var parts = [];
    if (i > 0) { parts.push({ t: text.slice(0, i), hit: false }); }
    parts.push({ t: text.slice(i, i + q.length), hit: true });
    if (i + q.length < text.length) { parts.push({ t: text.slice(i + q.length), hit: false }); }
    return parts;
  }

  function withParams(base, params) {
    var out = [];
    Object.keys(params).forEach(function (k) { out.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k])); });
    return base + (base.indexOf('?') > -1 ? '&' : '?') + out.join('&');
  }

  function weightLabel(tpl, kg) {
    return kg > 0 ? String(tpl).replace('{n}', kg) : '';
  }

  /** Is the form usable: Ukraine only when configured and the form has a country field. */
  function formActive(cfg, countryValue) {
    if (!cfg.only_ua || !cfg.country_id || countryValue === undefined || countryValue === null || countryValue === '') { return true; }
    return Number(countryValue) === Number(cfg.country_id);
  }

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = { debounce: debounce, splitMatch: splitMatch, withParams: withParams, weightLabel: weightLabel, formActive: formActive };
    return;
  }

  if (typeof document === 'undefined' || root.OCS_NP) { return; }

  // ---- browser part ----

  var script = document.currentScript;
  var cfg = null;
  var T = {};
  var contexts = [];
  var openPop = null;

  function qs(sel) {
    if (!sel) { return null; }
    try { return document.querySelector(sel); } catch (e) { return null; }
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined) { n.textContent = text; }
    return n;
  }

  function setValue(input, value) {
    var proto = input.tagName === 'SELECT' ? HTMLSelectElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(input, value);
  }

  /** Dispatch a native event that our own listeners ignore, so Simple/jQuery handlers still run. */
  function fire(input, type) {
    input.__npSilent = true;
    input.dispatchEvent(new Event(type, { bubbles: true }));
    input.__npSilent = false;
  }

  function post(url, data) {
    return fetch(url, { method: 'POST', body: new URLSearchParams(data), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (!r.ok) { throw new Error('http ' + r.status); } return r.json(); });
  }

  function get(url, signal) {
    return fetch(url, { credentials: 'same-origin', signal: signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (!r.ok) { throw new Error('http ' + r.status); } return r.json(); });
  }

  function place(pop, anchor) {
    var r = anchor.getBoundingClientRect();
    var below = window.innerHeight - r.bottom - 16;
    pop.style.left = Math.max(8, r.left) + 'px';
    pop.style.top = (r.bottom + 4) + 'px';
    pop.style.width = Math.max(r.width, Math.min(320, window.innerWidth - 16)) + 'px';
    pop.style.maxHeight = Math.max(200, Math.min(420, below)) + 'px';
  }

  function closePop() {
    if (openPop) {
      openPop.node.remove();
      window.removeEventListener('scroll', openPop.onMove, true);
      window.removeEventListener('resize', openPop.onMove);
      openPop = null;
    }
  }

  function showPop(anchor, node) {
    closePop();
    document.body.appendChild(node);
    var onMove = function () { place(node, anchor); };
    place(node, anchor);
    window.addEventListener('scroll', onMove, true);
    window.addEventListener('resize', onMove);
    openPop = { node: node, anchor: anchor, onMove: onMove };
  }

  function highlighted(parent, text, query) {
    splitMatch(text, query).forEach(function (p) {
      parent.appendChild(p.hit ? el('mark', '', p.t) : document.createTextNode(p.t));
    });
  }

  function moveActive(list, dir) {
    var items = list.querySelectorAll('[role="option"]');
    if (!items.length) { return null; }
    var cur = -1;
    items.forEach(function (n, i) { if (n.classList.contains('is-active')) { cur = i; } });
    var next = Math.min(items.length - 1, Math.max(0, cur + dir));
    items.forEach(function (n, i) { n.classList.toggle('is-active', i === next); n.setAttribute('aria-selected', i === next ? 'true' : 'false'); });
    items[next].scrollIntoView({ block: 'nearest' });
    return items[next];
  }

  // ---- one address form ----

  function Form(group) {
    this.group = group;
    this.city = qs(group.city);
    this.address = qs(group.address);
    this.zone = qs(group.zone);
    this.country = qs(group.country);
    this.cityRef = '';
    this.whRef = '';
    this.type = 'all';
    this.ctl = null;
  }

  Form.prototype.active = function () {
    return formActive(cfg, this.country ? this.country.value : null);
  };

  Form.prototype.sync = function (data) {
    return post(cfg.urls.select, data).catch(function () { return { ok: false }; });
  };

  Form.prototype.applyRegion = function (res) {
    var self = this;
    if (self.country && res.country_id && String(self.country.value) !== String(res.country_id)) {
      setValue(self.country, String(res.country_id));
      fire(self.country, 'change');
    }
    if (self.zone && res.zone_id) {
      var tries = 0;
      (function wait() {
        var opt = self.zone.querySelector('option[value="' + res.zone_id + '"]');
        if (opt) {
          setValue(self.zone, String(res.zone_id));
          fire(self.zone, 'change');
        } else if (tries++ < 25) {
          setTimeout(wait, 150);
        }
      })();
    }
  };

  Form.prototype.bind = function () {
    var self = this;
    var city = self.city, address = self.address;

    [city, address].forEach(function (i) {
      i.setAttribute('autocomplete', 'off');
      i.setAttribute('data-ocs-np', '1');
    });
    city.setAttribute('role', 'combobox');
    city.setAttribute('aria-autocomplete', 'list');
    address.setAttribute('role', 'combobox');
    address.setAttribute('aria-haspopup', 'listbox');

    var suggest = debounce(function () { self.openCities(); }, 200);

    city.addEventListener('input', function () {
      if (city.__npSilent) { return; }
      if (self.cityRef || self.whRef) {
        self.cityRef = ''; self.whRef = '';
        self.sync({ city_ref: '' });
      }
      suggest();
    });
    city.addEventListener('focus', function () { if (city.value.trim().length >= 2 && !self.cityRef) { suggest(); } });
    city.addEventListener('keydown', function (e) { self.keys(e, city); });

    address.addEventListener('input', function () {
      if (address.__npSilent) { return; }
      if (self.whRef) {
        self.whRef = '';
        self.sync({ city_ref: self.cityRef });
      }
      if (self.cityRef) { self.openPoints(address.value, true); }
    });
    address.addEventListener('focus', function () { if (self.cityRef && !self.whRef) { self.openPoints(address.value, false); } });
    address.addEventListener('click', function () { if (self.cityRef && !self.whRef && !openPop) { self.openPoints(address.value, false); } });
    address.addEventListener('keydown', function (e) { self.keys(e, address); });

    if (self.country) {
      self.country.addEventListener('change', function () { if (!self.country.__npSilent) { closePop(); } });
    }
  };

  Form.prototype.keys = function (e, input) {
    if (!openPop || openPop.anchor !== input) { return; }
    var list = openPop.node.querySelector('[data-list]');
    if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(list, 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(list, -1); }
    else if (e.key === 'Enter') {
      var a = list.querySelector('.is-active');
      if (a) { e.preventDefault(); a.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })); }
    } else if (e.key === 'Escape' || e.key === 'Tab') { closePop(); }
  };

  Form.prototype.openCities = function () {
    var self = this;
    var q = self.city.value.trim();
    if (!self.active() || q.length < 2) { closePop(); return; }

    if (self.ctl) { self.ctl.abort(); }
    self.ctl = new AbortController();

    get(withParams(cfg.urls.cities, { q: q }), self.ctl.signal).then(function (j) {
      if (document.activeElement !== self.city) { return; }
      var pop = el('div', 'ocs-np-pop');
      var list = el('div', 'ocs-np-list');
      list.setAttribute('role', 'listbox');
      list.setAttribute('data-list', '1');
      (j.items || []).forEach(function (c) {
        var row = el('div', 'ocs-np-item');
        row.setAttribute('role', 'option');
        var title = el('div', 'ocs-np-title');
        highlighted(title, c.name, q);
        row.appendChild(title);
        var sub = [c.type, c.area].filter(Boolean).join(', ');
        if (sub) { row.appendChild(el('div', 'ocs-np-sub', sub)); }
        row.addEventListener('mousedown', function (e) { e.preventDefault(); self.pickCity(c); });
        list.appendChild(row);
      });
      if (!j.items || !j.items.length) { list.appendChild(el('div', 'ocs-np-empty', T.np_no_cities)); }
      pop.appendChild(list);
      showPop(self.city, pop);
    }).catch(function () { /* the field stays a plain input */ });
  };

  Form.prototype.pickCity = function (c) {
    var self = this;
    closePop();
    setValue(self.city, c.name);
    self.cityRef = c.ref;
    self.whRef = '';
    setValue(self.address, '');

    self.sync({ city_ref: c.ref }).then(function (res) {
      fire(self.city, 'input');
      fire(self.city, 'change');
      if (res && res.ok) { self.applyRegion(res); }
      fire(self.address, 'input');
      if (c.points > 0) {
        self.address.focus();
      } else {
        self.address.setAttribute('placeholder', T.np_no_points_city);
      }
    });
  };

  Form.prototype.openPoints = function (query, typed) {
    var self = this;
    if (!self.active() || !self.cityRef) { return; }

    var pop = el('div', 'ocs-np-pop ocs-np-pop--points');
    var head = el('div', 'ocs-np-head');
    var search = el('input', 'ocs-np-search');
    search.type = 'search';
    search.placeholder = T.np_search;
    search.value = typed ? query : '';
    search.setAttribute('aria-label', T.np_search);
    head.appendChild(search);

    var tabs = el('div', 'ocs-np-tabs');
    [['all', T.np_all], ['branch', T.np_branches], ['postomat', T.np_postomats]].forEach(function (t) {
      var b = el('button', 'ocs-np-tab' + (self.type === t[0] ? ' is-on' : ''), t[1]);
      b.type = 'button';
      b.addEventListener('mousedown', function (e) { e.preventDefault(); self.type = t[0]; tabs.querySelectorAll('button').forEach(function (n) { n.classList.toggle('is-on', n === b); }); load(1); });
      tabs.appendChild(b);
    });
    head.appendChild(tabs);

    var list = el('div', 'ocs-np-list');
    list.setAttribute('role', 'listbox');
    list.setAttribute('data-list', '1');
    var more = el('button', 'ocs-np-more', T.np_more);
    more.type = 'button';
    pop.appendChild(head);
    pop.appendChild(list);
    pop.appendChild(more);

    var page = 1, ctl = null;

    function load(p) {
      page = p;
      if (ctl) { ctl.abort(); }
      ctl = new AbortController();
      if (p === 1) { list.textContent = ''; list.appendChild(el('div', 'ocs-np-empty', T.np_loading)); }
      get(withParams(cfg.urls.warehouses, { city_ref: self.cityRef, q: search.value, type: self.type, page: p }), ctl.signal).then(function (j) {
        if (p === 1) { list.textContent = ''; }
        (j.items || []).forEach(function (w) {
          var row = el('div', 'ocs-np-item');
          row.setAttribute('role', 'option');
          row.appendChild(el('div', 'ocs-np-title', w.name));
          var meta = [w.type === 'postomat' ? T.np_postomats : T.np_branches];
          if (w.schedule) { meta.push(T.np_today + ' ' + w.schedule); }
          var kg = weightLabel(T.np_up_to, w.max_weight);
          if (kg) { meta.push(kg); }
          row.appendChild(el('div', 'ocs-np-sub', meta.join(' · ')));
          row.addEventListener('mousedown', function (e) { e.preventDefault(); self.pickPoint(w); });
          list.appendChild(row);
        });
        if (p === 1 && (!j.items || !j.items.length)) { list.appendChild(el('div', 'ocs-np-empty', T.np_no_points)); }
        more.style.display = (j.total || 0) > p * 50 ? '' : 'none';
        if (p === 1) { moveActive(list, 1); }
      }).catch(function () { /* keep what is shown */ });
    }

    search.addEventListener('input', debounce(function () { load(1); }, 200));
    search.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(list, 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(list, -1); }
      else if (e.key === 'Enter') { var a = list.querySelector('.is-active'); if (a) { e.preventDefault(); a.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })); } }
      else if (e.key === 'Escape') { closePop(); self.address.focus(); }
    });
    more.addEventListener('mousedown', function (e) { e.preventDefault(); load(page + 1); });

    showPop(self.address, pop);
    if (typed) { load(1); } else { load(1); search.focus(); }
  };

  Form.prototype.pickPoint = function (w) {
    var self = this;
    closePop();
    self.sync({ city_ref: self.cityRef, warehouse_ref: w.ref }).then(function (res) {
      setValue(self.address, w.name);
      self.whRef = res && res.ok ? w.ref : '';
      fire(self.address, 'input');
      fire(self.address, 'change');
    });
  };

  // ---- start-up and re-attachment ----

  function scan() {
    if (!cfg) { return; }
    cfg.groups.forEach(function (g, idx) {
      var city = qs(g.city), address = qs(g.address);
      if (!city || !address || city.getAttribute('data-ocs-np') === '1') { return; }
      var prev = contexts[idx];
      var form = new Form(g);
      if (prev && prev.cityRef && !city.value) { prev.cityRef = ''; prev.whRef = ''; }
      if (prev) { form.cityRef = prev.cityRef; form.whRef = prev.whRef; }
      contexts[idx] = form;
      form.bind();
    });
  }

  function start(config) {
    cfg = config;
    T = config.strings || {};
    scan();
    var rescan = debounce(scan, 150);
    new MutationObserver(rescan).observe(document.body, { childList: true, subtree: true });
    document.addEventListener('mousedown', function (e) {
      if (openPop && !openPop.node.contains(e.target) && e.target !== openPop.anchor) { closePop(); }
    });
    root.OCS_NP = { rescan: scan };
  }

  function init() {
    var src = script && script.src ? script.src : '';
    var base = src.indexOf('catalog/view/javascript') > -1 ? src.split('catalog/view/javascript')[0] : '';
    if (!base) { return; }
    fetch(base + 'index.php?route=extension/ocs_novaposhta/api/config', { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) { throw new Error('off'); } return r.json(); })
      .then(start)
      .catch(function () { /* module off or unreachable: plain fields */ });
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(this);
