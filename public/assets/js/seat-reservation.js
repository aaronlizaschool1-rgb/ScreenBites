(function () {
  'use strict';

  const root = document.getElementById('seatApp');
  if (!root) return;

  const cfg = {
    screeningId: Number(root.dataset.screeningId),
    base: root.dataset.baseUrl,
    csrf: root.dataset.csrf,
    holdTimeout: Number(root.dataset.holdTimeout) || 600,
    mock: root.dataset.mock === '1',   // true = fake responses, no backend needed
    pollMs: 5000
  };

  const grid        = document.getElementById('seatGrid');
  const listEl      = document.getElementById('selectedList');
  const totalEl     = document.getElementById('selectedTotal');
  const timerEl     = document.getElementById('holdTimer');
  const timerBox    = document.getElementById('holdTimerBox');
  const checkoutBtn = document.getElementById('checkoutBtn');

  const STATUS_CLASS = { Available: 'available', Held: 'held', HeldByMe: 'mine', Sold: 'sold' };
  const STATUS_TEXT  = { Available: 'Available', Held: 'Reserved by someone else', HeldByMe: 'Held for you', Sold: 'Sold' };

  const seats = new Map();

  /* ---------- State ---------- */
  function setStatus(seat, status, secs) {
    const wasMine = seat.status === 'HeldByMe';
    seat.status = status;

    if (status === 'HeldByMe') {
      if (secs != null)  seat.expiresAt = Date.now() + secs * 1000;
      else if (!wasMine || !seat.expiresAt) seat.expiresAt = Date.now() + cfg.holdTimeout * 1000;
    } else {
      seat.expiresAt = null;
    }

    const b = seat.btn;
    b.classList.remove('available', 'held', 'mine', 'sold');
    b.classList.add(STATUS_CLASS[status] || 'sold');
    b.disabled = status === 'Held' || status === 'Sold';
    b.dataset.status = status;
    b.setAttribute('aria-pressed', status === 'HeldByMe' ? 'true' : 'false');
    b.setAttribute('aria-label',
      `Seat ${seat.label}, ${seat.tier}, ${formatPeso(seat.price)}, ${STATUS_TEXT[status] || status}`);
  }

  grid.querySelectorAll('.seat').forEach((btn) => {
    const secs = btn.dataset.seconds;
    const seat = {
      id: Number(btn.dataset.seatId),
      btn,
      row: btn.dataset.row,
      num: Number(btn.dataset.num),
      label: btn.dataset.row + btn.dataset.num,
      tier: btn.dataset.tier,
      price: Number(btn.dataset.price),
      status: null,
      expiresAt: null,
      busy: false
    };
    seats.set(seat.id, seat);
    setStatus(seat, btn.dataset.status, secs !== '' ? Number(secs) : null);
  });

  /* ---------- API ---------- */
  async function api(path, method, body) {
    if (cfg.mock) {
      await new Promise((r) => setTimeout(r, 250));
      return { ok: true, status: 200, data: { success: true, message: '(mock) OK' } };
    }
    const opts = {
      method,
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': cfg.csrf
      }
    };
    if (body) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    const res = await fetch(cfg.base + path, opts);
    let data = null;
    try { data = await res.json(); } catch (e) { /* non-JSON response */ }
    return { ok: res.ok, status: res.status, data };
  }

  /* ---------- Hold / release (FR4) ---------- */
  async function toggle(seat) {
    if (seat.busy) return;
    seat.busy = true;
    seat.btn.classList.add('busy');

    const holding = seat.status === 'Available';
    try {
      const r = await api(
        holding ? '/ticketing/hold-seat' : '/ticketing/release-seat',
        'POST',
        { screening_id: cfg.screeningId, seat_id: seat.id }
      );

      if (r.ok && r.data && r.data.success) {
        setStatus(seat, holding ? 'HeldByMe' : 'Available');
        showToast(holding ? `Seat ${seat.label} held for you` : `Seat ${seat.label} released`, 'success');
      } else if (r.status === 409) {
        setStatus(seat, 'Held');
        showToast(`Seat ${seat.label} was just taken by someone else.`, 'warn');
      } else {
        showToast((r.data && r.data.message) || 'Something went wrong. Please try again.', 'error');
      }
    } catch (e) {
      showToast('Network error. Check your connection.', 'error');
    } finally {
      seat.busy = false;
      seat.btn.classList.remove('busy');
      renderSummary();
    }
  }

  grid.addEventListener('click', (e) => {
    const btn = e.target.closest('.seat');
    if (!btn) return;
    const seat = seats.get(Number(btn.dataset.seatId));
    if (seat && (seat.status === 'Available' || seat.status === 'HeldByMe')) toggle(seat);
  });

  /* ---------- Summary + countdown ---------- */
  const mySeats = () =>
    [...seats.values()]
      .filter((s) => s.status === 'HeldByMe')
      .sort((a, b) => a.row.localeCompare(b.row) || a.num - b.num);

  function renderSummary() {
    const mine = mySeats();
    listEl.replaceChildren();

    if (!mine.length) {
      const li = document.createElement('li');
      li.className = 'empty';
      li.textContent = 'No seats selected yet.';
      listEl.appendChild(li);
    } else {
      mine.forEach((s) => {
        const li = document.createElement('li');
        const name = document.createElement('span');
        const price = document.createElement('span');
        name.textContent = `${s.label} · ${s.tier}`;
        price.textContent = formatPeso(s.price);
        li.append(name, price);
        listEl.appendChild(li);
      });
    }

    totalEl.textContent = formatPeso(mine.reduce((t, s) => t + s.price, 0));
    checkoutBtn.disabled = mine.length === 0;
    timerBox.hidden = mine.length === 0;
    updateTimer();
  }

  function updateTimer() {
    const mine = mySeats();
    if (!mine.length) return;
    const soonest = Math.min(...mine.map((s) => s.expiresAt));
    const left = Math.max(0, Math.ceil((soonest - Date.now()) / 1000));
    const mm = String(Math.floor(left / 60)).padStart(2, '0');
    const ss = String(left % 60).padStart(2, '0');
    timerEl.textContent = `${mm}:${ss}`;
    timerBox.classList.toggle('urgent', left <= 60);
  }

  // Runs every second: expire holds locally, then ask the server for the truth
  setInterval(() => {
    let expired = false;
    seats.forEach((s) => {
      if (s.status === 'HeldByMe' && s.expiresAt && Date.now() >= s.expiresAt) {
        setStatus(s, 'Available');
        expired = true;
      }
    });
    if (expired) {
      showToast('Your seat hold expired. Please pick your seats again.', 'warn');
      renderSummary();
      poll();
    } else {
      updateTimer();
    }
  }, 1000);

  /* ---------- Live polling (FR2) ---------- */
  async function poll() {
    if (cfg.mock || document.hidden) return;
    if ([...seats.values()].some((s) => s.busy)) return;   // don't fight an in-flight click

    try {
      const r = await api(`/ticketing/screening/${cfg.screeningId}`, 'GET');
      if (!r.ok || !r.data) return;
      const list = Array.isArray(r.data) ? r.data : r.data.seats;
      if (!Array.isArray(list)) return;

      list.forEach((d) => {
        const seat = seats.get(Number(d.seat_id));
        if (!seat || seat.busy) return;
        if (d.seat_status !== seat.status || d.seat_status === 'HeldByMe') {
          setStatus(seat, d.seat_status, d.seconds_remaining);
        }
      });
      renderSummary();
    } catch (e) { /* ignore, try again next tick */ }
  }

  setInterval(poll, cfg.pollMs);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

  renderSummary();
})();