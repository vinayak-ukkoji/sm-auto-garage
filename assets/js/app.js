/* S M AUTO GARAGE – shared front-end helpers (jQuery) */
(function ($) {
  'use strict';
  const A = window.APP || {};
  $.ajaxSetup({ headers: { 'X-CSRF-Token': A.csrf } });

  // ---------- Toast ----------
  window.toast = function (msg, type) {
    const el = $('<div class="toast align-items-center ' + (type === 'err' ? 'err' : 'ok') + '" role="alert"><div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>');
    el.find('.toast-body').text(msg || '');
    $('#toastBox').append(el);
    const t = new bootstrap.Toast(el[0], { delay: type === 'err' ? 4500 : 2500 });
    el.on('hidden.bs.toast', () => el.remove());
    t.show();
  };
  (A.flash || []).forEach(f => toast(f[0], f[1] === 'error' ? 'err' : 'ok'));

  // ---------- Formatting ----------
  window.inr = function (n, dec) {
    dec = dec === undefined ? 2 : dec;
    n = Number(n || 0);
    return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: dec, maximumFractionDigits: dec });
  };
  window.esc = function (s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  };
  window.debounce = function (fn, ms) { let t; return function () { clearTimeout(t); const a = arguments, s = this; t = setTimeout(() => fn.apply(s, a), ms || 300); }; };
  window.url = p => A.base + '/' + String(p).replace(/^\//, '');

  // ---------- API ----------
  function handle(p) {
    return p.then(r => {
      if (!r || !r.ok) { toast((r && r.msg) || A.i18n.error, 'err'); return $.Deferred().reject(r); }
      return r;
    }, xhr => {
      const m = (xhr.responseJSON && xhr.responseJSON.msg) || A.i18n.error;
      toast(m, 'err');
      if (xhr.status === 401) setTimeout(() => location.href = url('login.php'), 1200);
      return $.Deferred().reject(xhr);
    });
  }
  window.api = function (path, data, method) {
    method = method || (data ? 'POST' : 'GET');
    return handle($.ajax({ url: url('api/' + path), method: method, data: data || {}, dataType: 'json' }));
  };
  window.apiForm = function (path, formData) {
    formData.append('csrf', A.csrf);
    return handle($.ajax({ url: url('api/' + path), method: 'POST', data: formData, processData: false, contentType: false, dataType: 'json' }));
  };
  window.apiGet = (path, data) => api(path, data || {}, 'GET');

  // ---------- Generic AJAX forms ----------
  // <form data-api="customers.php?action=save" data-redirect="customer.php?id={id}" data-reload>
  $(document).on('submit', 'form[data-api]', function (ev) {
    ev.preventDefault();
    const f = $(this), btn = f.find('[type=submit]').prop('disabled', true);
    const hasFile = f.find('input[type=file]').length > 0;
    const p = hasFile ? apiForm(f.data('api'), new FormData(this)) : api(f.data('api'), f.serialize());
    p.then(r => {
      toast(r.msg || A.i18n.saved);
      const modal = f.closest('.modal');
      if (modal.length) bootstrap.Modal.getOrCreateInstance(modal[0]).hide();
      const rd = f.data('redirect');
      if (rd) setTimeout(() => location.href = url(String(rd).replace('{id}', (r.data && r.data.id) || '')), 400);
      else if (f.is('[data-reload]')) setTimeout(() => location.reload(), 400);
      f.trigger('saved', [r]);
    }).always(() => btn.prop('disabled', false));
  });

  // <button data-post="parts.php?action=delete" data-params='{"id":3}' data-confirm="Delete?" data-reload>
  $(document).on('click', '[data-post]', function (ev) {
    ev.preventDefault();
    const b = $(this);
    if (b.data('confirm') && !confirm(b.data('confirm'))) return;
    b.prop('disabled', true);
    api(b.data('post'), b.data('params') || {}).then(r => {
      toast(r.msg || A.i18n.saved);
      if (b.data('redirect')) setTimeout(() => location.href = url(String(b.data('redirect')).replace('{id}', (r.data && r.data.id) || '')), 400);
      else if (b.is('[data-reload]')) setTimeout(() => location.reload(), 400);
      b.trigger('posted', [r]);
    }).always(() => b.prop('disabled', false));
  });

  // Search inputs with a clear (x) button
  $(document).on('input', '.search-card input', function () { $(this).siblings('.clear').toggle(this.value.length > 0); });
  $(document).on('click', '.search-card .clear', function () { $(this).siblings('input').val('').trigger('input').focus(); });

  // Clickable rows
  $(document).on('click', '[data-href]', function (e) {
    if ($(e.target).closest('a,button,input,label,select').length) return;
    location.href = url($(this).data('href'));
  });

  // Fill a modal form from data attributes: <button data-bs-toggle="modal" data-bs-target="#m" data-fill='{"name":"x"}'>
  $(document).on('show.bs.modal', '.modal', function (ev) {
    const trg = ev.relatedTarget ? $(ev.relatedTarget) : null;
    const form = $(this).find('form');
    if (!trg || !form.length || trg.data('fill') === undefined) return;
    form[0].reset();
    form.find('input[type=hidden]:not([name=csrf])').val('');
    const d = trg.data('fill') || {};
    Object.keys(d).forEach(k => {
      const el = form.find('[name="' + k + '"]');
      if (el.is(':checkbox')) el.prop('checked', !!Number(d[k])); else el.val(d[k]);
    });
    if (trg.data('title')) $(this).find('.modal-title').text(trg.data('title'));
  });

  // ---------- Autocomplete ----------
  // autocomplete(input, sourceFn(term) => promise<array>, renderFn(item) => html, onPick(item))
  window.autocomplete = function (input, source, render, onPick, opts) {
    opts = opts || {};
    const $in = $(input);
    let list = $in.siblings('.ac-list');
    if (!list.length) { list = $('<div class="ac-list"></div>'); $in.after(list); }
    let items = [], idx = -1;
    const show = debounce(function () {
      const term = $in.val().trim();
      if (term.length < (opts.min || 1)) { list.hide(); return; }
      source(term).then(res => {
        items = res || []; idx = -1;
        let html = items.map((it, i) => '<div data-i="' + i + '">' + render(it) + '</div>').join('');
        if (opts.extra) html += opts.extra(term);
        if (!html) html = '<div class="text-muted">' + esc(A.i18n.no_results) + '</div>';
        list.html(html).show();
      });
    }, 250);
    $in.on('input focus', show);
    $in.on('keydown', function (e) {
      const divs = list.children('[data-i]');
      if (e.key === 'ArrowDown') { idx = Math.min(idx + 1, divs.length - 1); }
      else if (e.key === 'ArrowUp') { idx = Math.max(idx - 1, 0); }
      else if (e.key === 'Enter') { e.preventDefault(); if (idx >= 0) divs.eq(idx).trigger('mousedown'); else list.children('[data-extra]').first().trigger('mousedown'); return; }
      else return;
      divs.removeClass('hl').eq(idx).addClass('hl');
    });
    list.on('mousedown', '[data-i]', function (e) { e.preventDefault(); const it = items[$(this).data('i')]; list.hide(); onPick(it); });
    list.on('mousedown', '[data-extra]', function (e) { e.preventDefault(); list.hide(); opts.onExtra && opts.onExtra($in.val().trim()); });
    $in.on('blur', () => setTimeout(() => list.hide(), 150));
  };

  // ---------- Line editor (labour / parts / purchase lines) ----------
  window.LineEditor = function (el, opts) {
    const $el = $(el), kind = opts.kind, self = this;
    let lines = (opts.lines || []).map(l => Object.assign({}, l));
    const GST = [0, 5, 12, 18, 28];
    const gstOn = !(window.APP && window.APP.gst === false);   // GST disabled in Settings
    const rateOf = l => gstOn ? (+l.gst_rate || 0) : 0;
    const noDisc = kind === 'po';
    const placeholder = opts.placeholder || (kind === 'labour' ? 'Search / type service…' : 'Search part by name / number…');
    $el.html('<div class="le-rows"></div><div class="le-add"><input type="text" placeholder="' + esc(placeholder) + '"></div><div class="totals-box le-tot"></div>');
    const rowsEl = $el.find('.le-rows');

    function amount(l) { return Math.round(((+l.qty || 0) * (+l.rate || 0) - (noDisc ? 0 : (+l.discount || 0))) * 100) / 100; }
    function render() {
      rowsEl.html(lines.map((l, i) => {
        const stock = (kind !== 'labour' && l.stock !== undefined) ? ' <small class="' + ((+l.stock) < (+l.qty) ? 'neg' : 'text-muted') + '">(' + 'stock ' + (+l.stock) + ')</small>' : '';
        return '<div class="le-row" data-i="' + i + '">' +
          '<div class="le-top"><div class="le-desc"><input class="form-control form-control-sm le-d" value="' + esc(l.description) + '">' + stock + '</div>' +
          '<div class="le-amt">' + inr(amount(l)) + '</div><button type="button" class="le-del" title="Remove"><i class="bi bi-trash"></i></button></div>' +
          '<div class="le-grid">' +
          '<div><label>Qty</label><input type="number" step="0.01" min="0.01" class="le-q" value="' + esc(l.qty) + '"></div>' +
          '<div><label>' + (kind === 'po' ? 'Purchase rate' : 'Rate') + '</label><input type="number" step="0.01" min="0" class="le-r" value="' + esc(l.rate) + '"></div>' +
          (noDisc ? '' : '<div><label>Discount ₹</label><input type="number" step="0.01" min="0" class="le-x" value="' + esc(l.discount || 0) + '"></div>') +
          (gstOn ? '<div><label>GST %</label><select class="le-g">' + GST.map(g => '<option ' + (+l.gst_rate === g ? 'selected' : '') + '>' + g + '</option>').join('') + '</select></div>' : '') +
          '</div></div>';
      }).join(''));
      totals();
    }
    function totals() {
      let tax = 0, taxable = 0;
      lines.forEach(l => { const a = amount(l); taxable += a; tax += Math.round(a * rateOf(l)) / 100; });
      $el.find('.le-tot').html(lines.length ? (gstOn ? '<div class="kv"><span>Taxable</span><span>' + inr(taxable) + '</span></div><div class="kv"><span>GST</span><span>' + inr(tax) + '</span></div>' : '') + '<div class="kv"><span>Total</span><span>' + inr(taxable + tax) + '</span></div>' : '');
      opts.onChange && opts.onChange();
    }
    rowsEl.on('input change', 'input,select', function () {
      const i = $(this).closest('.le-row').data('i'), l = lines[i];
      if ($(this).hasClass('le-d')) l.description = this.value;
      if ($(this).hasClass('le-q')) l.qty = this.value;
      if ($(this).hasClass('le-r')) l.rate = this.value;
      if ($(this).hasClass('le-x')) l.discount = this.value;
      if ($(this).hasClass('le-g')) l.gst_rate = this.value;
      $(this).closest('.le-row').find('.le-amt').text(inr(amount(l)));
      totals();
    });
    rowsEl.on('click', '.le-del', function () { lines.splice($(this).closest('.le-row').data('i'), 1); render(); });

    const input = $el.find('.le-add input');
    if (kind === 'labour') {
      autocomplete(input, t => apiGet('search.php', { type: 'labour', q: t }).then(r => r.data),
        it => esc(it.name) + ' <small class="float-end">' + inr(it.rate) + '</small>',
        it => { lines.push({ labour_rate_id: it.id, description: it.name, qty: 1, rate: +it.rate, discount: 0, gst_rate: +it.gst_rate }); input.val(''); render(); },
        { extra: t => '<div data-extra="1"><i class="bi bi-plus-circle"></i> Add "' + esc(t) + '"</div>',
          onExtra: t => { if (!t) return; lines.push({ labour_rate_id: null, description: t, qty: 1, rate: 0, discount: 0, gst_rate: gstOn ? 18 : 0 }); input.val(''); render(); rowsEl.find('.le-r').last().focus(); } });
    } else {
      autocomplete(input, t => apiGet('search.php', { type: 'parts', q: t }).then(r => r.data),
        it => esc(it.name) + (it.part_no ? ' <small>(' + esc(it.part_no) + ')</small>' : '') + ' <small class="float-end">Stock ' + (+it.stock) + ' · ' + inr(kind === 'po' ? it.purchase_price : it.selling_price) + '</small>',
        it => {
          const ex = lines.find(l => +l.part_id === +it.id);
          if (ex) ex.qty = (+ex.qty || 0) + 1;
          else lines.push({ part_id: it.id, description: it.name, qty: 1, rate: +(kind === 'po' ? it.purchase_price : it.selling_price), discount: 0, gst_rate: +it.gst_rate, stock: +it.stock });
          input.val(''); render();
        });
    }
    this.get = () => lines.map(l => Object.assign({}, l, { amount: amount(l) }));
    this.set = ls => { lines = ls.map(l => Object.assign({}, l)); render(); };
    this.json = () => JSON.stringify(self.get());
    this.totals = () => { let tx = 0, ta = 0; lines.forEach(l => { const a = amount(l); ta += a; tx += Math.round(a * rateOf(l)) / 100; }); return { taxable: ta, tax: tx, total: ta + tx }; };
    render();
  };
})(jQuery);
