// Mobile sidebar toggle
(function () {
  const btn = document.getElementById('menuBtn');
  const scrim = document.getElementById('scrim');
  const toggle = (open) => document.body.classList.toggle('nav-open', open);
  if (btn)   btn.addEventListener('click', () => toggle(!document.body.classList.contains('nav-open')));
  if (scrim) scrim.addEventListener('click', () => toggle(false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') toggle(false); });
})();

// showToast('Seat held for 5:00', 'success')   types: success | warn | error | info
window.showToast = function (message, type = 'info', ms = 3500) {
  const stack = document.getElementById('toastStack');
  if (!stack) return;
  const el = document.createElement('div');
  el.className = 'toast toast-' + type;
  el.textContent = message;
  stack.appendChild(el);
  setTimeout(() => el.remove(), ms);
};

// formatPeso(1234.5) -> "₱1,234.50"
window.formatPeso = function (n) {
  return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// <button data-confirm="Cancel this ticket?"> asks before proceeding
document.addEventListener('click', (e) => {
  const el = e.target.closest('[data-confirm]');
  if (el && !confirm(el.dataset.confirm)) e.preventDefault();
});