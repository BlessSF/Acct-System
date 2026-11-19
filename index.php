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
.auth-shell { min-height:100vh; display:grid; grid-template-columns:minmax(320px,44%) 1fr; }

/* left: brand */
.login-side { background:var(--ink); color:#e8eaed; padding:44px 48px; display:flex; flex-direction:column; justify-content:space-between; position:relative; overflow:hidden; }
.login-side::after { content:''; position:absolute; inset:0; background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px); background-size:44px 44px; mask-image:linear-gradient(180deg,transparent,#000 40%,#000); pointer-events:none; }
.brand { display:flex; align-items:center; gap:12px; position:relative; z-index:1; }
.brand-icon { width:36px; height:36px; border-radius:9px; background:var(--acc); color:#fff; display:grid; place-items:center; font:700 1rem var(--f); }
.brand-name { font:600 .95rem var(--f); letter-spacing:-.01em; }
.brand-tag { font:400 .72rem var(--m); color:#8b929c; margin-top:1px; }
.side-copy { position:relative; z-index:1; max-width:360px; }
.side-copy h2 { font:600 1.7rem/1.2 var(--f); letter-spacing:-.025em; color:#fff; }
.side-copy p { margin-top:12px; font:400 .9rem/1.6 var(--f); color:#9aa1ab; }
.side-foot { position:relative; z-index:1; font:400 .72rem var(--m); color:#6b727d; }

/* right: form */
.login-wrap { display:flex; align-items:center; justify-content:center; padding:40px 24px; background:#fff; }
.login-card { width:100%; max-width:400px; }
.login-eyebrow { font:600 .72rem var(--f); letter-spacing:.1em; text-transform:uppercase; color:var(--acc); margin-bottom:10px; }
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

@media (max-width:860px) {
  .auth-shell { grid-template-columns:1fr; }
  .login-side { padding:22px 24px; flex-direction:row; align-items:center; }
  .side-copy, .side-foot { display:none; }
  .login-side::after { display:none; }
  .login-wrap { align-items:flex-start; padding:32px 20px; }
}
</style>
</head>
<body>
<div class="auth-shell">
<aside class="login-side">
  <div class="brand">
    <div class="brand-icon">M</div>
    <div>
      <div class="brand-name">Multipliers Corp</div>
      <div class="brand-tag">Meritoni</div>
    </div>
  </div>
  <div class="side-copy">
    <h2>Sales, inventory and books for every branch.</h2>
    <p>Sign in with your role to continue to the daily reports.</p>
  </div>
  <div class="side-foot">SalesHub</div>
</aside>

<main class="login-wrap">
  <div class="login-card">

    <div class="login-eyebrow">Sign in</div>
    <h1 class="login-title">Choose your role</h1>
    <p class="login-sub">Select how you're signing in to continue.</p>

    <?php if (!$error && isset($_GET['timeout'])): ?>
    <div class="error-box" style="background:#fffbeb;border-color:#fde68a;color:#92400e">⏱ You were signed out after 20 minutes of inactivity. Please sign in again.</div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php" id="loginForm">
      <input type="hidden" name="mode"   id="modeInput"   value="<?= htmlspecialchars($mode) ?>">
      <input type="hidden" name="branch" id="branchInput" value="<?= htmlspecialchars($branchKey) ?>">

      <!-- STEP 1: role -->
      <div class="step-panel" id="panel-icons">
        <div class="role-icons">
          <div class="role-icon-btn" role="button" tabindex="0" onclick="chooseMode('manager')" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();chooseMode('manager')}">
            <div class="role-icon-glyph"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2M3 13h18"/></svg></div>
            <div><div class="role-icon-label">Manager</div><div class="role-icon-sub">Full access</div></div>
            <svg class="role-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
          </div>
          <div class="role-icon-btn" role="button" tabindex="0" onclick="chooseMode('accounting_head')" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();chooseMode('accounting_head')}">
            <div class="role-icon-glyph"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></div>
            <div><div class="role-icon-label">Accounting Head</div><div class="role-icon-sub">All branches, financial oversight</div></div>
            <svg class="role-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
          </div>
          <div class="role-icon-btn" role="button" tabindex="0" onclick="chooseMode('oic')" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();chooseMode('oic')}">
            <div class="role-icon-glyph"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="14" r="4"/><path d="M11 11l9-9M16 6l3 3M14 8l2 2"/></svg></div>
            <div><div class="role-icon-label">Accounting</div><div class="role-icon-sub">Pick a branch</div></div>
            <svg class="role-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
          </div>
          <div class="role-icon-btn" role="button" tabindex="0" onclick="chooseMode('cashier')" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();chooseMode('cashier')}">
            <div class="role-icon-glyph"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg></div>
            <div><div class="role-icon-label">OIC/Cashier</div><div class="role-icon-sub">Dashboard &amp; Sales Report</div></div>
            <svg class="role-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
          </div>
        </div>
      </div>

      <!-- STEP 2 (Accounting / OIC-Cashier): pick a branch -->
      <div class="step-panel" id="panel-branch">
        <button type="button" class="back-link" onclick="goToIcons()">← Back</button>
        <div class="branch-grid">
          <?php foreach ($BRANCHES as $key => $b): ?>
          <div class="branch-btn" onclick="chooseBranch('<?= $key ?>','<?= htmlspecialchars($b['label'], ENT_QUOTES) ?>')">
            <?= htmlspecialchars($b['label']) ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- STEP 3: password -->
      <div class="step-panel" id="panel-password">
        <button type="button" class="back-link" id="passwordBackBtn" onclick="goBackFromPassword()">← Back</button>
        <div><span class="context-badge" id="contextBadge">Manager</span></div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password"
                 placeholder="••••••••"
                 autocomplete="current-password">
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
  var T = {'panel-icons':['Choose your role',"Select how you're signing in to continue."],'panel-branch':['Select a branch','Pick the branch you work in.'],'panel-password':['Enter your password','Confirm your identity to continue.']}[id];
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