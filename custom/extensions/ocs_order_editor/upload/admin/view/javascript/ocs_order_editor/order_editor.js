/* ocs_order_editor: single-page order editor for the admin order form. Owner: SPEC-122.
 * Talks to the stock catalog API (api/customer, api/cart/*, api/payment/*, api/shipping/*, api/order/*) in the same
 * sequence the stock tabbed form uses, but runs it automatically: every change marks the affected stages dirty and a
 * single serial queue (no overlapping requests, REQ-ORD-022) replays them. */
(function () {
  'use strict';

  var $ = window.jQuery;
  var cfgEl = document.getElementById('oe-config');
  var $root = $('#oe-root');

  if (!$ || !cfgEl || !$root.length) {
    return;
  }

  var cfg;

  try {
    cfg = JSON.parse(cfgEl.textContent);
  } catch (e) {
    return;
  }

  var O = cfg.order;
  var S = cfg.strings || {};
  var ADDR = ['firstname', 'lastname', 'company', 'address_1', 'address_2', 'city', 'postcode', 'country_id', 'zone_id'];

  /* ---------- small helpers ---------- */

  function T(key) {
    var s = S[key] !== undefined ? String(S[key]) : key;
    var args = Array.prototype.slice.call(arguments, 1);

    args.forEach(function (a) {
      s = s.replace('%s', function () { return a; });
    });

    return s;
  }

  function esc(v) {
    return String(v === null || v === undefined ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function plain(html) {
    try {
      return new DOMParser().parseFromString(String(html), 'text/html').body.textContent || '';
    } catch (e) {
      return String(html);
    }
  }

  function toArray(x) {
    if (!x) {
      return [];
    }

    return Array.isArray(x) ? x.slice() : Object.keys(x).map(function (k) { return x[k]; });
  }

  function errText(e) {
    if (!e) {
      return '';
    }

    if (typeof e === 'string') {
      return e;
    }

    if (e.warning) {
      return String(e.warning);
    }

    var first = Object.keys(e).map(function (k) { return e[k]; }).filter(function (v) { return typeof v === 'string'; })[0];

    return first || '';
  }

  /* ---------- state ---------- */

  var state = {
    storeId: O.store_id || 0,
    same: true,
    customerId: O.customer_id || '',
    loggedId: '',
    addresses: toArray(cfg.addresses),
    cart: O.products.map(function (p) { return { product_id: p.product_id, quantity: p.quantity, option: p.option }; }),
    vouchers: O.vouchers.slice(),
    totals: [],
    cartErr: {},
    shipMethods: null,
    payMethods: null,
    shipCode: O.shipping_code || '',
    payCode: O.payment_code || '',
    shipHint: '',
    payHint: '',
    shipLost: false,
    payLost: false,
    needsShipping: undefined,
    addrOk: false,
    affiliateId: O.affiliate_id || '',
    err: { cust: {}, ship: {}, pay: {}, vf: {} },
    warn: {},
    touched: {},
    submitted: false,
    reloadCart: false,
    pending: null,
    saving: false,
    redirecting: false,
    busy: 0,
    changed: false,
    zones: {},
    zoneToken: {}
  };

  var dirty = {};
  var timer = null;
  var chain = Promise.resolve();
  var qtyTimers = {};

  /* ---------- API + serial queue ---------- */

  function api(route, data, query) {
    var url = cfg.catalog + 'index.php?route=' + route + '&api_token=' + encodeURIComponent(cfg.api_token) + '&store_id=' + encodeURIComponent(state.storeId) + (query || '');

    return new Promise(function (resolve, reject) {
      $.ajax({ url: url, type: data === undefined ? 'get' : 'post', data: data, dataType: 'json', crossDomain: true })
        .done(function (j) { resolve(j && typeof j === 'object' ? j : {}); })
        .fail(function (xhr, st, err) { reject(new Error((err || st || 'error') + (xhr && xhr.status ? ' (' + xhr.status + ')' : ''))); });
    });
  }

  function enqueue(fn) {
    var run = chain.then(function () {
      setBusy(1);

      return fn();
    }).catch(function (e) {
      if (window.console) {
        window.console.error(e);
      }

      flash('danger', T('oe_api_fail', e && e.message ? e.message : ''));
    }).then(function () {
      setBusy(-1);
    });

    chain = run;

    return run;
  }

  function scheduleSync(delay) {
    clearTimeout(timer);
    timer = setTimeout(function () {
      timer = null;
      enqueue(syncNow);
    }, delay === undefined ? 500 : delay);
    updateStatus();
  }

  /* ---------- form access ---------- */

  function fld(name) {
    return $root.find('[data-f="' + name + '"]');
  }

  function val(name) {
    var $e = fld(name);

    if (!$e.length) {
      return '';
    }

    var v = $e.first().val();

    return v === null || v === undefined ? '' : String(v);
  }

  function setVal(name, v) {
    fld(name).val(v === null || v === undefined ? '' : v);
  }

  function customFields(loc) {
    return cfg.custom_fields.filter(function (c) { return c.location === loc && c.type !== 'file'; });
  }

  function readCf(scope, c) {
    var $e = fld('cf.' + scope + '.' + c.id);

    if (c.type === 'radio') {
      return $e.filter(':checked').val() || '';
    }

    if (c.type === 'checkbox') {
      return $e.filter(':checked').map(function () { return this.value; }).get();
    }

    return $e.first().val() || '';
  }

  function setCf(scope, id, v) {
    var $e = fld('cf.' + scope + '.' + id);
    var list = Array.isArray(v) ? v.map(String) : [String(v === null || v === undefined ? '' : v)];

    $e.each(function () {
      if (this.type === 'radio' || this.type === 'checkbox') {
        this.checked = list.indexOf(this.value) >= 0;
      } else {
        $(this).val(list[0]);
      }
    });
  }

  function cfData(d, scope, loc) {
    // The catalog API validates custom_field[location][id] while the order stores the flat custom_field[id]; both are sent.
    customFields(loc).forEach(function (c) {
      var v = readCf(scope, c);
      var suffix = c.type === 'checkbox' ? '[]' : '';

      d['custom_field[' + c.id + ']' + suffix] = v;
      d['custom_field[' + loc + '][' + c.id + ']' + suffix] = v;
    });
  }

  function custData() {
    var d = {
      customer_id: state.customerId || '',
      customer_group_id: val('cust.group'),
      firstname: val('cust.firstname'),
      lastname: val('cust.lastname'),
      email: val('cust.email'),
      telephone: val('cust.telephone')
    };

    cfData(d, 'cust', 'account');

    return d;
  }

  function addrData(scope) {
    var d = {};

    ADDR.forEach(function (k) { d[k] = val(scope + '.' + k); });
    cfData(d, scope, 'address');

    return d;
  }

  function optionsData(d, prefix, options) {
    options.forEach(function (o) {
      var id = o.product_option_id;

      if (o.type === 'checkbox') {
        var key = prefix + '[' + id + '][]';

        d[key] = (d[key] || []).concat([o.product_option_value_id]);
      } else if (o.type === 'select' || o.type === 'radio' || o.type === 'image') {
        d[prefix + '[' + id + ']'] = o.product_option_value_id;
      } else {
        d[prefix + '[' + id + ']'] = o.value;
      }
    });
  }

  function addressEmpty(a) {
    return !a.address_1 && !a.city && !a.firstname;
  }

  /* ---------- errors ---------- */

  function mapErr(scope, err, bucket) {
    var target = state.err[bucket || scope];

    if (!err) {
      return;
    }

    if (typeof err === 'string') {
      state.warn[scope] = err;

      return;
    }

    Object.keys(err).forEach(function (key) {
      var v = err[key];
      var m = /^custom_field(\d+)$/.exec(key);
      var name;

      if (typeof v !== 'string') {
        return;
      }

      if (key === 'warning') {
        state.warn[scope] = v;

        return;
      }

      if (m) {
        name = 'cf.' + scope + '.' + m[1];
      } else if (scope === 'vf') {
        name = 'vf.' + key;
      } else if (key === 'country') {
        name = scope + '.country_id';
      } else if (key === 'zone') {
        name = scope + '.zone_id';
      } else {
        name = scope + '.' + key;
      }

      target[name] = v;
    });
  }

  function allErrors() {
    var all = {};

    Object.keys(state.err).forEach(function (s) { $.extend(all, state.err[s]); });

    return all;
  }

  function renderErrors() {
    var all = allErrors();

    $root.find('.oe-err').remove();
    $root.find('.has-error').removeClass('has-error');

    Object.keys(all).forEach(function (name) {
      var $el = fld(name);

      if (!$el.length || !(state.submitted || state.touched[name])) {
        return;
      }

      var $g = $el.first().closest('.oe-g');

      $g.addClass('has-error');
      $('<div class="text-danger oe-err"></div>').text(all[name]).appendTo($g.find('.oe-fc').first());
    });

    renderWarnings();
  }

  function renderWarnings() {
    var map = { cust: '#oe-w-cust', ship: '#oe-w-ship', pay: '#oe-w-pay' };

    Object.keys(map).forEach(function (k) {
      var $w = $(map[k]);
      var t = state.warn[k] || '';

      $w.html(t ? '<div class="alert alert-danger oe-note">' + esc(t) + '</div>' : '');
    });

    var notes = '';
    var ce = state.cartErr || {};

    if (ce.warning) {
      notes += '<div class="alert alert-danger oe-note">' + esc(ce.warning) + '</div>';
    }

    if (ce.stock) {
      notes += '<div class="alert alert-warning oe-note">' + esc(ce.stock) + '</div>';
    }

    toArray(ce.minimum).forEach(function (m) {
      notes += '<div class="alert alert-warning oe-note">' + esc(m) + '</div>';
    });

    $('#oe-cart-notes').html(notes);
  }

  /* ---------- flash alerts ---------- */

  function flash(type, msg, noScroll) {
    var $box = $('#oe-alerts');

    $box.find('.oe-flash').remove();
    $('<div class="alert alert-' + type + ' alert-dismissible oe-flash"><i class="fa fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> <span></span> <button type="button" class="close" data-dismiss="alert">&times;</button></div>')
      .find('span').text(msg).end().appendTo($box);

    if (!noScroll) {
      $('html, body').animate({ scrollTop: 0 }, 150);
    }
  }

  /* ---------- markup ---------- */

  function col(n, html) {
    return '<div class="col-sm-' + n + '">' + html + '</div>';
  }

  function group(name, label, control, opt) {
    opt = opt || {};

    return '<div class="form-group oe-g' + (opt.req ? ' required' : '') + '" data-g="' + esc(name) + '"><label class="control-label" for="oe-f-' + esc(name) + '">' + esc(label) + '</label><div class="oe-fc">' + control + '</div></div>';
  }

  function inp(name, extra) {
    return '<input type="text" class="form-control" id="oe-f-' + esc(name) + '" data-f="' + esc(name) + '" ' + (extra || '') + ' />';
  }

  function select(name, optionsHtml, extra) {
    return '<select class="form-control" id="oe-f-' + esc(name) + '" data-f="' + esc(name) + '" ' + (extra || '') + '>' + optionsHtml + '</select>';
  }

  function opts(list, valueKey, labelKey, selected) {
    return list.map(function (x) {
      return '<option value="' + esc(x[valueKey]) + '"' + (String(x[valueKey]) === String(selected) ? ' selected="selected"' : '') + '>' + esc(x[labelKey]) + '</option>';
    }).join('');
  }

  function cfHtml(scope, loc) {
    return customFields(loc).map(function (c) {
      var n = 'cf.' + scope + '.' + c.id;
      var ctl;

      if (c.type === 'select') {
        ctl = select(n, '<option value="">' + esc(T('oe_select')) + '</option>' + c.values.map(function (x) { return '<option value="' + x.id + '">' + esc(x.name) + '</option>'; }).join(''));
      } else if (c.type === 'radio' || c.type === 'checkbox') {
        ctl = c.values.map(function (x) {
          return '<div class="' + c.type + '"><label><input type="' + c.type + '" name="' + esc(n) + '" data-f="' + esc(n) + '" value="' + x.id + '" /> ' + esc(x.name) + '</label></div>';
        }).join('');
      } else if (c.type === 'textarea') {
        ctl = '<textarea rows="3" class="form-control" id="oe-f-' + esc(n) + '" data-f="' + esc(n) + '"></textarea>';
      } else {
        ctl = inp(n, 'value="' + esc(c.value) + '"' + (c.type === 'date' ? ' placeholder="YYYY-MM-DD"' : c.type === 'time' ? ' placeholder="HH:MM"' : c.type === 'datetime' ? ' placeholder="YYYY-MM-DD HH:MM"' : ''));
      }

      return col(6, group(n, c.name, ctl, { req: c.required }));
    }).join('');
  }

  function addressFields(scope) {
    var h = '<div class="row">';

    h += col(6, group(scope + '.firstname', T('oe_firstname'), inp(scope + '.firstname', 'autocomplete="off"'), { req: 1 }));
    h += col(6, group(scope + '.lastname', T('oe_lastname'), inp(scope + '.lastname', 'autocomplete="off"'), { req: 1 }));
    h += col(6, group(scope + '.country_id', T('oe_country'), select(scope + '.country_id', '<option value="">' + esc(T('oe_select')) + '</option>' + opts(cfg.countries, 'id', 'name', '')), { req: 1 }));
    h += col(6, group(scope + '.zone_id', T('oe_zone'), select(scope + '.zone_id', '<option value="">' + esc(T('oe_select')) + '</option>'), { req: 1 }));
    h += col(6, group(scope + '.city', T('oe_city'), inp(scope + '.city', 'autocomplete="off"'), { req: 1 }));
    h += col(6, group(scope + '.postcode', T('oe_postcode'), inp(scope + '.postcode', 'autocomplete="off"')));
    h += col(8, group(scope + '.address_1', T('oe_address_1'), inp(scope + '.address_1', 'autocomplete="off"'), { req: 1 }));
    h += col(4, group(scope + '.address_2', T('oe_address_2'), inp(scope + '.address_2', 'autocomplete="off"')));
    h += col(12, group(scope + '.company', T('oe_company'), inp(scope + '.company', 'autocomplete="off"')));
    h += cfHtml(scope, 'address');

    return h + '</div>';
  }

  function card(id, title, body) {
    return '<div class="panel panel-default oe-card" id="' + id + '"><div class="panel-heading"><h3 class="panel-title">' + esc(title) + '</h3></div><div class="panel-body">' + body + '</div></div>';
  }

  function giftForm() {
    var h = '<div class="row">';

    h += col(6, group('vf.to_name', T('oe_gift_to_name'), inp('vf.to_name'), { req: 1 }));
    h += col(6, group('vf.to_email', T('oe_gift_to_email'), inp('vf.to_email'), { req: 1 }));
    h += col(6, group('vf.from_name', T('oe_gift_from_name'), inp('vf.from_name'), { req: 1 }));
    h += col(6, group('vf.from_email', T('oe_gift_from_email'), inp('vf.from_email'), { req: 1 }));
    h += col(6, group('vf.theme', T('oe_gift_theme'), select('vf.theme', opts(cfg.voucher_themes, 'id', 'name', ''))));
    h += col(6, group('vf.amount', T('oe_gift_amount'), inp('vf.amount', 'value="' + esc(cfg.voucher_min) + '"'), { req: 1 }));
    h += col(12, group('vf.message', T('oe_gift_message'), '<textarea rows="2" class="form-control" id="oe-f-vf.message" data-f="vf.message"></textarea>'));

    return h + '</div><button type="button" class="btn btn-default" id="oe-gift-add"><i class="fa fa-plus-circle"></i> ' + esc(T('oe_gift_add_btn')) + '</button>';
  }

  function shell() {
    var multiStore = cfg.stores.length > 1;
    var multiCur = cfg.currencies.length > 1;
    var multiGroup = cfg.customer_groups.length > 1;
    var h = '<div class="row"><div class="col-lg-8 oe-main">';

    // 1. Customer
    var c = '<div class="oe-search form-group"><label class="control-label" for="oe-f-cust.search">' + esc(T('oe_find_customer')) + '</label>'
      + '<input type="text" class="form-control" id="oe-f-cust.search" data-f="cust.search" placeholder="' + esc(T('oe_find_customer_ph')) + '" autocomplete="off" />'
      + '<div id="oe-cust-chip" class="oe-chip"></div></div><div class="row">';

    c += col(6, group('cust.telephone', T('oe_telephone'), inp('cust.telephone', 'autocomplete="off"'), { req: 1 }));
    c += col(6, group('cust.email', T('oe_email'), inp('cust.email', 'autocomplete="off"'), { req: 1 }));
    c += col(6, group('cust.firstname', T('oe_firstname'), inp('cust.firstname', 'autocomplete="off"'), { req: 1 }));
    c += col(6, group('cust.lastname', T('oe_lastname'), inp('cust.lastname', 'autocomplete="off"'), { req: 1 }));
    c += '<div class="' + (multiGroup ? '' : 'hidden') + '">' + col(6, group('cust.group', T('oe_group'), select('cust.group', opts(cfg.customer_groups, 'id', 'name', O.group_id)))) + '</div>';
    c += '<div class="' + (multiStore ? '' : 'hidden') + '">' + col(3, group('store', T('oe_store'), select('store', opts(cfg.stores, 'id', 'name', O.store_id)))) + '</div>';
    c += '<div class="' + (multiCur ? '' : 'hidden') + '">' + col(3, group('cur', T('oe_currency'), select('cur', opts(cfg.currencies, 'code', 'title', O.currency)))) + '</div>';
    c += cfHtml('cust', 'account');
    c += '</div><div id="oe-w-cust"></div>';
    h += card('oe-card-customer', '1. ' + T('oe_s_customer'), c);

    // 2. Products
    var p = '<div class="form-group"><label class="control-label" for="oe-f-p.search">' + esc(T('oe_product_search')) + '</label>'
      + '<input type="text" class="form-control" id="oe-f-p.search" data-f="p.search" placeholder="' + esc(T('oe_product_search_ph')) + '" autocomplete="off" /></div>'
      + '<div id="oe-pending"></div>'
      + '<div class="table-responsive"><table class="table oe-cart"><thead><tr><th>' + esc(T('oe_col_product')) + '</th><th class="text-right">' + esc(T('oe_col_price')) + '</th><th class="text-center">' + esc(T('oe_col_qty')) + '</th><th class="text-right">' + esc(T('oe_col_total')) + '</th><th></th></tr></thead><tbody id="oe-cart"></tbody></table></div>'
      + '<div id="oe-cart-notes"></div>'
      + '<p><a href="#" id="oe-gift-toggle"><i class="fa fa-gift"></i> ' + esc(T('oe_gift_add')) + '</a></p><div id="oe-gift" class="oe-gift" style="display:none">' + giftForm() + '</div>';
    h += card('oe-card-products', '2. ' + T('oe_s_products'), p);

    // 3. Delivery
    var d = '<div class="form-group oe-book" id="oe-book-ship" style="display:none"><label class="control-label">' + esc(T('oe_address_book')) + '</label><select class="form-control" data-f="ship.book"></select></div>'
      + addressFields('ship')
      + '<div class="checkbox"><label><input type="checkbox" id="oe-same" checked="checked" /> ' + esc(T('oe_same_address')) + '</label></div>'
      + '<div id="oe-w-ship"></div>'
      + '<div id="oe-ship-block"><h4 class="oe-sub">' + esc(T('oe_shipping_method')) + '</h4><div id="oe-ship-methods"></div></div>';
    h += card('oe-card-delivery', '3. ' + T('oe_s_delivery'), d);

    // Payer (only when different from the delivery address)
    var py = '<div class="form-group oe-book" id="oe-book-pay" style="display:none"><label class="control-label">' + esc(T('oe_address_book')) + '</label><select class="form-control" data-f="pay.book"></select></div>'
      + addressFields('pay') + '<div id="oe-w-pay"></div>';
    h += '<div id="oe-payer-wrap" style="display:none">' + card('oe-card-payer', T('oe_s_payer'), py) + '</div>';

    // 4. Payment
    h += card('oe-card-payment', '4. ' + T('oe_s_payment'), '<div id="oe-pay-methods"></div>');

    // 5. Extra
    var x = '<div class="row">' + col(6, group('x.status', T('oe_order_status'), select('x.status', opts(cfg.order_statuses, 'id', 'name', O.status_id))))
      + col(6, group('x.affiliate', T('oe_affiliate'), inp('x.affiliate', 'value="' + esc(O.affiliate) + '" autocomplete="off"')))
      + col(12, group('x.comment', T('oe_comment'), '<textarea rows="3" class="form-control" id="oe-f-x.comment" data-f="x.comment">' + esc(O.comment) + '</textarea>')) + '</div>';
    h += card('oe-card-extra', '5. ' + T('oe_s_extra'), x);

    h += '</div><div class="col-lg-4"><div class="oe-aside">';

    // Summary
    var s = '<div id="oe-totals"></div>'
      + '<div class="oe-promo">' + group('x.coupon', T('oe_coupon'), inp('x.coupon', 'autocomplete="off"')) + group('x.gift', T('oe_gift_code'), inp('x.gift', 'autocomplete="off"')) + group('x.reward', T('oe_reward'), inp('x.reward', 'autocomplete="off"')) + '</div>'
      + '<div id="oe-checks"></div>'
      + '<button type="button" class="btn btn-primary btn-lg btn-block oe-save"><i class="fa fa-check-circle"></i> <span class="oe-save-text">' + esc(T('oe_save')) + '</span></button>'
      + '<div class="oe-status text-muted" id="oe-status"></div>'
      + '<p class="text-center"><a href="#" id="oe-recalc"><i class="fa fa-refresh"></i> ' + esc(T('oe_recalc')) + '</a></p>';
    h += card('oe-card-summary', T('oe_s_summary'), s);

    h += '</div></div></div>'
      + '<div class="oe-mbar"><div class="oe-mbar-total" id="oe-mbar-total"></div><button type="button" class="btn btn-primary oe-save"><i class="fa fa-check-circle"></i> <span class="oe-save-text">' + esc(T('oe_save')) + '</span></button></div>';

    return h;
  }

  /* ---------- dynamic renderers ---------- */

  function optLines(options) {
    return toArray(options).map(function (o) {
      return '<div class="oe-opt-line">' + esc(o.name) + ': ' + esc(o.value) + '</div>';
    }).join('');
  }

  function renderCart() {
    var $a = $(document.activeElement);
    var focusKey = $a.is('.oe-qinput') && $.contains($('#oe-cart')[0], $a[0]) ? $a.closest('tr').attr('data-key') : null;
    var caret = focusKey !== null ? $a[0].selectionStart : 0;
    var h = '';

    state.cart.forEach(function (p) {
      if (!p.cart_id) {
        return;
      }

      h += '<tr data-key="' + esc(p.cart_id) + '"><td class="oe-pname">' + esc(p.name) + (p.stock === false ? ' <span class="label label-danger">' + esc(T('oe_out_of_stock')) + '</span>' : '')
        + '<div class="text-muted small">' + esc(p.model) + '</div>' + optLines(p.option) + '</td>'
        + '<td class="text-right oe-nowrap" data-label="' + esc(T('oe_col_price')) + '">' + esc(p.price) + '</td>'
        + '<td class="text-center"><div class="oe-qty"><button type="button" class="oe-qminus" aria-label="-">&minus;</button><input type="text" class="oe-qinput" inputmode="numeric" value="' + esc(p.quantity) + '" /><button type="button" class="oe-qplus" aria-label="+">+</button></div></td>'
        + '<td class="text-right oe-nowrap"><strong>' + esc(p.total) + '</strong></td>'
        + '<td class="text-right"><button type="button" class="btn btn-link oe-rm" title="' + esc(T('oe_remove')) + '"><i class="fa fa-trash-o"></i></button></td></tr>';
    });

    state.vouchers.forEach(function (v) {
      h += '<tr data-vkey="' + esc(v.code) + '"><td class="oe-pname"><i class="fa fa-gift"></i> ' + esc(v.description) + '</td><td class="text-right oe-nowrap">' + esc(v.price || '') + '</td><td class="text-center">1</td><td class="text-right oe-nowrap"><strong>' + esc(v.price || '') + '</strong></td>'
        + '<td class="text-right"><button type="button" class="btn btn-link oe-rm" title="' + esc(T('oe_remove')) + '"><i class="fa fa-trash-o"></i></button></td></tr>';
    });

    if (!h) {
      h = '<tr><td colspan="5" class="text-center text-muted oe-empty">' + esc(T('oe_no_products')) + '</td></tr>';
    }

    $('#oe-cart').html(h);

    if (focusKey !== null) {
      var $i = $('#oe-cart tr').filter(function () { return $(this).attr('data-key') === focusKey; }).find('.oe-qinput');

      if ($i.length) {
        $i.focus();
        try { $i[0].setSelectionRange(caret, caret); } catch (e) { /* not critical */ }
      }
    }
  }

  function totalText() {
    var t = state.totals.length ? state.totals[state.totals.length - 1] : null;

    return t ? t.text : '';
  }

  function renderTotals() {
    var h = '';

    state.totals.forEach(function (t, i) {
      h += '<tr' + (i === state.totals.length - 1 ? ' class="oe-grand"' : '') + '><td>' + esc(t.title) + '</td><td class="text-right oe-nowrap">' + esc(t.text) + '</td></tr>';
    });

    $('#oe-totals').html(h ? '<table class="table oe-totals"><tbody>' + h + '</tbody></table>' : '<p class="text-muted text-center">' + esc(T('oe_totals_empty')) + '</p>');
    $('#oe-mbar-total').text(totalText());
  }

  function shipQuotes() {
    var list = [];

    Object.keys(state.shipMethods || {}).forEach(function (k) {
      var m = state.shipMethods[k];

      Object.keys(m.quote || {}).forEach(function (q) {
        list.push({ code: m.quote[q].code, title: m.quote[q].title, text: m.quote[q].text, group: m.title, error: m.error });
      });
    });

    return list;
  }

  function radioCard(name, value, title, price, checked) {
    return '<label class="oe-opt' + (checked ? ' is-checked' : '') + '"><input type="radio" name="' + name + '" value="' + esc(value) + '"' + (checked ? ' checked="checked"' : '') + ' /> <span class="oe-opt-title">' + esc(plain(title)) + '</span>' + (price ? '<span class="oe-opt-price">' + esc(plain(price)) + '</span>' : '') + '</label>';
  }

  function renderMethods() {
    var h = '';

    $('#oe-ship-block').toggle(state.needsShipping !== false);

    if (state.shipMethods === null) {
      h = '<p class="text-muted">' + esc(T('oe_need_address')) + '</p>';
    } else {
      shipQuotes().forEach(function (q) {
        h += radioCard('oe-ship', q.code, q.group && q.group !== q.title ? q.group + ' — ' + q.title : q.title, q.text, q.code === state.shipCode);
      });

      Object.keys(state.shipMethods).forEach(function (k) {
        if (state.shipMethods[k].error) {
          h += '<p class="text-danger">' + esc(plain(state.shipMethods[k].title)) + ': ' + esc(state.shipMethods[k].error) + '</p>';
        }
      });

      if (!h) {
        h = '<p class="text-danger">' + esc(state.shipHint || T('oe_no_methods')) + '</p>';
      }

      if (state.shipLost) {
        h = '<p class="text-warning">' + esc(T('oe_method_lost')) + '</p>' + h;
      }
    }

    $('#oe-ship-methods').html(h);

    h = '';

    if (state.payMethods === null) {
      h = '<p class="text-muted">' + esc(T('oe_need_address')) + '</p>';
    } else {
      Object.keys(state.payMethods).forEach(function (k) {
        var m = state.payMethods[k];

        h += radioCard('oe-pay', m.code || k, m.title, '', (m.code || k) === state.payCode);
      });

      if (!h) {
        h = '<p class="text-danger">' + esc(state.payHint || T('oe_no_methods')) + '</p>';
      }

      if (state.payLost) {
        h = '<p class="text-warning">' + esc(T('oe_method_lost')) + '</p>' + h;
      }
    }

    $('#oe-pay-methods').html(h);
  }

  function renderBooks() {
    ['ship', 'pay'].forEach(function (scope) {
      var $w = $('#oe-book-' + scope);
      var h = '<option value="0">' + esc(T('oe_address_new')) + '</option>';

      state.addresses.forEach(function (a) {
        h += '<option value="' + esc(a.address_id) + '">' + esc([a.firstname + ' ' + a.lastname, a.address_1, a.city, a.country].filter(Boolean).join(', ')) + '</option>';
      });

      fld(scope + '.book').html(h);
      $w.toggle(state.addresses.length > 0);
    });
  }

  function renderChip() {
    var name = $.trim(val('cust.firstname') + ' ' + val('cust.lastname'));

    $('#oe-cust-chip').html(state.customerId ? '<span class="label label-info">' + esc(T('oe_customer_linked', '#' + state.customerId + (name ? ' ' + name : ''))) + '</span> <a href="#" class="oe-detach">' + esc(T('oe_detach')) + '</a>' : '<span class="text-muted small">' + esc(T('oe_guest_hint')) + '</span>');
  }

  function updateStatus() {
    var busy = state.busy > 0 || timer !== null;

    $('#oe-status').text(state.saving ? T('oe_saving') : busy ? T('oe_recalculating') : T('oe_synced'));
    $('.oe-save').prop('disabled', state.saving || state.busy > 0);
    $('.oe-save-text').text(state.saving ? T('oe_saving') : T('oe_save'));
    $root.toggleClass('is-busy', busy);
  }

  function setBusy(delta) {
    state.busy = Math.max(0, state.busy + delta);
    updateStatus();
  }

  /* ---------- zones and addresses ---------- */

  function getCountry(id) {
    id = String(id || '');

    if (!id) {
      return Promise.resolve({ zone: [], postcode_required: '0' });
    }

    if (!state.zones[id]) {
      state.zones[id] = new Promise(function (resolve) {
        $.ajax({ url: 'index.php?route=localisation/country/country&user_token=' + encodeURIComponent(cfg.user_token) + '&country_id=' + encodeURIComponent(id), dataType: 'json' })
          .done(function (j) { resolve(j && j.zone ? j : { zone: [], postcode_required: '0' }); })
          .fail(function () { delete state.zones[id]; resolve({ zone: [], postcode_required: '0' }); });
      });
    }

    return state.zones[id];
  }

  function loadZones(scope, countryId, zoneId) {
    var token = state.zoneToken[scope] = (state.zoneToken[scope] || 0) + 1;

    return getCountry(countryId).then(function (c) {
      if (token !== state.zoneToken[scope]) {
        return;
      }

      var $z = fld(scope + '.zone_id');
      var h = '<option value="">' + esc(T('oe_select')) + '</option>';

      toArray(c.zone).forEach(function (z) { h += '<option value="' + esc(z.zone_id) + '">' + esc(z.name) + '</option>'; });
      $z.html(h);

      if (zoneId) {
        $z.find('option').each(function () {
          if (this.value === String(zoneId)) {
            $z.val(this.value);
          }
        });
      }

      fld(scope + '.postcode').closest('.oe-g').toggleClass('required', String(c.postcode_required) === '1');
    });
  }

  function fillAddress(scope, a) {
    ADDR.forEach(function (k) {
      if (k !== 'zone_id' && k !== 'country_id') {
        setVal(scope + '.' + k, a[k]);
      }
    });

    setVal(scope + '.country_id', a.country_id);
    customFields('address').forEach(function (c) { setCf(scope, c.id, (a.custom_field || {})[c.id] !== undefined ? a.custom_field[c.id] : (c.type === 'checkbox' ? [] : '')); });

    if (scope === 'ship') {
      fld('ship.firstname').attr('data-auto', '0');
      fld('ship.lastname').attr('data-auto', '0');
    }

    return loadZones(scope, a.country_id, a.zone_id).then(function () { syncBook(scope); });
  }

  // the saved-address select shows the entry that matches what is typed, or "new address"
  function syncBook(scope) {
    var match = state.addresses.filter(function (a) {
      return ['firstname', 'lastname', 'address_1', 'city', 'country_id', 'zone_id'].every(function (k) { return String(a[k]) === val(scope + '.' + k); });
    })[0];

    fld(scope + '.book').val(match ? String(match.address_id) : '0');
  }

  function copyShipToPay() {
    var a = {};

    ADDR.forEach(function (k) { a[k] = val('ship.' + k); });
    a.custom_field = {};
    customFields('address').forEach(function (c) { a.custom_field[c.id] = readCf('ship', c); });

    return fillAddress('pay', a);
  }

  function setSame(same) {
    state.same = same;
    $('#oe-same').prop('checked', same);
    $('#oe-payer-wrap').toggle(!same);

    if (!same) {
      return copyShipToPay();
    }

    state.err.pay = {};
    state.warn.pay = '';

    return Promise.resolve();
  }

  /* ---------- sync stages ---------- */

  async function stageCurrency() {
    var j = await api('api/currency', { currency: val('cur') });

    state.warn.cur = j.error ? errText(j.error) : '';
  }

  async function stageCustomer() {
    var j = await api('api/customer', custData());
    var id = String(state.customerId || '');

    state.err.cust = {};
    state.warn.cust = '';

    // the API logs the customer in before it validates; the cart belongs to that login, so it is rebuilt once per login change
    if (id && id !== state.loggedId) {
      state.loggedId = id;
      state.reloadCart = true;
    }

    if (j.error) {
      mapErr('cust', j.error);
    }
  }

  async function stageCartReload() {
    state.reloadCart = false;

    var d = {};
    var items = state.cart.filter(function (p) { return p.product_id; });

    if (items.length) {
      items.forEach(function (p, i) {
        d['product[' + i + '][product_id]'] = p.product_id;
        d['product[' + i + '][quantity]'] = p.quantity;
        optionsData(d, 'product[' + i + '][option]', toArray(p.option));
      });

      var j = await api('api/cart/add', d);

      if (j.error) {
        state.cartErr = { warning: errText(j.error) };
      }
    }

    if (state.vouchers.length) {
      var v = {};

      state.vouchers.forEach(function (x, i) {
        ['code', 'description', 'from_name', 'from_email', 'to_name', 'to_email', 'voucher_theme_id', 'message', 'amount'].forEach(function (k) { v['voucher[' + i + '][' + k + ']'] = x[k]; });
      });

      await api('api/voucher/add', v);
    }

    return true;
  }

  async function stageAddress() {
    var ship = addrData('ship');
    var pay = state.same ? ship : addrData('pay');
    var jp = await api('api/payment/address', pay);
    var js = { };

    state.err.ship = {};
    state.err.pay = {};
    state.warn.ship = '';
    state.warn.pay = '';

    if (jp.error) {
      mapErr(state.same ? 'ship' : 'pay', jp.error);
    }

    if (state.needsShipping !== false || state.same) {
      js = await api('api/shipping/address', ship);

      if (js.error) {
        mapErr('ship', js.error);
      }
    }

    state.addrOk = !jp.error && !js.error;
  }

  async function stageExtras(d) {
    if (d.coup) {
      var c = $.trim(val('x.coupon'));
      var jc = await api('api/coupon', { coupon: c });

      state.warn.coup = c && jc.error ? errText(jc.error) : '';
    }

    if (d.gift) {
      var g = $.trim(val('x.gift'));
      var jg = await api('api/voucher', { voucher: g });

      state.warn.gift = g && jg.error ? errText(jg.error) : '';
    }

    if (d.rew) {
      var r = $.trim(val('x.reward'));
      var jr = await api('api/reward', { reward: r });

      state.warn.rew = r && jr.error ? errText(jr.error) : '';
    }

    ['coup', 'gift', 'rew'].forEach(function (k) {
      var field = { coup: 'x.coupon', gift: 'x.gift', rew: 'x.reward' }[k];
      var $g = fld(field).closest('.oe-g');

      $g.toggleClass('has-error', !!state.warn[k]);
      $g.find('.oe-err').remove();

      if (state.warn[k]) {
        $('<div class="text-danger oe-err"></div>').text(state.warn[k]).appendTo($g.find('.oe-fc').first());
      }
    });
  }

  async function stageMethods(doShip, doPay) {
    if (!state.addrOk) {
      if (doShip) {
        state.shipMethods = null;
      }

      if (doPay) {
        state.payMethods = null;
      }

      renderMethods();

      return;
    }

    if (doShip) {
      var js = await api('api/shipping/methods');

      state.shipHint = '';
      state.shipLost = false;

      if (js.error) {
        state.shipMethods = {};
        state.shipHint = errText(js.error);
        state.needsShipping = true;
      } else {
        state.shipMethods = js.shipping_methods && !Array.isArray(js.shipping_methods) ? js.shipping_methods : {};
        state.needsShipping = Object.keys(state.shipMethods).length > 0;
      }

      var codes = shipQuotes().map(function (q) { return q.code; });

      if (state.shipCode && codes.indexOf(state.shipCode) < 0) {
        state.shipLost = true;
        state.shipCode = '';
      }

      if (!state.shipCode && codes.length === 1) {
        state.shipCode = codes[0];
      }

      if (state.shipCode) {
        await api('api/shipping/method', { shipping_method: state.shipCode });
      }
    }

    if (doPay) {
      var jp = await api('api/payment/methods');

      state.payHint = '';
      state.payLost = false;

      if (jp.error) {
        state.payMethods = {};
        state.payHint = errText(jp.error);
      } else {
        state.payMethods = jp.payment_methods && !Array.isArray(jp.payment_methods) ? jp.payment_methods : {};
      }

      var pcodes = Object.keys(state.payMethods).map(function (k) { return state.payMethods[k].code || k; });

      if (state.payCode && pcodes.indexOf(state.payCode) < 0) {
        state.payLost = true;
        state.payCode = '';
      }

      if (!state.payCode && pcodes.length === 1) {
        state.payCode = pcodes[0];
      }

      if (state.payCode) {
        await api('api/payment/method', { payment_method: state.payCode });
      }
    }

    renderMethods();
  }

  async function stageTotals() {
    var j = await api('api/cart/products');

    state.cartErr = j.error && typeof j.error === 'object' ? j.error : {};

    if (!j.products) {
      renderWarnings();
      flash('danger', T('oe_api_fail', state.cartErr.warning || ''), true);

      return;
    }

    state.cart = j.products;
    state.vouchers = j.vouchers || [];
    state.totals = j.totals || [];

    if (state.cart.length && state.needsShipping === undefined) {
      state.needsShipping = state.cart.some(function (p) { return Number(p.shipping) !== 0; });
    }

    renderCart();
    renderTotals();
    renderErrors();
    renderChecks();
  }

  async function syncNow() {
    var d = dirty;
    var cartTouched = false;

    dirty = {};

    if (!Object.keys(d).length) {
      return;
    }

    if (d.cur) {
      await stageCurrency();
    }

    if (d.cust) {
      await stageCustomer();
    }

    if (state.reloadCart) {
      await stageCartReload();
      cartTouched = true;
    }

    if (d.addr) {
      await stageAddress();
    }

    if (d.cur || d.cust || d.cart || d.addr || cartTouched) {
      d.ship = true;
      d.pay = true;
    }

    if (d.coup || d.gift || d.rew || d.cart || cartTouched) {
      if ($.trim(val('x.reward'))) {
        d.rew = true;
      }

      d.pay = true;
    }

    if (d.coup || d.gift || d.rew) {
      await stageExtras(d);
    }

    if (d.selShip && !d.ship && state.shipCode) {
      await api('api/shipping/method', { shipping_method: state.shipCode });
    }

    if (d.selPay && !d.pay && state.payCode) {
      await api('api/payment/method', { payment_method: state.payCode });
    }

    if (d.ship || d.pay) {
      await stageMethods(!!d.ship, !!d.pay);
    }

    await stageTotals();
    renderChips();
  }

  function renderChips() {
    renderChip();
  }

  function markAll() {
    dirty.cur = true;
    dirty.cust = true;
    dirty.addr = true;
    dirty.ship = true;
    dirty.pay = true;

    if ($.trim(val('x.coupon'))) {
      dirty.coup = true;
    }

    if ($.trim(val('x.gift'))) {
      dirty.gift = true;
    }

    if ($.trim(val('x.reward'))) {
      dirty.rew = true;
    }
  }

  /* ---------- checks and save ---------- */

  function problems() {
    var list = [];
    var all = $.extend({}, state.err.cust, state.err.ship, state.err.pay);

    if (Object.keys(all).length || state.warn.cust || state.warn.ship || state.warn.pay) {
      list.push(T('oe_save_errors'));
    }

    if (!state.cart.length && !state.vouchers.length) {
      list.push(T('oe_err_products'));
    }

    if (state.needsShipping !== false && !state.shipCode && state.addrOk) {
      list.push(T('oe_err_shipping'));
    }

    if (!state.payCode && state.addrOk) {
      list.push(T('oe_err_payment'));
    }

    if (!state.addrOk && !Object.keys(all).length) {
      list.push(T('oe_need_address'));
    }

    return list;
  }

  function renderChecks() {
    var list = [];

    if (state.submitted) {
      list = problems();
    }

    $('#oe-checks').html(list.length ? '<div class="alert alert-danger oe-note"><ul>' + list.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul></div>' : '');
  }

  function scrollToFirstError() {
    var $e = $root.find('.has-error, .oe-note.alert-danger, .text-danger').filter(':visible').first();

    if ($e.length) {
      $('html, body').animate({ scrollTop: Math.max(0, $e.offset().top - 90) }, 200);
    }
  }

  async function save() {
    if (state.saving) {
      return;
    }

    state.submitted = true;
    state.saving = true;
    updateStatus();
    $('#oe-alerts .oe-flash').remove();

    try {
      clearTimeout(timer);
      timer = null;
      markAll();
      await enqueue(syncNow);
      renderErrors();
      renderChecks();

      var list = problems();

      if (list.length) {
        flash('danger', list[0], true);
        scrollToFirstError();

        return;
      }

      var d = { order_status_id: val('x.status'), comment: val('x.comment'), affiliate_id: state.affiliateId || '' };
      var edit = O.order_id > 0;
      var j = await api(edit ? 'api/order/edit' : 'api/order/add', d, edit ? '&order_id=' + encodeURIComponent(O.order_id) : '');

      if (j.error) {
        flash('danger', errText(j.error));

        return;
      }

      if (j.success) {
        var id = edit ? O.order_id : j.order_id;

        state.changed = false;
        flash('success', T(edit ? 'oe_saved_edit' : 'oe_saved_new', id));

        if (!edit && j.order_id) {
          O.order_id = parseInt(j.order_id, 10);
          O.status_id = parseInt(val('x.status'), 10);
        }

        state.redirecting = true;
        setTimeout(function () { window.location.href = cfg.info_url + encodeURIComponent(id); }, 600);

        return;
      }

      flash('danger', T('oe_api_fail', ''));
    } catch (e) {
      flash('danger', T('oe_api_fail', e && e.message ? e.message : ''));
    } finally {
      state.saving = state.redirecting;
      updateStatus();
    }
  }

  /* ---------- products ---------- */

  function renderPending() {
    var p = state.pending;

    if (!p) {
      $('#oe-pending').html('');

      return;
    }

    var h = '<div class="oe-pending"><div class="oe-pending-title"><strong>' + esc(p.name) + '</strong> <span class="text-muted">' + esc(p.model) + '</span></div><div class="row">';
    var blocked = false;

    p.options.forEach(function (o) {
      var id = o.product_option_id;
      var name = 'po.' + id;
      var ctl = '';
      var values = toArray(o.product_option_value);

      if (o.type === 'select' || o.type === 'radio' || o.type === 'image') {
        ctl = '<select class="form-control" data-po="' + id + '" data-type="' + esc(o.type) + '"><option value="">' + esc(T('oe_select')) + '</option>' + values.map(function (v) {
          return '<option value="' + esc(v.product_option_value_id) + '">' + esc(v.name) + (v.price ? ' (' + esc(v.price_prefix) + esc(v.price) + ')' : '') + '</option>';
        }).join('') + '</select>';
      } else if (o.type === 'checkbox') {
        ctl = values.map(function (v) {
          return '<div class="checkbox"><label><input type="checkbox" data-po="' + id + '" data-type="checkbox" value="' + esc(v.product_option_value_id) + '" /> ' + esc(v.name) + (v.price ? ' (' + esc(v.price_prefix) + esc(v.price) + ')' : '') + '</label></div>';
        }).join('');
      } else if (o.type === 'textarea') {
        ctl = '<textarea rows="2" class="form-control" data-po="' + id + '" data-type="textarea">' + esc(o.value) + '</textarea>';
      } else if (o.type === 'file') {
        ctl = '<p class="text-muted small">' + esc(T('oe_file_unsupported')) + '</p>';
        blocked = blocked || !!o.required;
      } else {
        ctl = '<input type="text" class="form-control" data-po="' + id + '" data-type="' + esc(o.type) + '" value="' + esc(o.value) + '" />';
      }

      h += '<div class="col-sm-6"><div class="form-group oe-g' + (o.required ? ' required' : '') + '" data-opt="' + id + '"><label class="control-label">' + esc(o.name) + '</label><div class="oe-fc">' + ctl + '</div></div></div>';
    });

    h += '<div class="col-sm-3"><div class="form-group"><label class="control-label">' + esc(T('oe_col_qty')) + '</label><input type="text" class="form-control" id="oe-pending-qty" value="1" inputmode="numeric" /></div></div></div>'
      + '<button type="button" class="btn btn-primary" id="oe-pending-add"' + (blocked ? ' disabled="disabled"' : '') + '><i class="fa fa-plus-circle"></i> ' + esc(T('oe_add_to_order')) + '</button> '
      + '<button type="button" class="btn btn-default" id="oe-pending-cancel">' + esc(T('oe_cancel')) + '</button></div>';

    $('#oe-pending').html(h);
  }

  function pendingOptions() {
    var d = {};

    $('#oe-pending [data-po]').each(function () {
      var id = $(this).attr('data-po');
      var type = $(this).attr('data-type');

      if (type === 'checkbox') {
        if (this.checked) {
          d['option[' + id + '][]'] = (d['option[' + id + '][]'] || []).concat([this.value]);
        }
      } else if (this.value !== '') {
        d['option[' + id + ']'] = this.value;
      }
    });

    return d;
  }

  function addPending() {
    var p = state.pending;

    if (!p) {
      return;
    }

    var qty = parseInt($('#oe-pending-qty').val(), 10);
    var d = $.extend({ product_id: p.product_id, quantity: qty > 0 ? qty : 1 }, pendingOptions());

    enqueue(async function () {
      var j = await api('api/cart/add', d);

      $('#oe-pending .oe-err').remove();
      $('#oe-pending .has-error').removeClass('has-error');

      if (j.error) {
        var opt = j.error.option || {};

        Object.keys(opt).forEach(function (id) {
          var $g = $('#oe-pending [data-opt="' + id + '"]');

          $g.addClass('has-error');
          $('<div class="text-danger oe-err"></div>').text(opt[id]).appendTo($g.find('.oe-fc'));
        });

        if (j.error.store || j.error.warning || typeof j.error === 'string') {
          flash('danger', j.error.store || j.error.warning || j.error, true);
        }

        return;
      }

      state.pending = null;
      renderPending();
      setVal('p.search', '');
      dirty.cart = true;
      await syncNow();
    });
  }

  function cartOp(fn) {
    dirty.cart = true;
    enqueue(async function () {
      await fn();
      await syncNow();
    });
  }

  function changeQty(key, qty) {
    qty = parseInt(qty, 10);

    if (!(qty > 0)) {
      qty = 1;
    }

    cartOp(async function () {
      if (!state.cart.some(function (p) { return String(p.cart_id) === String(key); })) {
        return;
      }

      await api('api/cart/edit', { key: key, quantity: qty });
    });
  }

  /* ---------- wiring ---------- */

  function touchField(name) {
    if (name === 'store' || name === 'cust.search' || name === 'p.search' || /\.book$/.test(name) || /^vf\./.test(name)) {
      return;
    }

    if (name === 'cur') {
      dirty.cur = true;
    } else if (name.indexOf('cust.') === 0 || name.indexOf('cf.cust.') === 0) {
      dirty.cust = true;
    } else if (/^(ship|pay)\./.test(name) || /^cf\.(ship|pay)\./.test(name)) {
      dirty.addr = true;
    } else if (name === 'x.coupon') {
      dirty.coup = true;
    } else if (name === 'x.gift') {
      dirty.gift = true;
    } else if (name === 'x.reward') {
      dirty.rew = true;
    } else {
      return;
    }

    scheduleSync(/^x\.(coupon|gift|reward)$/.test(name) ? 0 : 500);
  }

  function mirrorCustomer() {
    ['firstname', 'lastname'].forEach(function (k) {
      var $t = fld('ship.' + k);

      if ($t.attr('data-auto') !== '0') {
        $t.val(val('cust.' + k));
      }
    });
  }

  function bind() {
    $root.on('input change', '[data-f]', function (e) {
      var name = $(this).attr('data-f');

      if (/^x\.(coupon|gift|reward)$/.test(name) && e.type === 'input') {
        return;
      }

      state.changed = true;

      if (e.type === 'input' && (name === 'ship.firstname' || name === 'ship.lastname') && e.originalEvent) {
        $(this).attr('data-auto', '0');
      }

      if (name === 'cust.firstname' || name === 'cust.lastname') {
        mirrorCustomer();
        dirty.addr = true;
        renderChip();
      }

      if (/^(ship|pay)\.country_id$/.test(name) && e.type === 'change') {
        var scope = name.split('.')[0];

        loadZones(scope, val(name), '').then(function () { touchField(scope + '.zone_id'); });
      }

      if (name === 'cur' && e.type === 'change') {
        dirty.ship = true;
        dirty.pay = true;
      }

      if (name === 'x.affiliate' && !val('x.affiliate')) {
        state.affiliateId = '';
      }

      touchField(name);
    });

    $root.on('blur change', '[data-f]', function () {
      var name = $(this).attr('data-f');

      state.touched[name] = true;
      renderErrors();
    });

    $root.on('keydown', '[data-f="x.coupon"], [data-f="x.gift"], [data-f="x.reward"]', function (e) {
      if (e.which === 13) {
        e.preventDefault();
        $(this).trigger('change');
      }
    });

    $root.on('change', '[data-f="store"]', function () {
      var $s = $(this);
      var prev = String(state.storeId);

      if ((state.cart.length || state.vouchers.length) && !window.confirm(T('oe_confirm_store'))) {
        $s.val(prev);

        return;
      }

      state.storeId = parseInt($s.val(), 10) || 0;
      state.loggedId = '';
      state.reloadCart = true;
      state.shipCode = '';
      state.payCode = '';
      markAll();
      scheduleSync(0);
    });

    $root.on('change', '[data-f$=".book"]', function () {
      var scope = $(this).attr('data-f').split('.')[0];
      var id = parseInt($(this).val(), 10);
      var a = state.addresses.filter(function (x) { return Number(x.address_id) === id; })[0];

      if (!a) {
        return;
      }

      fillAddress(scope, a).then(function () {
        dirty.addr = true;
        scheduleSync(0);
      });
    });

    $root.on('change', '#oe-same', function () {
      setSame(this.checked).then(function () {
        dirty.addr = true;
        scheduleSync(0);
        renderErrors();
      });
    });

    $root.on('click', '.oe-detach', function (e) {
      e.preventDefault();
      state.customerId = '';
      renderChip();
      dirty.cust = true;
      scheduleSync(0);
    });

    $root.on('click', '#oe-gift-toggle', function (e) {
      e.preventDefault();
      $('#oe-gift').slideToggle(120);
    });

    $root.on('click', '#oe-gift-add', function () {
      var d = {
        from_name: val('vf.from_name'), from_email: val('vf.from_email'), to_name: val('vf.to_name'), to_email: val('vf.to_email'),
        voucher_theme_id: val('vf.theme'), message: val('vf.message'), amount: val('vf.amount')
      };

      enqueue(async function () {
        var j = await api('api/voucher/add', d);

        state.err.vf = {};

        if (j.error) {
          mapErr('vf', j.error);
          Object.keys(state.err.vf).forEach(function (n) { state.touched[n] = true; });
          renderErrors();

          return;
        }

        renderErrors();
        ['to_name', 'to_email', 'message'].forEach(function (k) { setVal('vf.' + k, ''); });
        $('#oe-gift').slideUp(120);
        dirty.cart = true;
        await syncNow();
      });
    });

    $root.on('click', '.oe-qplus, .oe-qminus', function () {
      var $r = $(this).closest('tr');
      var $i = $r.find('.oe-qinput');
      var q = (parseInt($i.val(), 10) || 1) + ($(this).hasClass('oe-qplus') ? 1 : -1);

      $i.val(q > 0 ? q : 1);
      queueQty($r);
    });

    $root.on('input change', '.oe-qinput', function () {
      queueQty($(this).closest('tr'));
    });

    $root.on('click', '.oe-rm', function () {
      var $r = $(this).closest('tr');
      var key = $r.attr('data-key');
      var vkey = $r.attr('data-vkey');

      $r.addClass('is-removing');
      state.changed = true;
      cartOp(async function () {
        await api('api/cart/remove', { key: vkey || key });
      });
    });

    $root.on('change', 'input[name="oe-ship"]', function () {
      state.shipCode = this.value;
      state.shipLost = false;
      state.changed = true;
      $(this).closest('#oe-ship-methods').find('.oe-opt').removeClass('is-checked');
      $(this).closest('.oe-opt').addClass('is-checked');
      dirty.selShip = true;
      dirty.pay = true;
      scheduleSync(0);
    });

    $root.on('change', 'input[name="oe-pay"]', function () {
      state.payCode = this.value;
      state.payLost = false;
      state.changed = true;
      $(this).closest('#oe-pay-methods').find('.oe-opt').removeClass('is-checked');
      $(this).closest('.oe-opt').addClass('is-checked');
      dirty.selPay = true;
      scheduleSync(0);
    });

    $root.on('click', '#oe-pending-add', addPending);
    $root.on('click', '#oe-pending-cancel', function () {
      state.pending = null;
      renderPending();
      setVal('p.search', '');
    });

    $root.on('keydown', '#oe-pending-qty', function (e) {
      if (e.which === 13) {
        e.preventDefault();
        addPending();
      }
    });

    $root.on('click', '.oe-save', function () {
      save();
    });

    $root.on('click', '#oe-recalc', function (e) {
      e.preventDefault();
      state.reloadCart = true;
      markAll();
      scheduleSync(0);
    });

    $(window).on('beforeunload', function () {
      return state.changed && !state.saving ? T('oe_leave') : undefined;
    });
  }

  function queueQty($r) {
    var key = $r.attr('data-key');

    state.changed = true;
    clearTimeout(qtyTimers[key]);
    qtyTimers[key] = setTimeout(function () {
      changeQty(key, $r.find('.oe-qinput').val());
    }, 450);
  }

  function bindAutocomplete() {
    if (!$.fn.autocomplete) {
      return;
    }

    fld('cust.search').autocomplete({
      source: function (request, response) {
        $.ajax({
          url: 'index.php?route=customer/customer/autocomplete&user_token=' + encodeURIComponent(cfg.user_token) + '&filter_name=' + encodeURIComponent(request),
          dataType: 'json',
          success: function (json) {
            response($.map(json, function (item) {
              return { category: item.customer_group, label: item.name, value: item.customer_id, item: item };
            }));
          }
        });
      },
      select: function (picked) {
        var it = picked.item;

        state.customerId = String(it.customer_id);
        setVal('cust.search', '');
        setVal('cust.group', it.customer_group_id);
        setVal('cust.firstname', it.firstname);
        setVal('cust.lastname', it.lastname);
        setVal('cust.email', it.email);
        setVal('cust.telephone', it.telephone);
        customFields('account').forEach(function (c) { setCf('cust', c.id, (it.custom_field || {})[c.id] !== undefined ? it.custom_field[c.id] : (c.type === 'checkbox' ? [] : '')); });

        state.addresses = toArray(it.address);
        renderBooks();
        renderChip();
        mirrorCustomer();
        state.changed = true;
        dirty.cust = true;

        var first = state.addresses[0];
        var current = {};

        ADDR.forEach(function (k) { current[k] = val('ship.' + k); });

        var ready = first && addressEmpty({ address_1: current.address_1, city: current.city, firstname: '' }) ? fillAddress('ship', first) : Promise.resolve();

        ready.then(function () {
          dirty.addr = true;
          scheduleSync(0);
        });
      }
    });

    fld('p.search').autocomplete({
      source: function (request, response) {
        $.ajax({
          url: 'index.php?route=catalog/product/autocomplete&user_token=' + encodeURIComponent(cfg.user_token) + '&filter_name=' + encodeURIComponent(request),
          dataType: 'json',
          success: function (json) {
            response($.map(json, function (item) {
              return { label: item.name, value: item.product_id, model: item.model, option: item.option };
            }));
          }
        });
      },
      select: function (item) {
        state.pending = { product_id: item.value, name: item.label, model: item.model, options: toArray(item.option) };
        setVal('p.search', item.label);

        if (!state.pending.options.length) {
          addPending();
        } else {
          renderPending();
        }
      }
    });

    fld('x.affiliate').autocomplete({
      source: function (request, response) {
        $.ajax({
          url: 'index.php?route=customer/customer/autocomplete&user_token=' + encodeURIComponent(cfg.user_token) + '&filter_affiliate=1&filter_name=' + encodeURIComponent(request),
          dataType: 'json',
          success: function (json) {
            response($.map(json, function (item) {
              return { label: item.name, value: item.customer_id };
            }));
          }
        });
      },
      select: function (item) {
        setVal('x.affiliate', item.label);
        state.affiliateId = String(item.value);
        state.changed = true;
      }
    });
  }

  /* ---------- init ---------- */

  function initValues() {
    setVal('cust.firstname', O.firstname);
    setVal('cust.lastname', O.lastname);
    setVal('cust.email', O.email);
    setVal('cust.telephone', O.telephone);
    customFields('account').forEach(function (c) {
      if ((O.custom_field || {})[c.id] !== undefined) {
        setCf('cust', c.id, O.custom_field[c.id]);
      }
    });
    setVal('x.coupon', O.coupon);
    setVal('x.gift', O.voucher);
    setVal('x.reward', O.reward);
  }

  function init() {
    if (!cfg.api_token) {
      flash('danger', T('oe_api_off'), true);

      return;
    }

    $root.html(shell());
    renderBooks();
    renderChip();
    initValues();
    renderCart();
    renderTotals();
    renderMethods();
    bindAutocomplete();
    bind();

    var edit = O.order_id > 0;
    var pay = O.payment;
    var ship = O.shipping;
    var jobs = [];

    if (edit) {
      if (addressEmpty(ship) && !addressEmpty(pay)) {
        ship = pay;
      }

      state.same = ADDR.every(function (k) { return String(ship[k]) === String(pay[k]); });
    }

    jobs.push(fillAddress('ship', ship));

    if (!edit && !val('ship.country_id') && cfg.default_country_id) {
      setVal('ship.country_id', cfg.default_country_id);
      jobs.push(loadZones('ship', cfg.default_country_id, ''));
    }

    jobs.push(edit ? fillAddress('pay', pay) : Promise.resolve());

    fld('ship.firstname').attr('data-auto', edit && ship.firstname ? '0' : '1');
    fld('ship.lastname').attr('data-auto', edit && ship.lastname ? '0' : '1');

    $('#oe-same').prop('checked', state.same);
    $('#oe-payer-wrap').toggle(!state.same);

    Promise.all(jobs).then(function () {
      dirty.cur = true;

      if (edit) {
        state.reloadCart = true;
        state.loggedId = '';
        state.touched = {};
        markAll();
      } else {
        mirrorCustomer();
      }

      scheduleSync(0);
    });

    updateStatus();
  }

  init();
}());
