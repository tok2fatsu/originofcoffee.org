<?php
// public/register.php
declare(strict_types=1);

// Simple public registration page for exhibitors.
// Posts to ../api.php?target=exhibitors&action=register

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Exhibitor Registration — Origin Expo</title>
  <link rel="stylesheet" href="/assets/css/styles.css">
  <style>
    /* Minimal form styles overriding site styles for registration form */
    .register-wrapper { max-width: 820px; margin: 48px auto; padding: 24px; background:#fff; border-radius:10px; box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
    label{ display:block; margin:10px 0 6px; font-weight:600; color:#222;}
    input, textarea, select { width:100%; padding:10px 12px; border:1px solid #e6e6e6; border-radius:6px; font-size:1rem; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
    .form-actions { margin-top:14px; display:flex; gap:10px; align-items:center; }
    .btn { padding:10px 14px; border-radius:8px; cursor:pointer; border:none; }
    .btn-primary { background:#8a4d03; color:white; }
    .btn-ghost { background:transparent; color:#333; border:1px solid #ddd; }
    .muted { color:#666; font-size:.95rem; margin-top:6px; }
    .alert { padding:10px; border-radius:6px; background:#f8f8f8; margin:10px 0;}
  </style>
</head>
<body>
  <main class="container">
    <section class="register-wrapper" aria-labelledby="reg-title">
      <h1 id="reg-title">Exhibitor Registration</h1>
      <p class="muted">Fill the form below to apply as an exhibitor. Applications are reviewed by our team — you will receive an email when your application is approved.</p>

      <div id="result" role="status" aria-live="polite"></div>

      <form id="regForm" autocomplete="on">
        <div>
          <label for="company_name">Company / Brand name *</label>
          <input id="company_name" name="company_name" type="text" required>
        </div>

        <div class="grid">
          <div>
            <label for="contact_name">Contact person *</label>
            <input id="contact_name" name="contact_name" type="text" required>
          </div>
          <div>
            <label for="contact_email">Contact email *</label>
            <input id="contact_email" name="contact_email" type="email" required>
          </div>
        </div>

        <div class="grid">
          <div>
            <label for="country">Country *</label>
            <select id="country" name="country" required>
              <option value="Ethiopia" selected>Ethiopia</option>
              <option value="United States">United States</option>
              <option value="United Kingdom">United Kingdom</option>
              <!-- Add more client-side if needed (your existing scripts.js already populates a select elsewhere) -->
            </select>
          </div>
          <div>
            <label for="phone">Phone *</label>
            <input id="phone" name="phone" type="tel" inputmode="tel" required>
          </div>
        </div>

        <div>
          <label for="password">Password (optional)</label>
          <input id="password" name="password" type="password" autocomplete="new-password"
                 placeholder="Provide a password now to use on accounts.originofcoffee.org after approval (optional)">
          <div class="muted">If you provide a password it will be used for your account on approval. If you leave blank we will create a secure password and email it to you on approval.</div>
        </div>

        <div>
          <label for="notes">Notes / Booth preferences (optional)</label>
          <textarea id="notes" name="notes" rows="4"></textarea>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Submit application</button>
          <button type="button" id="cancelBtn" class="btn btn-ghost">Reset</button>
        </div>
      </form>
    </section>
  </main>

<script>
(() => {
  const form = document.getElementById('regForm');
  const result = document.getElementById('result');
  const cancel = document.getElementById('cancelBtn');

  function setResult(html, ok){
    result.innerHTML = '<div class="alert">'+html+'</div>';
    if (ok) result.querySelector('.alert').style.borderLeft = '4px solid #8a4d03';
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    result.innerHTML = '';
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Submitting…';

    const fd = new FormData(form);
    // Basic client side validation
    const email = fd.get('contact_email') || '';
    const company = fd.get('company_name') || '';
    if (!email || !company) { setResult('Company name and contact email are required.'); btn.disabled=false; btn.textContent='Submit application'; return; }

    try {
      const resp = await fetch('/api.php?target=exhibitors&action=register', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });

      if (!resp.ok) {
        const txt = await resp.text();
        setResult('Server error: ' + (txt || resp.statusText));
        btn.disabled = false; btn.textContent='Submit application';
        return;
      }

      const json = await resp.json();
      if (json.ok) {
        setResult('Application received. We have created a user for you and your application is pending review by our team. You will receive email updates at the address you provided.', true);
        form.reset();
      } else {
        setResult('Error: ' + (json.error || 'Unknown error'));
      }
    } catch (err) {
      console.error(err);
      setResult('Network error: ' + err.message);
    } finally {
      btn.disabled = false; btn.textContent='Submit application';
    }
  });

  cancel.addEventListener('click', () => form.reset());
})();
</script>
</body>
</html>
