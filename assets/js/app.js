/* Durban's Pallets — shared behaviour for the shop, the customer panel and the admin. */
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  function toast(msg) {
    var t = $('#toast'); if (!t) return;
    t.textContent = msg; t.classList.add('show');
    clearTimeout(t._h); t._h = setTimeout(function () { t.classList.remove('show'); }, 2800);
  }
  window.pmToast = toast;

  function post(path, data) {
    var body = new FormData();
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
    body.append('_csrf', PM.csrf);
    return fetch(PM.url + '/' + path, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }
  window.pmPost = post;

  // Theme
  if ($('#themeBtn')) $('#themeBtn').onclick = function () {
    var r = document.documentElement, dark = r.dataset.theme ? r.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
    r.dataset.theme = dark ? 'light' : 'dark';
    try { localStorage.setItem('pm-theme', r.dataset.theme); } catch (e) {}
  };

  // Mobile menu (site) + sidebar (panels)
  var burger = $('#burger'), nav = $('#mainNav');
  if (burger) burger.onclick = function () { var o = nav.classList.toggle('open'); burger.setAttribute('aria-expanded', o); burger.innerHTML = o ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-bars"></i>'; };
  var side = $('#admSide'), scrim = $('#admScrim');
  if ($('#admBurger')) $('#admBurger').onclick = function () { side.classList.add('open'); scrim.classList.add('show'); };
  if (scrim) scrim.onclick = function () { side.classList.remove('open'); scrim.classList.remove('show'); };

  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });
  $$('form[data-busy]').forEach(function (f) {
    f.addEventListener('submit', function () { var b = f.querySelector('[type=submit]'); if (b) { b.disabled = true; b.classList.add('is-busy'); } });
  });

  function setCount(n) {
    var b = $('#basketN'); if (!b) return;
    b.textContent = n; b.hidden = !n;
  }

  // Add to quote (cards + product page)
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-add]'); if (!b) return;
    e.preventDefault();
    var qtyInput = b.dataset.qtyFrom ? $(b.dataset.qtyFrom) : null;
    var noteInput = b.dataset.noteFrom ? $(b.dataset.noteFrom) : null;
    var qty = qtyInput ? parseInt(qtyInput.value, 10) || 1 : 1;
    b.disabled = true;
    post('ajax/basket.php', { action: 'add', product_id: b.dataset.add, qty: qty, notes: noteInput ? noteInput.value : '' }).then(function (r) {
      b.disabled = false;
      if (!r.ok) { toast(r.error || 'Could not add'); return; }
      setCount(r.count);
      b.innerHTML = '<i class="fa-solid fa-check"></i>Added';
      setTimeout(function () { b.innerHTML = '<i class="fa-solid fa-file-circle-plus"></i>Add to quote'; }, 1800);
      toast(r.message);
      if (window.fbq) fbq('track', 'AddToCart');
    }).catch(function () { b.disabled = false; toast('Network problem — please try again'); });
  });

  // Quantity steppers
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-step]'); if (!b) return;
    var i = b.parentNode.querySelector('input'); if (!i) return;
    var v = Math.max(parseInt(i.min || '1', 10), (parseInt(i.value, 10) || 0) + parseInt(b.dataset.step, 10));
    i.value = v; i.dispatchEvent(new Event('change', { bubbles: true }));
  });

  // Basket page: update qty / notes / remove without reloading
  $$('.qline[data-id]').forEach(function (row) {
    var id = row.dataset.id, qty = row.querySelector('input[type=number]'), note = row.querySelector('input.note'), t;
    function save() {
      post('ajax/basket.php', { action: 'set', product_id: id, qty: qty.value, notes: note ? note.value : '' }).then(function (r) {
        if (!r.ok) return;
        setCount(r.count);
        var line = row.querySelector('[data-line]'); if (line) line.textContent = r.line;
        var est = $('#basketEst'); if (est) est.textContent = r.estimate;
      });
    }
    if (qty) qty.addEventListener('change', function () { clearTimeout(t); t = setTimeout(save, 250); });
    if (note) note.addEventListener('change', save);
    var rm = row.querySelector('.rm');
    if (rm) rm.onclick = function () {
      post('ajax/basket.php', { action: 'set', product_id: id, qty: 0 }).then(function (r) {
        setCount(r.count); row.remove();
        var est = $('#basketEst'); if (est) est.textContent = r.estimate;
        if (!r.count) location.reload();
      });
    };
  });

  // Product gallery
  $$('.gallery__thumbs button').forEach(function (b) {
    b.onclick = function () {
      $('.gallery__main img').src = b.dataset.src;
      $$('.gallery__thumbs button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
    };
  });

  // Live estimate on the product page
  var est = $('#pdpEst'), q = $('#pdpQty');
  if (est && q) {
    var upd = function () { var n = Math.max(1, parseInt(q.value, 10) || 1), p = parseFloat(est.dataset.price); est.textContent = 'R' + (n * p).toLocaleString('en-ZA', { maximumFractionDigits: 2 }).replace(/,/g, ' '); };
    q.addEventListener('input', upd); q.addEventListener('change', upd); upd();
  }

  // Copy buttons (admin)
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () { navigator.clipboard.writeText(b.dataset.copy); b.innerHTML = '<i class="fa-solid fa-check"></i>'; });
  });

  // Scroll message threads to the newest message
  $$('.msgs').forEach(function (m) { m.scrollTop = m.scrollHeight; });
})();
