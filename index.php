<?php
// ============================================================
//  index.php — Login page
//  Flow: pick Manager / OIC / Cashier  →  (OIC/Cashier only)
//        pick a branch  →  enter that branch's password
// ============================================================
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in? Go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php'); exit;
}

$error = '';

// ── Manager password (management, full access, no branch) ──
$MANAGER_PASSWORD = 'manager123';

// ── Accounting Head password (company-wide financial oversight,
//    full access across every branch, no branch selection — same
//    shape as Manager login, distinct role for tracking/reporting) ──
$ACCOUNTING_HEAD_PASSWORD = 'accthead123';

// ── Branch passwords — shared by both the OIC and Cashier
//    entry points. Which icon was clicked decides the access
//    level (subrole), not the password. ─────────────────────
$BRANCHES = [
    'stella'        => ['label' => 'Stella',        'password' => 'stella123'],
    'dois'          => ['label' => 'Dois',          'password' => 'dois123'],
    'h'             => ['label' => 'H',             'password' => 'h123'],
    'pub_express'   => ['label' => 'Pub Express',   'password' => 'pubexpress123'],
    'commissary'    => ['label' => 'Commissary',    'password' => 'commissary123'],
    'recovery'      => ['label' => 'Recovery',      'password' => 'recovery123'],
    'demiclab'      => ['label' => 'DemicLab-Main', 'password' => 'demic123'],
    'demiclab_jaro' => ['label' => 'DemicLab-Jaro', 'password' => 'demicjaro123'],
];

$mode      = $_POST['mode'] ?? '';   // 'manager' | 'oic' | 'cashier'
$branchKey = $_POST['branch'] ?? '';
$step      = 'icons';                 // which panel to (re)open on reload

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_login'])) {
    $password = $_POST['password'] ?? '';

    if ($mode === 'manager') {
        $step = 'password';
        if ($password === '') {
            $error = 'Please enter the manager password.';
        } elseif ($password !== $MANAGER_PASSWORD) {
            $error = 'Incorrect manager password.';
        } else {
            $_SESSION['user_id']      = 'manager';
            $_SESSION['user_name']    = 'Manager';
            $_SESSION['user_role']    = 'manager';
            $_SESSION['user_email']   = 'manager@multiplierscorp.com';
            $_SESSION['user_branch']  = null;
            $_SESSION['user_subrole'] = null;
            $_SESSION['last_activity'] = time();
            header('Location: dashboard.php'); exit;
        }
    } elseif ($mode === 'accounting_head') {
        $step = 'password';
        if ($password === '') {
            $error = 'Please enter the Accounting Head password.';
        } elseif ($password !== $ACCOUNTING_HEAD_PASSWORD) {
            $error = 'Incorrect Accounting Head password.';
        } else {
            $_SESSION['user_id']      = 'accounting_head';
            $_SESSION['user_name']    = 'Accounting Head';
            $_SESSION['user_role']    = 'accounting';
            $_SESSION['user_email']   = 'accounting@multiplierscorp.com';
            $_SESSION['user_branch']  = null;
            $_SESSION['user_subrole'] = null;
            $_SESSION['last_activity'] = time();
            header('Location: dashboard.php'); exit;
        }
    } elseif ($mode === 'oic' || $mode === 'cashier') {
        if (!isset($BRANCHES[$branchKey])) {
            $error = 'Please select a branch.';
            $step  = 'branch';
        } elseif ($password === '') {
            $error = 'Please enter the branch password.';
            $step  = 'password';
        } elseif ($password !== $BRANCHES[$branchKey]['password']) {
            $error = 'Incorrect password for this branch.';
            $step  = 'password';
        } else {
            $b = $BRANCHES[$branchKey];
            $_SESSION['user_id']      = $branchKey . '_' . $mode;
            $_SESSION['user_name']    = $b['label'];
            $_SESSION['user_role']    = 'branch';   // same role model the rest of the app already expects
            $_SESSION['user_email']   = $branchKey . '@multiplierscorp.com';
            $_SESSION['user_branch']  = $b['label'];
            $_SESSION['user_subrole'] = $mode;       // 'oic' = full branch access, 'cashier' = Sales Report only
            $_SESSION['last_activity'] = time();
            header('Location: dashboard.php'); exit;
        }
    } else {
        $error = 'Please choose Manager, Accounting Head, Accounting, or OIC/Cashier.';
        $step  = 'icons';
    }
} elseif ($mode === 'oic' || $mode === 'cashier') {
    $step = $branchKey !== '' ? 'password' : 'branch';
} elseif ($mode === 'manager' || $mode === 'accounting_head') {
    $step = 'password';
}

$branchLabel = $BRANCHES[$branchKey]['label'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — SalesHub</title>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&family=Geist+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root { --ink:#111418; --text:#1c2026; --mut:#6b7280; --line:#e4e7eb; --soft:#f6f7f9; --acc:#0f7b5c; --accbg:#e8f5f0; --bad:#b42318; --badbg:#fef3f2; --f:'Geist',system-ui,-apple-system,'Segoe UI',sans-serif; --m:'Geist Mono',ui-monospace,Menlo,monospace; }
html,body { height:100%; }
body { font-family:var(--f); color:var(--text); background:#fff; -webkit-font-smoothing:antialiased; }
.auth-shell { min-height:100vh; display:grid; place-items:center; background:#fff; padding:24px; }

/* brand (inside the card) */
.brand { display:inline-flex; align-items:center; gap:12px; margin-bottom:34px; text-align:left; }
.brand-icon { width:38px; height:38px; border-radius:10px; background:var(--acc); color:#fff; display:grid; place-items:center; font:700 1.05rem var(--f); }
.brand-name { font:600 .98rem var(--f); letter-spacing:-.01em; color:var(--ink); }
.brand-tag { font:400 .72rem var(--m); color:var(--mut); margin-top:1px; }

/* right: form */
.login-wrap { width:100%; display:flex; justify-content:center; }
.login-card { width:100%; max-width:460px; text-align:center; }
.login-eyebrow { display:none; font:600 .72rem var(--f); letter-spacing:.1em; text-transform:uppercase; color:var(--acc); margin-bottom:10px; }
.login-title { font:600 1.65rem/1.2 var(--f); letter-spacing:-.025em; color:var(--ink); }
.login-title em { font-style:normal; }
.login-sub { margin:8px 0 26px; font:400 .92rem/1.5 var(--f); color:var(--mut); }
.error-box { display:flex; gap:8px; padding:11px 13px; margin:0 0 18px; border:1px solid #fecdca; background:var(--badbg); color:var(--bad); border-radius:10px; font:500 .84rem/1.45 var(--f); }
.step-panel { display:none; }
.step-panel.active { display:block; animation:rise .22s ease both; }
@keyframes rise { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }

/* role list */
.role-icons { display:flex; flex-direction:column; gap:10px; }
.role-icon-btn { display:grid; grid-template-columns:40px 1fr 16px; align-items:center; gap:14px; padding:13px 14px; border:1px solid var(--line); border-radius:12px; background:#fff; cursor:pointer; transition:border-color .15s, background .15s, box-shadow .15s; }
.role-icon-btn:hover { border-color:var(--ink); background:var(--soft); }
.role-icon-btn:focus-visible { outline:2px solid var(--acc); outline-offset:2px; }
.role-icon-glyph { width:40px; height:40px; border-radius:10px; background:var(--soft); color:var(--ink); display:grid; place-items:center; }
.role-icon-btn:hover .role-icon-glyph { background:#fff; }
.role-icon-glyph svg { width:20px; height:20px; stroke:currentColor; fill:none; stroke-width:1.7; stroke-linecap:round; stroke-linejoin:round; }
.role-icon-label { font:600 .95rem var(--f); color:var(--ink); }
.role-icon-sub { font:400 .8rem var(--f); color:var(--mut); margin-top:2px; }
.role-chev { width:16px; height:16px; stroke:#9aa1ab; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; transition:transform .15s, stroke .15s; }
.role-icon-btn:hover .role-chev { stroke:var(--ink); transform:translateX(2px); }

/* branch list */
.branch-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.branch-btn { padding:14px 14px; border:1px solid var(--line); border-radius:12px; font:600 .92rem var(--f); color:var(--ink); cursor:pointer; transition:border-color .15s, background .15s; }
.branch-btn:hover { border-color:var(--ink); background:var(--soft); }

/* back / badge / password */
.back-link { display:inline-flex; align-items:center; gap:6px; margin:0 0 18px; padding:6px 10px 6px 6px; border:0; background:none; color:var(--mut); font:500 .84rem var(--f); border-radius:8px; cursor:pointer; }
.back-link:hover { color:var(--ink); background:var(--soft); }
.context-badge { display:inline-block; margin-bottom:18px; padding:6px 12px; border-radius:999px; background:var(--accbg); color:var(--acc); font:600 .8rem var(--f); }
.field { margin-bottom:16px; }
.field label { display:block; margin-bottom:7px; font:600 .82rem var(--f); color:var(--ink); }
.field input { width:100%; height:46px; padding:0 14px; border:1px solid #cfd4db; border-radius:10px; background:#fff; color:var(--ink); font:500 1rem var(--f); outline:none; transition:border-color .15s, box-shadow .15s; }
.field input::placeholder { color:#b3b9c2; letter-spacing:.2em; }
.field input:focus { border-color:var(--acc); box-shadow:0 0 0 3px rgba(15,123,92,.15); }
.btn-login { width:100%; height:46px; border:0; border-radius:10px; background:var(--ink); color:#fff; font:600 .95rem var(--f); cursor:pointer; transition:background .15s, transform .05s; }
.btn-login:hover { background:#000; }
.btn-login:active { transform:translateY(1px); }
.login-foot { margin-top:26px; font:400 .76rem var(--m); color:#9aa1ab; text-align:center; }

.sel { position:relative; }
.sel select { width:100%; height:46px; padding:0 40px 0 14px; border:1px solid #cfd4db; border-radius:10px; background:#fff; color:var(--ink); font:500 .98rem var(--f); appearance:none; -webkit-appearance:none; outline:none; cursor:pointer; transition:border-color .15s, box-shadow .15s; }
.sel select:focus { border-color:var(--acc); box-shadow:0 0 0 3px rgba(15,123,92,.15); }
.sel::after { content:''; position:absolute; right:16px; top:50%; width:7px; height:7px; border-right:2px solid #8b929c; border-bottom:2px solid #8b929c; transform:translateY(-70%) rotate(45deg); pointer-events:none; }
.btn-login { margin-top:6px; }
.tiles { display:grid; grid-template-columns:1fr 1fr; gap:12px; text-align:center; }
.tile { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; min-height:132px; padding:18px 12px; border:1.5px solid var(--line); border-radius:16px; background:#fff; cursor:pointer; font-family:var(--f); transition:border-color .15s, background .15s, transform .08s; }
.tile:hover { border-color:var(--ink); background:var(--soft); }
.tile:active { transform:scale(.985); }
.tile:focus-visible { outline:2px solid var(--acc); outline-offset:2px; }
.tile-ic { width:44px; height:44px; border-radius:12px; background:var(--soft); color:var(--ink); display:grid; place-items:center; margin-bottom:8px; }
.tile:hover .tile-ic { background:#fff; }
.tile-ic svg { width:22px; height:22px; stroke:currentColor; fill:none; stroke-width:1.7; stroke-linecap:round; stroke-linejoin:round; }
.tile-l { font:600 .95rem var(--f); color:var(--ink); }
.tile-s { font:400 .78rem var(--f); color:var(--mut); }
.tile-branch { min-height:72px; }
.login-card .step-panel[id=panel-password], .login-card .step-panel[id=panel-branch] { text-align:left; }

/* ── light, centered rows (no card, no dark) ── */
body { background:#f6f7f9; color:#1c2026; }
.auth-shell { background:#f6f7f9; }
.login-card { max-width:400px; }
.tiles { grid-template-columns:1fr; gap:10px; }
.tile { flex-direction:row; justify-content:flex-start; gap:14px; min-height:60px; padding:12px 16px; border:1px solid #e1e4e9; background:#fff; text-align:left; border-radius:12px; }
.tile:hover { border-color:#111418; background:#fff; box-shadow:0 2px 10px rgba(16,24,40,.06); }
.tile-ic { margin:0; width:38px; height:38px; flex:none; }
.tile-l { flex:1; text-align:left; }
.tile-branch { min-height:56px; }
@media (max-width:520px) { .auth-shell { padding:16px; } .tile { min-height:116px; } }
</style>
</head>
<body>
<div class="auth-shell">
<main class="login-wrap">
  <div class="login-card">

    <div class="brand">
      <div class="brand-icon">M</div>
      <div>
        <div class="brand-name">Multipliers Corp</div>
        <div class="brand-tag">Meritoni</div>
      </div>
    </div>

    <h1 class="login-title">Sign in as</h1>
    <p class="login-sub">Tap your role to continue.</p>

    <?php if (!$error && isset($_GET['timeout'])): ?>
    <div class="error-box" style="background:#fffbeb;border-color:#fde68a;color:#92400e">⏱ You were signed out after 20 minutes of inactivity. Please sign in again.</div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php" id="loginForm">
      <input type="hidden" name="mode"   id="modeInput"   value="<?= htmlspecialchars($mode) ?>">
      <input type="hidden" name="branch" id="branchInput" value="<?= htmlspecialchars($branchKey) ?>">

      <div class="step-panel" id="panel-icons">
        <div class="tiles">
          <button type="button" class="tile" onclick="chooseMode('manager')">
            <span class="tile-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2M3 13h18"/></svg></span>
            <span class="tile-l">Manager</span>
          </button>
          <button type="button" class="tile" onclick="chooseMode('accounting_head')">
            <span class="tile-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></span>
            <span class="tile-l">Accounting Head</span>
          </button>
          <button type="button" class="tile" onclick="chooseMode('oic')">
            <span class="tile-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="14" r="4"/><path d="M11 11l9-9M16 6l3 3M14 8l2 2"/></svg></span>
            <span class="tile-l">Accounting</span>
          </button>
          <button type="button" class="tile" onclick="chooseMode('cashier')">
            <span class="tile-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg></span>
            <span class="tile-l">OIC/Cashier</span>
          </button>
        </div>
      </div>

      <div class="step-panel" id="panel-branch">
        <button type="button" class="back-link" onclick="goToIcons()">← Back</button>
        <div class="tiles">
          <?php foreach ($BRANCHES as $key => $b): ?>
          <button type="button" class="tile tile-branch" onclick="chooseBranch('<?= $key ?>','<?= htmlspecialchars($b['label'], ENT_QUOTES) ?>')">
            <span class="tile-l"><?= htmlspecialchars($b['label']) ?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="step-panel" id="panel-password">
        <button type="button" class="back-link" id="passwordBackBtn" onclick="goBackFromPassword()">← Back</button>
        <div><span class="context-badge" id="contextBadge">Manager</span></div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password">
        </div>
        <button type="submit" name="submit_login" value="1" class="btn-login">Sign in</button>
      </div>
    </form>

  </div>
</main>
</div>

<script>
function showPanel(id) {
  document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  var T = {'panel-icons':['Sign in as','Tap your role to continue.'],'panel-branch':['Select a branch','Pick the branch you work in.'],'panel-password':['Enter your password','Confirm your identity to continue.']}[id];
  if (T) { document.querySelector('.login-title').textContent = T[0]; document.querySelector('.login-sub').textContent = T[1]; }
}

function chooseMode(mode) {
  document.getElementById('modeInput').value = mode;
  document.getElementById('branchInput').value = '';
  if (mode === 'manager' || mode === 'accounting_head') {
    document.getElementById('contextBadge').textContent = mode === 'manager' ? 'Manager' : 'Accounting Head';
    document.getElementById('passwordBackBtn').onclick = goToIcons;
    showPanel('panel-password');
    document.getElementById('password').focus();
  } else {
    showPanel('panel-branch');
  }
}

function chooseBranch(key, label) {
  document.getElementById('branchInput').value = key;
  const mode = document.getElementById('modeInput').value;
  const modeLabel = mode === 'oic' ? 'Accounting' : 'OIC/Cashier';
  document.getElementById('contextBadge').textContent = label + ' — ' + modeLabel;
  document.getElementById('passwordBackBtn').onclick = goToBranch;
  showPanel('panel-password');
  document.getElementById('password').focus();
}

function goToIcons() {
  document.getElementById('modeInput').value = '';
  document.getElementById('branchInput').value = '';
  showPanel('panel-icons');
}

function goToBranch() { showPanel('panel-branch'); }

function goBackFromPassword() {
  const mode = document.getElementById('modeInput').value;
  if (mode === 'manager' || mode === 'accounting_head') goToIcons();
  else goToBranch();
}

// Restore state after a failed login attempt (server round-trip)
(function initState() {
  const step   = <?= json_encode($step) ?>;
  const mode   = <?= json_encode($mode) ?>;
  const branch = <?= json_encode($branchKey) ?>;
  const branchLabel = <?= json_encode($branchLabel) ?>;

  if (step === 'password') {
    if (mode === 'manager' || mode === 'accounting_head') {
      document.getElementById('contextBadge').textContent = mode === 'manager' ? 'Manager' : 'Accounting Head';
      document.getElementById('passwordBackBtn').onclick = goToIcons;
    } else {
      const modeLabel = mode === 'oic' ? 'Accounting' : 'OIC/Cashier';
      document.getElementById('contextBadge').textContent = branchLabel + ' — ' + modeLabel;
      document.getElementById('passwordBackBtn').onclick = goToBranch;
    }
    showPanel('panel-password');
  } else if (step === 'branch') {
    showPanel('panel-branch');
  } else {
    showPanel('panel-icons');
  }
})();
</script>
</body>
</html>