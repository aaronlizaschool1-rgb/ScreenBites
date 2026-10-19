/* public/assets/js/chart-analytics.js  (FR12, FR14, FR15)
 * Reads window.ScreenBitesCharts (set by each report view) and draws every
 * chart whose <canvas> exists on the current page. */
(function () {
  'use strict';

  var cfg = window.ScreenBitesCharts || {};
  var COLOR = { indigo: '#2b2d7a', red: '#d93a4a', gold: '#f5b93a', green: '#2f9e55', grid: '#e7e9f5', muted: '#5d6180' };
  var IDS = ['peakHourChart', 'occupancyChart', 'revenueSplitChart', 'financeChart'];

  function byId(id) { return document.getElementById(id); }

  function showMessage(canvas, text) {
    var p = document.createElement('p');
    p.className = 'chart-fallback';
    p.textContent = text;
    canvas.parentNode.replaceChild(p, canvas);
  }

  // Chart.js comes from a CDN, so it can be missing when offline
  if (typeof Chart === 'undefined') {
    IDS.forEach(function (id) {
      var c = byId(id);
      if (c) { showMessage(c, 'The chart could not load (Chart.js needs an internet connection). The figures are listed in the table on this page.'); }
    });
    return;
  }

  Chart.defaults.font.family = '"Figtree", system-ui, sans-serif';
  Chart.defaults.color = COLOR.muted;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    Chart.defaults.animation = false;
  }

  function peso(n) {
    return '\u20B1' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function pesoShort(n) { return '\u20B1' + Number(n || 0).toLocaleString('en-PH'); }
  function hourLabel(h) { return (h < 10 ? '0' : '') + h + ':00'; }
  function time12(t) {
    var parts = String(t || '').split(':');
    var h = parseInt(parts[0], 10);
    if (isNaN(h)) { return ''; }
    var suffix = h >= 12 ? 'PM' : 'AM';
    return (h % 12 || 12) + ':' + (parts[1] || '00') + ' ' + suffix;
  }

  /* ---- FR15: peak-hour curve (last 30 days) ---- */
  var peakCanvas = byId('peakHourChart');
  if (peakCanvas) {
    var peak = (cfg.peakHours || []).slice().sort(function (a, b) { return a.hour_of_day - b.hour_of_day; });
    if (!peak.length) {
      showMessage(peakCanvas, 'No ticket sales in the last 30 days.');
    } else {
      new Chart(peakCanvas, {
        type: 'line',
        data: {
          labels: peak.map(function (p) { return hourLabel(p.hour_of_day); }),
          datasets: [{
            label: 'Tickets sold',
            data: peak.map(function (p) { return p.total_tickets_sold; }),
            borderColor: COLOR.indigo,
            backgroundColor: 'rgba(43, 45, 122, .12)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
            pointBackgroundColor: COLOR.indigo
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: function (ctx) { return ctx.parsed.y + ' tickets'; } } }
          },
          scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: COLOR.grid } }
          }
        }
      });
    }
  }

  /* ---- FR15: occupancy per screening ---- */
  var occCanvas = byId('occupancyChart');
  if (occCanvas) {
    var rows = cfg.occupancy || [];
    if (!rows.length) {
      showMessage(occCanvas, 'No screenings on this date.');
    } else {
      new Chart(occCanvas, {
        type: 'bar',
        data: {
          labels: rows.map(function (r) { return [r.movie_title, r.hall_name + ', ' + time12(r.start_time)]; }),
          datasets: [{
            data: rows.map(function (r) { return r.rate; }),
            backgroundColor: rows.map(function (r) { return r.rate >= 80 ? COLOR.green : (r.rate < 30 ? COLOR.red : COLOR.indigo); }),
            borderRadius: 4,
            maxBarThickness: 28
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  var r = rows[ctx.dataIndex];
                  return r.rate + '% (' + r.sold + ' of ' + r.cap + ' seats)';
                }
              }
            }
          },
          scales: {
            x: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } }, grid: { color: COLOR.grid } },
            y: { grid: { display: false } }
          }
        }
      });
    }
  }

  /* ---- FR12 / FR14: ticket vs concession revenue ---- */
  var splitCanvas = byId('revenueSplitChart');
  if (splitCanvas) {
    var split = cfg.revenueSplit || { ticket: 0, concession: 0 };
    var splitTotal = Number(split.ticket) + Number(split.concession);
    if (!splitTotal) {
      showMessage(splitCanvas, 'No sales recorded for this period.');
    } else {
      new Chart(splitCanvas, {
        type: 'doughnut',
        data: {
          labels: ['Box office', 'Concessions'],
          datasets: [{
            data: [split.ticket, split.concession],
            backgroundColor: [COLOR.indigo, COLOR.gold],
            borderWidth: 2,
            borderColor: '#ffffff'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          plugins: {
            legend: { position: 'bottom' },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  var pct = (ctx.parsed / splitTotal * 100).toFixed(1);
                  return ctx.label + ': ' + peso(ctx.parsed) + ' (' + pct + '%)';
                }
              }
            }
          }
        }
      });
    }
  }

  /* ---- FR12: revenue vs expenses vs net income ---- */
  var financeCanvas = byId('financeChart');
  if (financeCanvas) {
    var fin = cfg.finance || {};
    new Chart(financeCanvas, {
      type: 'bar',
      data: {
        labels: ['Gross revenue', 'Cost of goods', 'Utilities', 'Net income'],
        datasets: [{
          data: [fin.gross, fin.cogs, fin.utilities, fin.net],
          backgroundColor: [COLOR.indigo, COLOR.red, '#e98a94', fin.net >= 0 ? COLOR.green : COLOR.red],
          borderRadius: 4,
          maxBarThickness: 56
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function (ctx) { return peso(ctx.parsed.y); } } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { ticks: { callback: function (v) { return pesoShort(v); } }, grid: { color: COLOR.grid } }
        }
      }
    });
  }
})();