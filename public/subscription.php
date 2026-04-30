<?php
// ============================================================
//  subscription.php  —  Owner-Only License Management
//  Access is protected by a hardcoded MD5 owner password.
//  Only the software owner knows this password.
// ============================================================
require_once __DIR__ . '/../src/core.php';
$user = require_auth('admin');   // must be logged in as admin/superadmin
$page_title = 'Subscription & License';

$tid = tid();
$msg = '';
$err = '';

// ── OWNER PASSWORD GATE ───────────────────────────────────────
// MD5 of: @Daffodil1234567890#@
// Only the software owner knows this — admin cannot bypass it.
define('OWNER_PASS_HASH', '40f81949f237b6bc282196b45165c8ce');

// Session key that records a verified unlock (expires after 30 min)
define('OWNER_SESSION_KEY',  'sub_owner_unlocked');
define('OWNER_SESSION_TIME', 'sub_owner_unlock_ts');
define('OWNER_TIMEOUT',      1800); // 30 minutes

/**
 * Returns true if the owner has authenticated this session
 * and the unlock has not yet timed out.
 */
function owner_is_unlocked(): bool {
    if (empty($_SESSION[OWNER_SESSION_KEY])) return false;
    $ts = (int)($_SESSION[OWNER_SESSION_TIME] ?? 0);
    if (time() - $ts > OWNER_TIMEOUT) {
        // Timed out — clear and force re-auth
        unset($_SESSION[OWNER_SESSION_KEY], $_SESSION[OWNER_SESSION_TIME]);
        return false;
    }
    // Refresh timeout on activity
    $_SESSION[OWNER_SESSION_TIME] = time();
    return true;
}

// ── Handle owner password attempt ────────────────────────────
$pass_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['owner_password'])) {
    $entered = trim($_POST['owner_password'] ?? '');
    if (md5($entered) === OWNER_PASS_HASH) {
        $_SESSION[OWNER_SESSION_KEY]  = true;
        $_SESSION[OWNER_SESSION_TIME] = time();
        log_activity('owner_unlocked', 'Subscription',
            'Owner password verified — subscription panel unlocked');
        header('Location: ' . APP_URL . '/subscription.php');
        exit;
    } else {
        $pass_error = 'Incorrect owner password. Access denied.';
        log_activity('owner_unlock_failed', 'Subscription',
            'Invalid owner password attempt from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    }
}

// ── Handle lock button (owner can explicitly lock again) ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lock') {
    unset($_SESSION[OWNER_SESSION_KEY], $_SESSION[OWNER_SESSION_TIME]);
    header('Location: ' . APP_URL . '/subscription.php');
    exit;
}

// ── If not unlocked → show password gate, stop here ─────────
if (!owner_is_unlocked()) {
    // Calculate basic info to show even on the locked screen
    $locked_lic = lic_info();
    $locked_days = $locked_lic['days_left'];
    ob_start();
?>
<div style="max-width:480px;margin:40px auto">

  <!-- Status pill (read-only, always visible) -->
  <?php
    $sc = $locked_days < 0 ? '#c81e1e' : ($locked_days <= 7 ? '#d97706' : '#057a55');
    $sb = $locked_days < 0 ? '#fef2f2' : ($locked_days <= 7 ? '#fffbeb' : '#ecfdf5');
    $icon = $locked_days < 0 ? '🔒' : ($locked_days <= 7 ? '⚠' : '✅');
  ?>
  <div style="background:<?= $sb ?>;border:1px solid #e5e7eb;border-radius:10px;padding:18px 20px;margin-bottom:24px;display:flex;align-items:center;gap:14px">
    <span style="font-size:36px;line-height:1"><?= $icon ?></span>
    <div style="flex:1">
      <div style="font-weight:800;color:<?= $sc ?>;font-size:15px">
        <?= $locked_days < 0 ? 'LICENSE EXPIRED' : ($locked_days <= 7 ? 'EXPIRING SOON' : 'LICENSE ACTIVE') ?>
      </div>
      <?php if ($locked_lic['exists']): ?>
      <div style="font-size:13px;color:#374151;margin-top:4px;line-height:1.8">
        Expires: <strong><?= $locked_lic['expiry'] ? date('d M Y', strtotime($locked_lic['expiry'])) : '—' ?></strong>
        &nbsp;|&nbsp;
        Days left: <strong style="color:<?= $sc ?>"><?= max(0,$locked_days) ?></strong>
      </div>
      <?php else: ?>
      <div style="font-size:13px;color:#7f1d1d;margin-top:4px">No license installed.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Owner password form -->
  <div class="card">
    <div class="card-title" style="display:flex;align-items:center;gap:8px">
      <span style="font-size:20px">🔑</span>
      <span>Owner Access Required</span>
    </div>
    <p style="font-size:13px;color:#6b7280;margin-bottom:18px;line-height:1.7">
      This section is restricted to the <strong>software owner</strong> only.<br>
      Enter the owner password to manage the subscription and license.
    </p>

    <?php if ($pass_error): ?>
      <div class="alert alert-error" style="margin-bottom:14px">
        🚫 <?= h($pass_error) ?>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>Owner Password</label>
        <input type="password"
               name="owner_password"
               required autofocus
               placeholder="Enter owner password…"
               style="letter-spacing:.1em"
               autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;padding:11px;font-size:14px">
        🔓 Unlock Subscription Panel
      </button>
    </form>

    <div style="margin-top:14px;padding-top:12px;border-top:1px solid #f0f0f0;font-size:11px;color:#9ca3af;text-align:center;line-height:1.7">
      🔐 This panel is protected by a hardcoded owner password.<br>
      Regular admins and superadmins cannot access this without it.<br>
      Session auto-locks after 30 minutes of inactivity.
    </div>
  </div>

</div>
<?php
    $content = ob_get_clean();
    include __DIR__ . '/../templates/layout.php';
    exit; // Do NOT fall through to the management panel
}

// ════════════════════════════════════════════════════════════
//  OWNER IS AUTHENTICATED — full subscription management below
// ════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Generate / replace license
    if ($action === 'generate') {
        $expiry_date = trim($_POST['expiry_date'] ?? '');
        $note        = trim($_POST['note'] ?? '');
        if (!$expiry_date || !strtotime($expiry_date)) {
            $err = 'Please enter a valid expiry date.';
        } else {
            $data = [
                'tenant_id' => $tid,
                'issued'    => date('Y-m-d'),
                'expiry'    => $expiry_date,
                'note'      => $note,
                'issued_by' => $user['name'],
            ];
            lic_write($data);
            log_activity('license_generated', 'Subscription',
                "License set to expire: $expiry_date", json_encode($data));
            $msg = '✅ License generated. Expires: ' . date('d M Y', strtotime($expiry_date));
        }
    }

    // Revoke license (superadmin only)
    if ($action === 'revoke' && ($user['role'] ?? '') === 'superadmin') {
        if (file_exists(LIC_FILE)) {
            unlink(LIC_FILE);
            log_activity('license_revoked', 'Subscription', 'License file removed');
        }
        $msg = '🚫 License revoked. Users will not be able to log in.';
    }

    // Extend existing license by N days
    if ($action === 'extend') {
        $days = (int)($_POST['extend_days'] ?? 0);
        $lic  = lic_read();
        if (!$lic) {
            $err = 'No existing license to extend. Please generate one first.';
        } elseif ($days < 1 || $days > 3650) {
            $err = 'Enter between 1 and 3650 days.';
        } else {
            $base   = max(strtotime($lic['expiry']), time());
            $newExp = date('Y-m-d', strtotime("+{$days} days", $base));
            $lic['expiry']   = $newExp;
            $lic['extended'] = date('Y-m-d H:i:s');
            lic_write($lic);
            log_activity('license_extended', 'Subscription',
                "Extended by $days days. New expiry: $newExp");
            $msg = "✅ Extended by $days day(s). New expiry: " . date('d M Y', strtotime($newExp));
        }
    }
}

// ── Current license info ──────────────────────────────────────
$lic  = lic_info();
$days = $lic['days_left'];
$status_color = match(true) {
    !$lic['exists']  => '#6b7280',
    $days < 0        => '#c81e1e',
    $days <= 7       => '#d97706',
    default          => '#057a55',
};
$status_bg = match(true) {
    !$lic['exists']  => '#f3f4f6',
    $days < 0        => '#fef2f2',
    $days <= 7       => '#fffbeb',
    default          => '#ecfdf5',
};

// Compute unlock time remaining for display
$unlock_remaining = OWNER_TIMEOUT - (time() - (int)($_SESSION[OWNER_SESSION_TIME] ?? time()));
$unlock_min = max(0, (int)ceil($unlock_remaining / 60));

ob_start();
?>
<div style="max-width:720px">

<!-- ══ OWNER SESSION BANNER ════════════════════════════════ -->
<div style="background:linear-gradient(135deg,#1a1d2e,#1a56db);border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px">
  <div style="display:flex;align-items:center;gap:10px">
    <span style="font-size:22px">🔓</span>
    <div>
      <div style="font-size:13px;font-weight:800;color:#fff">Owner Session Active</div>
      <div style="font-size:11px;color:rgba(255,255,255,.65);margin-top:1px">
        Auto-locks in ~<?= $unlock_min ?> min · Session for: <?= h($user['name']) ?>
      </div>
    </div>
  </div>
  <form method="post" style="margin:0">
    <input type="hidden" name="action" value="lock">
    <button type="submit" class="btn" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);font-size:12px;padding:6px 12px">
      🔒 Lock Now
    </button>
  </form>
</div>

<?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:18px"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"   style="margin-bottom:18px"><?= h($err) ?></div><?php endif; ?>

<!-- ══ CURRENT STATUS ══════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px">
  <div class="card-title">🔐 Current License Status</div>

  <?php if (!$lic['exists']): ?>
    <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:24px;text-align:center">
      <div style="font-size:40px;margin-bottom:8px">❌</div>
      <div style="font-size:16px;font-weight:800;color:#c81e1e">No License Installed</div>
      <div style="font-size:13px;color:#7f1d1d;margin-top:6px;line-height:1.7">
        The file <code>public/yylic.txt</code> is missing or corrupt.<br>
        Users will <strong>not</strong> be able to log in until a license is generated.
      </div>
    </div>
  <?php else: ?>
    <div style="background:<?= $status_bg ?>;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="font-size:48px;line-height:1">
          <?= $days < 0 ? '🔒' : ($days <= 7 ? '⚠' : '✅') ?>
        </div>
        <div style="flex:1;min-width:200px">
          <div style="font-size:17px;font-weight:900;color:<?= $status_color ?>">
            <?= $days < 0 ? 'LICENSE EXPIRED' : ($days <= 7 ? 'EXPIRING SOON' : 'LICENSE ACTIVE') ?>
          </div>
          <div style="font-size:13px;color:#374151;margin-top:6px;line-height:1.9">
            <b>Expiry Date:</b> <?= $lic['expiry'] ? date('d M Y', strtotime($lic['expiry'])) : '—' ?><br>
            <b>Days Remaining:</b>
            <span style="font-weight:800;color:<?= $status_color ?>">
              <?= $days >= 0 ? "$days days" : abs($days).' days ago (EXPIRED)' ?>
            </span><br>
            <b>Issued:</b> <?= $lic['issued'] ? date('d M Y', strtotime($lic['issued'])) : '—' ?><br>
            <?php if ($lic['note']): ?>
            <b>Note:</b> <?= h($lic['note']) ?><br>
            <?php endif; ?>
            <b>Tenant ID:</b> <?= h($lic['tenant_id'] ?? '—') ?><br>
            <b>File:</b> <code style="background:#e5e7eb;padding:1px 6px;border-radius:3px;font-size:11px">public/yylic.txt</code>
          </div>
        </div>
        <div style="text-align:center;min-width:70px">
          <div style="font-size:36px;font-weight:900;color:<?= $status_color ?>">
            <?= max(0,$days) ?>
          </div>
          <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase">Days Left</div>
        </div>
      </div>

      <?php if ($lic['issued']): ?>
      <?php
        $total = max(1,(strtotime($lic['expiry']) - strtotime($lic['issued'])) / 86400);
        $used  = max(0, $total - max(0,$days));
        $pct   = min(100, round(($used/$total)*100));
        $bc    = $days < 0 ? '#c81e1e' : ($days <= 7 ? '#d97706' : ($days <= 30 ? '#f59e0b' : '#057a55'));
      ?>
      <div style="margin-top:14px">
        <div style="display:flex;justify-content:space-between;font-size:11px;color:#6b7280;margin-bottom:4px">
          <span>Issued <?= date('d M Y',strtotime($lic['issued'])) ?></span>
          <span><?= $pct ?>% used</span>
          <span>Expires <?= date('d M Y',strtotime($lic['expiry'])) ?></span>
        </div>
        <div style="height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden">
          <div style="height:100%;width:<?= $pct ?>%;background:<?= $bc ?>;border-radius:4px"></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ══ EXTEND ════════════════════════════════════════════ -->
<?php if ($lic['exists']): ?>
<div class="card" style="margin-bottom:20px">
  <div class="card-title">➕ Extend Existing License</div>
  <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="action" value="extend">
    <div class="form-group" style="margin:0;flex:1;min-width:160px">
      <label>Number of Days to Add</label>
      <input type="number" name="extend_days" id="extend_days_input"
             min="1" max="3650" placeholder="e.g. 365" required>
    </div>
    <button type="submit" class="btn btn-primary">⏩ Extend</button>
  </form>
  <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach ([30=>'1 Month',90=>'3 Months',180=>'6 Months',365=>'1 Year',730=>'2 Years'] as $d=>$label): ?>
      <button type="button"
        onclick="document.getElementById('extend_days_input').value=<?= $d ?>"
        class="btn btn-outline" style="font-size:12px;padding:5px 12px">
        +<?= $label ?>
      </button>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ══ GENERATE NEW ══════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px">
  <div class="card-title">🔑 Generate / Replace License</div>
  <form method="post">
    <input type="hidden" name="action" value="generate">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="form-group" style="margin:0">
        <label>Expiry Date *</label>
        <input type="date" name="expiry_date" required
               min="<?= date('Y-m-d') ?>"
               value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
      </div>
      <div class="form-group" style="margin:0">
        <label>Note (optional)</label>
        <input type="text" name="note"
               placeholder="e.g. Annual subscription — Invoice #123">
      </div>
    </div>
    <div style="margin-top:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">🔑 Generate License File</button>
      <span style="font-size:12px;color:#9ca3af">Overwrites existing <code>public/yylic.txt</code></span>
    </div>
  </form>

  <div style="margin-top:14px;padding-top:12px;border-top:1px solid #f0f0f0">
    <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:8px">Quick Presets</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ([
        '30 days'  =>'+30 days',
        '3 months' =>'+3 months',
        '6 months' =>'+6 months',
        '1 year'   =>'+1 year',
        '2 years'  =>'+2 years'
      ] as $label=>$offset): ?>
        <button type="button"
          onclick="document.querySelector('[name=expiry_date]').value='<?= date('Y-m-d', strtotime($offset)) ?>'"
          class="btn btn-outline" style="font-size:12px;padding:5px 12px">
          <?= $label ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ══ HOW IT WORKS ════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px;background:#f8f9fb">
  <div class="card-title" style="font-size:13px">ℹ How the License System Works</div>
  <div style="font-size:13px;color:#374151;line-height:1.9">
    <b>File location:</b> <code>public/yylic.txt</code><br>
    <b>Format:</b> Base64-encoded JSON (obfuscated, not encrypted)<br>
    <b>Fields stored:</b> tenant_id, issued date, expiry date, note, issued_by<br>
    <b>Login check:</b> Every login reads this file and verifies:<br>
    &nbsp;&nbsp;&nbsp;① File exists → ② Tenant ID matches → ③ Current date ≤ expiry date<br>
    <b>Warning:</b> Shows 7-day expiry warning banner on dashboard after login.<br>
    <b>On expiry:</b> Login is blocked with a clear "License Expired" message.<br>
    <b>Owner gate:</b> This panel requires the owner password (MD5-protected).<br>
    &nbsp;&nbsp;&nbsp;→ Admins/superadmins <em>cannot</em> access this without it.<br>
    <b>Session:</b> Owner session auto-expires after 30 minutes of inactivity.
  </div>
</div>

<!-- ══ DANGER ZONE ═══════════════════════════════════ -->
<?php if (($user['role'] ?? '') === 'superadmin'): ?>
<div class="card" style="border:2px solid #fca5a5;margin-bottom:20px">
  <div class="card-title" style="color:#c81e1e">⚠ Danger Zone (Superadmin Only)</div>
  <p style="font-size:13px;color:#6b7280;margin-bottom:14px">
    Revoking the license deletes <code>yylic.txt</code> immediately. All users will be locked out.
    This cannot be undone without generating a new license.
  </p>
  <form method="post" onsubmit="return confirm('REVOKE the license? ALL users will be locked out immediately!')">
    <input type="hidden" name="action" value="revoke">
    <button type="submit" class="btn" style="background:#c81e1e;color:#fff;border-color:#c81e1e">
      🚫 Revoke License Now
    </button>
  </form>
</div>
<?php endif; ?>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
