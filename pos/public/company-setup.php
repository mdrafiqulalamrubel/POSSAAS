<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('admin');
$tid  = tid();
$page_title = 'Company Setup';

// Load existing
$stmt = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$stmt->execute([$tid]);
$cs = $stmt->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'company_name'  => trim($_POST['company_name'] ?? ''),
        'address'       => trim($_POST['address'] ?? ''),
        'city'          => trim($_POST['city'] ?? ''),
        'country'       => trim($_POST['country'] ?? ''),
        'phone'         => trim($_POST['phone'] ?? ''),
        'phone2'        => trim($_POST['phone2'] ?? ''),
        'email'         => trim($_POST['email'] ?? ''),
        'website'       => trim($_POST['website'] ?? ''),
        'currency'      => trim($_POST['currency'] ?? '$'),
        'currency_code' => strtoupper(trim($_POST['currency_code'] ?? 'USD')),
        'tax_label'     => trim($_POST['tax_label'] ?? 'VAT'),
        'invoice_prefix'=> strtoupper(trim($_POST['invoice_prefix'] ?? 'INV')),
        'footer_note'   => trim($_POST['footer_note'] ?? ''),
    ];

    // Handle logo upload
    $logo_path = $cs['logo_path'] ?? '';
    if (!empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','gif','svg','webp'])) {
            $dir = __DIR__ . '/uploads/logos/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'logo_' . $tid . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $fname)) {
                // Remove old logo
                if ($logo_path && file_exists(__DIR__ . '/' . $logo_path)) {
                    @unlink(__DIR__ . '/' . $logo_path);
                }
                $logo_path = 'uploads/logos/' . $fname;
            }
        } else {
            $err = 'Logo must be PNG, JPG, GIF, SVG or WEBP.';
        }
    }

    if (empty($err)) {
        if ($cs) {
            $set = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $logo_path;
            $vals[] = $tid;
            db()->prepare("UPDATE company_settings SET $set, logo_path=? WHERE tenant_id=?")->execute($vals);
        } else {
            $fields['tenant_id']  = $tid;
            $fields['logo_path']  = $logo_path;
            $cols = implode(',', array_keys($fields));
            $plc  = implode(',', array_fill(0, count($fields), '?'));
            db()->prepare("INSERT INTO company_settings ($cols) VALUES ($plc)")->execute(array_values($fields));
        }
        flash('success', 'Company settings saved!');
        redirect('company-setup.php');
    }
}

ob_start();
?>
<div style="max-width:860px">
<div class="card">
  <div class="card-title">🏢 Company Setup</div>
  <?php if (!empty($err)): ?><div class="alert alert-error"><?= h($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <div style="font-size:13px;font-weight:600;color:var(--c-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--c-border)">
      Business Information
    </div>
    <div class="form-grid">
      <div class="form-group full">
        <label>Company Name *</label>
        <input type="text" name="company_name" value="<?= h($cs['company_name'] ?? '') ?>" required placeholder="Your Company Name">
      </div>
      <div class="form-group full">
        <label>Address</label>
        <textarea name="address" rows="2" placeholder="Street address…"><?= h($cs['address'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>City</label>
        <input type="text" name="city" value="<?= h($cs['city'] ?? '') ?>" placeholder="City">
      </div>
      <div class="form-group">
        <label>Country</label>
        <input type="text" name="country" value="<?= h($cs['country'] ?? '') ?>" placeholder="Country">
      </div>
      <div class="form-group">
        <label>Phone Number 1</label>
        <input type="text" name="phone" value="<?= h($cs['phone'] ?? '') ?>" placeholder="+1-555-0100">
      </div>
      <div class="form-group">
        <label>Phone Number 2</label>
        <input type="text" name="phone2" value="<?= h($cs['phone2'] ?? '') ?>" placeholder="+1-555-0200 (optional)">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= h($cs['email'] ?? '') ?>" placeholder="info@yourcompany.com">
      </div>
      <div class="form-group">
        <label>Website</label>
        <input type="url" name="website" value="<?= h($cs['website'] ?? '') ?>" placeholder="https://yourcompany.com">
      </div>
    </div>

    <div style="font-size:13px;font-weight:600;color:var(--c-muted);text-transform:uppercase;letter-spacing:.08em;margin:24px 0 12px;padding-bottom:8px;border-bottom:1px solid var(--c-border)">
      Logo
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label>Upload Logo</label>
        <input type="file" name="logo" accept="image/*">
        <small style="color:var(--c-muted)">PNG, JPG, SVG — max 2MB. Shown on invoices.</small>
      </div>
      <div class="form-group" style="justify-content:center">
        <?php if (!empty($cs['logo_path']) && file_exists(__DIR__ . '/' . $cs['logo_path'])): ?>
          <label>Current Logo</label>
          <img src="<?= APP_URL ?>/<?= h($cs['logo_path']) ?>" style="max-height:60px;max-width:200px;border:1px solid var(--c-border);border-radius:var(--radius);padding:6px">
        <?php else: ?>
          <span style="color:var(--c-muted);font-size:13px">No logo uploaded</span>
        <?php endif; ?>
      </div>
    </div>

    <div style="font-size:13px;font-weight:600;color:var(--c-muted);text-transform:uppercase;letter-spacing:.08em;margin:24px 0 12px;padding-bottom:8px;border-bottom:1px solid var(--c-border)">
      Invoice & Currency Settings
    </div>
    <div class="form-grid">
      <div class="form-group full">
        <label>Currency (select or type custom)</label>
        <select name="currency_preset" onchange="applyCurrencyPreset(this.value)" style="margin-bottom:6px">
          <option value="">— Select a currency preset —</option>
          <option value="BDT|৳|BDT">🇧🇩 Bangladeshi Taka (৳ BDT)</option>
          <option value="USD|$|USD">🇺🇸 US Dollar ($ USD)</option>
          <option value="EUR|€|EUR">🇪🇺 Euro (€ EUR)</option>
          <option value="GBP|£|GBP">🇬🇧 British Pound (£ GBP)</option>
          <option value="INR|₹|INR">🇮🇳 Indian Rupee (₹ INR)</option>
          <option value="SAR|﷼|SAR">🇸🇦 Saudi Riyal (﷼ SAR)</option>
          <option value="AED|د.إ|AED">🇦🇪 UAE Dirham (د.إ AED)</option>
          <option value="MYR|RM|MYR">🇲🇾 Malaysian Ringgit (RM MYR)</option>
          <option value="SGD|S$|SGD">🇸🇬 Singapore Dollar (S$ SGD)</option>
          <option value="JPY|¥|JPY">🇯🇵 Japanese Yen (¥ JPY)</option>
        </select>
        <div style="display:flex;gap:10px">
          <div style="flex:1">
            <label style="font-size:12px;color:var(--c-muted)">Symbol</label>
            <input type="text" name="currency" id="currency_sym" value="<?= h($cs['currency'] ?? '$') ?>" placeholder="৳ or $" maxlength="10">
          </div>
          <div style="flex:1">
            <label style="font-size:12px;color:var(--c-muted)">Code</label>
            <input type="text" name="currency_code" id="currency_code" value="<?= h($cs['currency_code'] ?? 'USD') ?>" placeholder="BDT or USD" maxlength="5">
          </div>
        </div>
      </div>
      <div class="form-group">
        <label>Tax Label</label>
        <input type="text" name="tax_label" value="<?= h($cs['tax_label'] ?? 'VAT') ?>" placeholder="VAT / GST / Tax" maxlength="20">
      </div>
      <div class="form-group">
        <label>Invoice Prefix</label>
        <input type="text" name="invoice_prefix" value="<?= h($cs['invoice_prefix'] ?? 'INV') ?>" placeholder="INV" maxlength="10">
      </div>
      <div class="form-group full">
        <label>Invoice Footer Note</label>
        <textarea name="footer_note" rows="2" placeholder="e.g. Thank you for your business! Payment due within 30 days."><?= h($cs['footer_note'] ?? '') ?></textarea>
      </div>
    </div>


  <script>
  function applyCurrencyPreset(val) {
    if (!val) return;
    const parts = val.split('|');
    document.getElementById('currency_sym').value = parts[1];
    document.getElementById('currency_code').value = parts[2];
  }
  // Auto-select preset if current value matches
  (function(){
    const sym = document.getElementById('currency_sym').value;
    const sel = document.querySelector('[name="currency_preset"]');
    if (!sel) return;
    for (const opt of sel.options) {
      const parts = opt.value.split('|');
      if (parts[1] === sym) { sel.value = opt.value; break; }
    }
  })();
  </script>
    <div style="margin-top:24px;display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">💾 Save Settings</button>
      <a href="<?= APP_URL ?>/index.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
