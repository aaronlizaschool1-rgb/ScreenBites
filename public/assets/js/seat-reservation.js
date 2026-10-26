(function () {
  'use strict';

  var cfg = window.SeatMatrixConfig;
  var root = document.getElementById('seatMatrix');
  if (!cfg || !root) { return; }

  var CART_KEY = 'screenbites.ticketCart';
  var WARN_AT = 60;

  var els = {
    msg: document.getElementById('seatMsg'),
    live: document.getElementById('seatLive'),
    list: document.getElementById('heldList'),
    empty: document.getElementById('heldEmpty'),
    clock: document.getElementById('holdClock'),
    total: document.getElementById('heldTotal'),
    checkout: document.getElementById('checkoutBtn'),
    releaseAll: document.getElementById('releaseAllBtn'),
    avail: document.getElementById('availCount')
  };

  var seats = {};
  var timeEls = {};
  var lastSig = '';
  var msgTimer = null;
  var pollInFlight = false;
  var warnedShape = false;

  var PHRASE = { available: 'available', held: 'held by another cashier', heldbyme: 'held by you', sold: 'sold' };

  function norm(v) { return String(v == null ? '' : v).toLowerCase(); }
  function peso(n) {
    return '\u20B1' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function mmss(sec) {
    var m = Math.floor(sec / 60), s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }
  function labelOf(s) { return s.row + s.number; }
  function ariaFor(s) {
    return 'Row ' + s.row + ', seat ' + s.number + ', ' + s.tier + ', ' + peso(s.price) + ', ' + PHRASE[s.status];
  }
  function byPosition(a, b) {
    if (a.row !== b.row) { return a.row < b.row ? -1 : 1; }
    return Number(a.number) - Number(b.number);
  }
  function mine() {
    return Object.keys(seats).map(function (k) { return seats[k]; })
      .filter(function (s) { return s.status === 'heldbyme'; })
      .sort(byPosition);
  }
  function countStatus(status) {
    return Object.keys(seats).filter(function (k) { return seats[k].status === status; }).length;
  }

  function say(text, kind) {
    els.msg.className = 'seat-msg is-' + (kind || 'info');
    els.msg.textContent = text;
    clearTimeout(msgTimer);
    msgTimer = setTimeout(function () {
      els.msg.textContent = '';
      els.msg.className = 'seat-msg';
    }, 7000);
  }

  function setConn(ok) {
    els.live.textContent = ok ? 'Seat map updates live' : 'Live updates paused. Retrying...';
    els.live.classList.toggle('is-down', !ok);
  }

  Array.prototype.forEach.call(root.querySelectorAll('.seat[data-seat-id]'), function (el) {
    var id = Number(el.getAttribute('data-seat-id'));
    var status = norm(el.getAttribute('data-status'));
    var secs = parseInt(el.getAttribute('data-seconds'), 10);
    seats[id] = {
      id: id,
      el: el,
      row: el.getAttribute('data-row'),
      number: el.getAttribute('data-number'),
      tier: el.getAttribute('data-tier'),
      price: parseFloat(el.getAttribute('data-price')) || 0,
      status: status,
      expiresAt: status === 'heldbyme' ? Date.now() + (isNaN(secs) ? cfg.holdTimeout : secs) * 1000 : 0,
      pending: false,
      settleUntil: 0,
      warned: false
    };
  });

  function applyStatus(s, status, seconds) {
    s.status = status;
    s.el.setAttribute('data-status', status);
    s.el.setAttribute('aria-pressed', status === 'heldbyme' ? 'true' : 'false');
    s.el.setAttribute('aria-disabled', (status === 'held' || status === 'sold') ? 'true' : 'false');
    s.el.setAttribute('aria-label', ariaFor(s));
    if (status === 'heldbyme') {
      s.expiresAt = Date.now() + (typeof seconds === 'number' ? seconds : cfg.holdTimeout) * 1000;
      s.warned = false;
    } else {
      s.expiresAt = 0;
    }
  }

  function setPending(s, on) {
    s.pending = on;
    s.el.classList.toggle('is-pending', on);
    s.el.setAttribute('aria-busy', on ? 'true' : 'false');
  }

  function renderList(items) {
    els.list.textContent = '';
    timeEls = {};
    items.forEach(function (s) {
      var li = document.createElement('li');
      li.className = 'held-item';

      var seat = document.createElement('div');
      seat.className = 'held-seat';
      var strong = document.createElement('strong');
      strong.textContent = 'Seat ' + labelOf(s);
      var small = document.createElement('small');
      small.textContent = s.tier;
      seat.appendChild(strong);
      seat.appendChild(small);

      var price = document.createElement('span');
      price.className = 'held-price';
      price.textContent = peso(s.price);

      var rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'held-remove';
      rm.textContent = 'Remove';
      rm.setAttribute('data-seat-id', s.id);
      rm.setAttribute('aria-label', 'Release seat ' + labelOf(s));

      var time = document.createElement('span');
      time.className = 'held-time';
      timeEls[s.id] = time;

      li.appendChild(seat);
      li.appendChild(price);
      li.appendChild(rm);
      li.appendChild(time);
      els.list.appendChild(li);
    });
  }

  function paintTimes() {
    var now = Date.now();
    var soonest = Infinity;
    mine().forEach(function (s) {
      var remain = Math.max(0, Math.ceil((s.expiresAt - now) / 1000));
      if (remain < soonest) { soonest = remain; }
      var t = timeEls[s.id];
      if (t) {
        t.textContent = 'Expires in ' + mmss(remain);
        t.classList.toggle('is-urgent', remain <= WARN_AT);
      }
      if (remain <= WARN_AT && remain > 0 && !s.warned) {
        s.warned = true;
        say('Your hold on seat ' + labelOf(s) + ' runs out in under a minute.', 'error');
      }
    });
    if (soonest === Infinity) {
      els.clock.textContent = 'No seats held';
      els.clock.classList.remove('is-urgent');
    } else {
      els.clock.textContent = 'Next hold runs out in ' + mmss(soonest);
      els.clock.classList.toggle('is-urgent', soonest <= WARN_AT);
    }
  }

  function refresh() {
    var items = mine();
    var sig = items.map(function (s) { return s.id; }).join(',');
    if (sig !== lastSig) {
      lastSig = sig;
      renderList(items);
    }
    var total = items.reduce(function (sum, s) { return sum + s.price; }, 0);
    els.total.textContent = peso(total);
    els.empty.hidden = items.length > 0;
    els.checkout.disabled = items.length === 0;
    els.releaseAll.disabled = items.length === 0;
    els.avail.textContent = countStatus('available');
    paintTimes();
  }

  function tick() {
    var now = Date.now();
    var expired = mine().filter(function (s) { return s.expiresAt - now <= 0; });
    if (expired.length) {
      expired.forEach(function (s) {
        applyStatus(s, 'available');
        say('Your hold on seat ' + labelOf(s) + ' ran out, so the seat was released.', 'error');
      });
      refresh();
      setTimeout(poll, 300);
      return;
    }
    paintTimes();
  }

  function post(path, body) {
    return fetch(cfg.baseUrl + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': cfg.csrfToken
      },
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.text().then(function (text) {
        var data = {};
        try { data = text ? JSON.parse(text) : {}; } catch (e) { data = {}; }
        return { ok: r.ok, status: r.status, data: data };
      });
    }).catch(function () {
      return { ok: false, status: 0, data: { message: 'Could not reach the server. Check your connection and try again.' } };
    });
  }

  function holdSeat(s) {
    if (s.pending) { return Promise.resolve(); }
    setPending(s, true);
    return post('/ticketing/hold-seat', { screening_id: cfg.screeningId, seat_id: s.id }).then(function (res) {
      setPending(s, false);
      s.settleUntil = Date.now() + 1500;
      if (res.ok && res.data.success !== false) {
        applyStatus(s, 'heldbyme', cfg.holdTimeout);
        say('Seat ' + labelOf(s) + ' is held for you for ' + Math.round(cfg.holdTimeout / 60) + ' minutes.', 'ok');
      } else if (res.status === 409) {
        applyStatus(s, 'held');
        say(res.data.message || 'Seat ' + labelOf(s) + ' was just taken by someone else. Pick another seat.', 'error');
      } else if (res.status === 401 || res.status === 403) {
        say('Your session has expired or you do not have access. Sign in again to continue.', 'error');
      } else {
        say(res.data.message || 'Seat ' + labelOf(s) + ' could not be held. Try again.', 'error');
      }
      refresh();
      setTimeout(poll, 400);
    });
  }

  function releaseSeat(s, quiet) {
    if (s.pending) { return Promise.resolve(false); }
    setPending(s, true);
    return post('/ticketing/release-seat', { screening_id: cfg.screeningId, seat_id: s.id }).then(function (res) {
      setPending(s, false);
      s.settleUntil = Date.now() + 1500;
      var ok = res.ok && res.data.success !== false;
      if (ok) {
        applyStatus(s, 'available');
        if (!quiet) { say('Seat ' + labelOf(s) + ' released.', 'info'); }
      } else {
        say(res.data.message || 'Seat ' + labelOf(s) + ' could not be released. Try again.', 'error');
      }
      refresh();
      setTimeout(poll, 400);
      return ok;
    });
  }

  function extractSeats(json) {
    if (Array.isArray(json)) { return json; }
    if (json && Array.isArray(json.seats)) { return json.seats; }
    if (json && json.data && Array.isArray(json.data.seats)) { return json.data.seats; }
    return null;
  }

  function sync(item) {
    var s = seats[Number(item.seat_id)];
    if (!s || s.pending || s.settleUntil > Date.now()) { return; }
    var status = norm(item.seat_status || item.status);
    if (!PHRASE[status]) { return; }
    var secs = typeof item.seconds_remaining === 'number' ? item.seconds_remaining : undefined;
    if (status === s.status) {
      if (status === 'heldbyme' && secs !== undefined) { s.expiresAt = Date.now() + secs * 1000; }
      return;
    }
    if (s.status === 'heldbyme') {
      say('Your hold on seat ' + labelOf(s) + ' has ended.', 'error');
    }
    applyStatus(s, status, secs);
  }

  function poll() {
    if (pollInFlight || document.hidden) { return Promise.resolve(); }
    pollInFlight = true;
    return fetch(cfg.screeningUrl, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    }).then(function (json) {
      var list = extractSeats(json);
      if (!list) {
        if (!warnedShape) {
          warnedShape = true;
          if (window.console) { console.warn('Seat polling: unexpected JSON shape, expected an array or {seats: [...]}', json); }
        }
        return;
      }
      list.forEach(sync);
      refresh();
      setConn(true);
    }).catch(function () {
      setConn(false);
    }).then(function () {
      pollInFlight = false;
    });
  }

  function loop() {
    poll().then(function () { setTimeout(loop, cfg.pollMs || 5000); });
  }

  root.addEventListener('click', function (e) {
    var btn = e.target.closest('.seat[data-seat-id]');
    if (!btn) { return; }
    var s = seats[btn.getAttribute('data-seat-id')];
    if (!s) { return; }
    if (s.status === 'available') {
      holdSeat(s);
    } else if (s.status === 'heldbyme') {
      releaseSeat(s);
    } else if (s.status === 'sold') {
      say('Seat ' + labelOf(s) + ' is already sold.', 'info');
    } else {
      say('Seat ' + labelOf(s) + ' is held by another cashier right now.', 'info');
    }
  });

  els.list.addEventListener('click', function (e) {
    var btn = e.target.closest('.held-remove');
    if (!btn) { return; }
    var s = seats[btn.getAttribute('data-seat-id')];
    if (!s) { return; }
    releaseSeat(s).then(function () { s.el.focus(); });
  });

  els.releaseAll.addEventListener('click', function () {
    var items = mine();
    if (!items.length) { return; }
    els.releaseAll.disabled = true;
    Promise.all(items.map(function (s) { return releaseSeat(s, true); })).then(function (results) {
      var n = results.filter(Boolean).length;
      if (n === items.length) {
        say('Released ' + n + (n === 1 ? ' seat.' : ' seats.'), 'info');
      } else {
        say('Some seats could not be released. Try again.', 'error');
      }
    });
  });

  els.checkout.addEventListener('click', function () {
    var items = mine();
    if (!items.length) { return; }
    var now = Date.now();
    var tickets = items.map(function (s) {
      return {
        screening_id: cfg.screeningId,
        seat_id: s.id,
        price: s.price,
        seat_label: labelOf(s),
        tier_name: s.tier,
        movie_title: cfg.movieTitle,
        hall_name: cfg.hallName,
        start_time: cfg.startTime,
        held_until: s.expiresAt
      };
    });
    try {

      var existing = JSON.parse(sessionStorage.getItem(CART_KEY) || '{"tickets":[]}');
      var others = (existing.tickets || []).filter(function (t) {
        return t.screening_id !== cfg.screeningId && t.held_until > now;
      });
      sessionStorage.setItem(CART_KEY, JSON.stringify({ tickets: others.concat(tickets) }));
    } catch (err) {
      say('Your seats could not be saved for checkout. Check that browser storage is allowed.', 'error');
      return;
    }
    window.location.href = cfg.checkoutUrl;
  });

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { poll(); }
  });

  refresh();
  setInterval(tick, 1000);
  setTimeout(loop, cfg.pollMs || 5000);
})();
