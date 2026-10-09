<?php
$pageTitle  = 'Layout Test';
$activePage = 'dashboard';
require __DIR__ . '/../app/views/layouts/header.php';
?>

<div class="grid grid-4 mb">
  <div class="card"><div class="stat-label">Tickets today</div><div class="stat-value">128</div></div>
  <div class="card"><div class="stat-label">Snack sales</div><div class="stat-value">₱9,450.00</div></div>
  <div class="card"><div class="stat-label">Net income (month)</div><div class="stat-value">₱182,300.00</div></div>
  <div class="card stat-low"><div class="stat-label">Low-stock items</div><div class="stat-value">3</div></div>
</div>

<div class="alert alert-warning">Popcorn kernels are below the reorder level.</div>

<div class="grid grid-2 mb">
  <div class="card">
    <h3 class="card-title">Seat colors</h3>
    <div class="screen-bar"></div>
    <div class="row">
      <button class="seat available">A1</button>
      <button class="seat reserved">A2</button>
      <button class="seat sold">A3</button>
      <button class="seat available selected">A4</button>
    </div>
  </div>

  <div class="card">
    <h3 class="card-title">Buttons and form</h3>
    <div class="form-group">
      <label for="demo">Movie title</label>
      <input type="text" id="demo" placeholder="Enter title">
    </div>
    <div class="row">
      <button class="btn">Save</button>
      <button class="btn btn-accent" onclick="showToast('Saved!', 'success')">Show toast</button>
      <button class="btn btn-outline">Cancel</button>
      <button class="btn btn-danger" data-confirm="Delete this?">Delete</button>
    </div>
  </div>
</div>

<div class="card table-wrap">
  <h3 class="card-title">Inventory</h3>
  <table>
    <thead><tr><th>Item</th><th>Stock</th><th>Status</th></tr></thead>
    <tbody>
      <tr><td>Popcorn kernels</td><td>4 kg</td><td><span class="badge badge-danger">Low</span></td></tr>
      <tr><td>Bottled water</td><td>120</td><td><span class="badge badge-success">OK</span></td></tr>
      <tr><td>Candy bars</td><td>18</td><td><span class="badge badge-warning">Reorder soon</span></td></tr>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>