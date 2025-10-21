<?php
// public/tickets.php
declare(strict_types=1);
require_once __DIR__ . '/../src/lib/Database.php';

// Load ticket types
$pdo = Database::getConnection();
$stmt = $pdo->query("SELECT id, name, price, currency, description FROM ticket_types ORDER BY id ASC");
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);

$notice = '';
if (isset($_GET['status']) && $_GET['status'] === 'redirect' && !empty($_GET['reference'])) {
    $notice = "We redirected you to the payment page. After payment you will receive confirmation by email. Reference: " . htmlspecialchars($_GET['reference']);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Buy Tickets — Origin Expo</title>
  <link rel="stylesheet" href="/assets/css/styles.css">
  <style>
    .ticket-card { border:1px solid #eee; padding:14px; border-radius:10px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; }
    .muted { color:#666; font-size:.95rem; }
    .btn { padding:10px 14px; border-radius:8px; cursor:pointer; border:none; }
    .btn-primary { background:#8a4d03; color:#fff; }
    .form-group { margin:8px 0; }
    .grid { display:grid; grid-template-columns:1fr 280px; gap:18px; }
  </style>
</head>
<body>
  <main class="container">
    <h1>Buy Tickets</h1>
    <?php if ($notice): ?>
      <div class="alert"><?php echo $notice; ?></div>
    <?php endif; ?>

    <div class="grid">
      <div>
        <?php foreach ($types as $t): ?>
          <div class="ticket-card">
            <div>
              <strong><?php echo htmlspecialchars($t['name']); ?></strong>
              <div class="muted"><?php echo htmlspecialchars($t['description'] ?? ''); ?></div>
            </div>
            <div>
              <div style="text-align:right">
                <div><strong><?php echo number_format((float)$t['price'],2)." ".htmlspecialchars($t['currency'] ?? 'ETB'); ?></strong></div>
                <button class="btn btn-primary" data-id="<?php echo intval($t['id']); ?>">Buy</button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <aside style="padding:12px; border-left:1px solid #f0f0f0;">
        <h3>Checkout</h3>
        <form id="checkoutForm">
          <div class="form-group">
            <label for="ticket_type">Ticket type</label>
            <select id="ticket_type" name="ticket_type_id" required>
              <?php foreach ($types as $t): ?>
                <option value="<?php echo intval($t['id']); ?>"><?php echo htmlspecialchars($t['name']) . ' — ' . number_format((float)$t['price'],2); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label for="fullname">Full name</label><input id="fullname" name="full_name" type="text" required></div>
          <div class="form-group"><label for="email">Email</label><input id="email" name="email" type="email" required></div>
          <div class="form-group"><label for="phone">Phone</label><input id="phone" name="phone" type="tel" required></div>
          <div class="form-group"><label for="qty">Quantity</label><input id="qty" name="quantity" type="number" min="1" value="1" required></div>
          <div class="form-group"><button type="submit" id="payBtn" class="btn btn-primary">Pay with Chapa</button></div>
          <div id="checkoutMsg" class="muted"></div>
        </form>
      </aside>
    </div>
  </main>

<script>
(function(){
  const form = document.getElementById('checkoutForm');
  const msg = document.getElementById('checkoutMsg');
  const payBtn = document.getElementById('payBtn');

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    payBtn.disabled = true;
    msg.textContent = 'Creating order...';

    const fd = new FormData(form);
    try {
      const res = await fetch('/public/api.php?target=tickets&action=create', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
      if (!res.ok) {
        msg.textContent = 'Server error: ' + res.status;
        payBtn.disabled = false;
        return;
      }
      const json = await res.json();
      if (!json.ok) {
        msg.textContent = 'Error: ' + (json.error || 'unknown error');
        payBtn.disabled = false;
        return;
      }
      // redirect to Chapa checkout
      window.location.href = json.checkout_url;
    } catch (err) {
      console.error(err);
      msg.textContent = 'Network error: ' + err.message;
      payBtn.disabled = false;
    }
  });

  // quick handler for "Buy" buttons
  document.querySelectorAll('.ticket-card .btn-primary').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-id');
      document.getElementById('ticket_type').value = id;
      window.scrollTo({top:0, behavior:'smooth'});
    });
  });
})();
</script>
</body>
</html>
