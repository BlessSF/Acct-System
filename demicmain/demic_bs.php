<?php
// ============================================================
//  demic_bs.php — DemicLab Balance Sheet
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'DemicLab-Main') {
    header('Location: ../dashboard.php');
    exit;
}

$pdo  = getPDO();
$user = currentUser();

// ── Filters ───────────────────────────────────────────────
$stmtDate = $_GET['date'] ?? date('Y-m-d');
// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $stmtDate)) $stmtDate = date('Y-m-d');
$displayDate = date('F d, Y', strtotime($stmtDate));

// ── AJAX: Save ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');

    $fields = [
        // Bank Balances
        'operation_bank_balance','cash_for_depo','revolving_fund',
        // Account Receivables (A/R)
        'hmo_ar','staff_cash_advances','ending_inventory',
        // Payables
        'for_releasing_payment','payables',
        // Depreciation
        'depreciation',
    ];

    $storeNameIn = trim((string)($_POST['store_name'] ?? ''));
    if ($storeNameIn === '') $storeNameIn = 'DemicLab-Main';
    $storeNameIn = substr($storeNameIn, 0, 100);

    $displayNameIn = trim((string)($_POST['display_name'] ?? $storeNameIn));
    if ($displayNameIn === '') $displayNameIn = $storeNameIn;
    $displayNameIn = substr($displayNameIn, 0, 150);

    $data = [
        'store_name'   => $storeNameIn,
        'display_name' => $displayNameIn,
        'stmt_date'    => $_POST['stmt_date'] ?? $stmtDate,
        'stmt_year'    => (int)date('Y', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'stmt_month'   => (int)date('n', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'stmt_day'     => (int)date('j', strtotime($_POST['stmt_date'] ?? $stmtDate)),
        'saved_by'     => $user['name'],
    ];
    foreach ($fields as $f) $data[$f] = (float)($_POST[$f] ?? 0);

    $cols      = array_keys($data);
    $dupUpdate = implode(',', array_map(fn($c) => "`$c`=VALUES(`$c`)", $cols));

    try {
        $sql = "INSERT INTO demic_balance_sheet ("
             . implode(',', array_map(fn($c) => "`$c`", $cols))
             . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")"
             . " ON DUPLICATE KEY UPDATE $dupUpdate";
        $pdo->prepare($sql)->execute(array_values($data));
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── Load saved data for ALL branch sections on this date ──
$sections = []; // store_name => row (or null)
try {
    $stmt = $pdo->prepare("SELECT * FROM demic_balance_sheet WHERE stmt_date=?");
    $stmt->execute([$stmtDate]);
    foreach ($stmt->fetchAll() as $r) {
        $sections[$r['store_name']] = $r;
    }
} catch (Throwable $ignored) {}

// Primary branch always shown, even with no data yet
if (!array_key_exists('DemicLab-Main', $sections)) $sections['DemicLab-Main'] = null;

// Primary branch first, remaining branches alphabetically by label
$orderedStores = array_keys($sections);
usort($orderedStores, function ($a, $b) use ($sections) {
    if ($a === 'DemicLab-Main') return -1;
    if ($b === 'DemicLab-Main') return 1;
    $labelA = $sections[$a]['display_name'] ?? $a;
    $labelB = $sections[$b]['display_name'] ?? $b;
    return strcasecmp($labelA, $labelB);
});

function bs_suffix(string $storeName): string {
    return trim(preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower($storeName)), '_');
}

// Renders one balance-sheet card. $storeKey is the stable, unique record
// identifier (never shown, never duplicated — used to save/update the right
// DB row). $label is the human-readable branch name shown in the header;
// multiple sections may share the same label. Pass $isNew=true to make the
// label editable and include a remove (✕) control (used only for the
// client-side "add section" template).
function render_bs_card(string $storeKey, string $label, string $suffix, ?array $row, string $year, string $dateDisplay, bool $isNew = false): string {
    $v = fn($k) => $row ? number_format((float)($row[$k] ?? 0), 2, '.', '') : '0.00';
    // Zero/empty values render as an empty field with a faint "0.00" placeholder
    // instead of an actual 0.00, so the user can type straight in without
    // deleting a 0 first.
    $vattr = function ($k) use ($v) {
        $val = $v($k);
        return ($val === '0.00') ? 'placeholder="0.00"' : ('value="' . $val . '"');
    };
    ob_start();
    ?>
    <div class="is-card" data-section="<?= $suffix ?>" data-store="<?= htmlspecialchars($storeKey, ENT_QUOTES) ?>" data-label="<?= htmlspecialchars($label, ENT_QUOTES) ?>">
      <div class="is-header">
        <div class="is-header-title">
          <?php if ($isNew): ?>
          <input type="text" class="is-storename-input" value="<?= htmlspecialchars($label, ENT_QUOTES) ?>"
                 oninput="onStoreNameInput(this)" placeholder="Branch name">
          <span class="is-remove-btn" onclick="removeSection('<?= $suffix ?>')" title="Remove section">✕</span>
          <?php else: ?>
          <?= htmlspecialchars($label) ?> Branch
          <?php endif; ?>
        </div>
        <div class="is-header-main">BALANCE SHEET <?= $year ?></div>
        <div class="is-header-date"><?= $dateDisplay ?></div>
      </div>
      <div class="is-body">

        <div class="is-section">Bank Balances</div>
        <?php foreach ([
          ['operation_bank_balance', 'Operation Bank Balance'],
          ['cash_for_depo',          'Cash for Depo'],
          ['revolving_fund',         'Revolving Fund / Petty Cash'],
        ] as [$field, $fieldLabel]): ?>
        <div class="is-row">
          <div class="is-label"><?= $fieldLabel ?></div>
          <div class="is-input-wrap">
            <input type="number" step="0.01" class="is-input" id="<?= $field ?>__<?= $suffix ?>" data-field="<?= $field ?>"
                   <?= $vattr($field) ?> oninput="recalcSection('<?= $suffix ?>')">
          </div>
        </div>
        <?php endforeach; ?>
        <div class="is-total underline">
          <div class="is-total-label">Total</div>
          <div class="is-total-val" id="tot_bank__<?= $suffix ?>">0.00</div>
        </div>

        <div class="is-section">Account Receivables (A/R)</div>
        <?php foreach ([
          ['hmo_ar',              'HMO'],
          ['staff_cash_advances', 'Staff Cash Advances'],
          ['ending_inventory',    'Ending Inventory'],
        ] as [$field, $fieldLabel]): ?>
        <div class="is-row">
          <div class="is-label"><?= $fieldLabel ?></div>
          <div class="is-input-wrap">
            <input type="number" step="0.01" class="is-input" id="<?= $field ?>__<?= $suffix ?>" data-field="<?= $field ?>"
                   <?= $vattr($field) ?> oninput="recalcSection('<?= $suffix ?>')">
          </div>
        </div>
        <?php endforeach; ?>
        <div class="is-total underline">
          <div class="is-total-label">Total</div>
          <div class="is-total-val" id="tot_ar__<?= $suffix ?>">0.00</div>
        </div>

        <div class="is-section">Payables</div>
        <?php foreach ([
          ['for_releasing_payment', 'For Releasing Payment'],
          ['payables',              'Payables'],
        ] as [$field, $fieldLabel]): ?>
        <div class="is-row">
          <div class="is-label"><?= $fieldLabel ?></div>
          <div class="is-input-wrap">
            <input type="number" step="0.01" class="is-input" id="<?= $field ?>__<?= $suffix ?>" data-field="<?= $field ?>"
                   <?= $vattr($field) ?> oninput="recalcSection('<?= $suffix ?>')">
          </div>
        </div>
        <?php endforeach; ?>
        <div class="is-total underline">
          <div class="is-total-label">Total</div>
          <div class="is-total-val" id="tot_payables__<?= $suffix ?>">0.00</div>
        </div>

        <div class="is-section">Depreciation</div>
        <div class="is-row">
          <div class="is-label">Depreciation</div>
          <div class="is-input-wrap">
            <input type="number" step="0.01" class="is-input" id="depreciation__<?= $suffix ?>" data-field="depreciation"
                   <?= $vattr('depreciation') ?> oninput="recalcSection('<?= $suffix ?>')">
          </div>
        </div>

        <div class="is-total is-net">
          <div class="is-total-label">Total Profit</div>
          <div class="is-total-val" id="tot_profit__<?= $suffix ?>">0.00</div>
        </div>

        <div class="is-card-footer">
          <button class="btn btn-primary btn-sm is-save-btn"
                  onclick="saveSection('<?= $suffix ?>')">
            💾 Save <?= htmlspecialchars($label) ?>
          </button>
          <span class="is-save-status" style="font-family:var(--font-m);font-size:.66rem;color:var(--subtext)"></span>
        </div>

      </div><!-- /is-body -->
    </div><!-- /is-card -->
    <?php
    return ob_get_clean();
}

// ── Comparison data: last 6 saved balance sheets, oldest → newest ──
$compareRows = [];
try {
    $cmpStmt = $pdo->prepare("SELECT * FROM demic_balance_sheet WHERE store_name='DemicLab-Main' ORDER BY stmt_date DESC LIMIT 6");
    $cmpStmt->execute();
    $compareRows = array_reverse($cmpStmt->fetchAll());
} catch (Throwable $ignored) {}

$cf = fn($row, $k) => number_format((float)($row[$k] ?? 0), 2, '.', ',');

$pageTitle  = 'Balance Sheet';
$activePage = 'demic_bs';
include '../layout.php';
?>

<style>
.is-wrap { max-width: 620px; margin: 0 auto; }
.is-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 16px;
}
.is-header {
  background: #1e3060;
  color: #fff;
  padding: 12px 20px 10px;
}
.is-header-title {
  font-family: var(--font-m);
  font-size: .56rem;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: rgba(255,255,255,.5);
  margin-bottom: 2px;
}
.is-header-main {
  font-size: .95rem;
  font-weight: 700;
  letter-spacing: -.02em;
  color: #fff;
}
.is-header-date {
  font-family: var(--font-m);
  font-size: .64rem;
  color: rgba(255,255,255,.6);
  margin-top: 2px;
}
.is-body { padding: 0; }

/* Section heading row */
.is-section {
  background: #f0f2f5;
  padding: 5px 20px;
  font-family: var(--font-m);
  font-size: .56rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--subtext2);
  border-top: 1px solid var(--border);
}

/* Row */
.is-row {
  display: flex;
  align-items: center;
  padding: 0 20px 0 32px;
  border-bottom: 1px solid #f4f5f7;
  min-height: 30px;
  transition: background .1s;
}
.is-row:hover { background: #fafbfc; }
.is-label {
  flex: 1;
  font-size: .72rem;
  color: var(--text);
  padding: 4px 0;
}
.is-input-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 120px;
  justify-content: flex-end;
}
.is-input {
  width: 108px;
  padding: 3px 8px;
  text-align: right;
  font-family: var(--font-m);
  font-size: .72rem;
  color: var(--text);
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 5px;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.is-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(15,123,92,.08);
}
.is-pct {
  font-family: var(--font-m);
  font-size: .64rem;
  color: var(--subtext);
  width: 32px;
  text-align: right;
}
.is-pct.red  { color: var(--accent2); font-weight: 600; }

/* Total rows */
.is-total {
  display: flex;
  align-items: center;
  padding: 6px 20px 6px 32px;
  background: #f8f9fb;
  border-top: 2px solid var(--border2);
  border-bottom: 1px solid var(--border);
}
.is-total.underline { border-bottom: 2px solid var(--border2); }
.is-total-label {
  flex: 1;
  font-family: var(--font-m);
  font-size: .64rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--subtext2);
}
.is-total-val {
  font-family: var(--font-m);
  font-size: .78rem;
  font-weight: 800;
  color: var(--text);
  min-width: 108px;
  text-align: right;
}
.is-total-pct {
  font-family: var(--font-m);
  font-size: .64rem;
  font-weight: 700;
  width: 32px;
  text-align: right;
  margin-left: 6px;
}

/* Total Profit highlight */
.is-net {
  background: #fffbeb;
  border-top: 2px solid var(--accent3) !important;
}
.is-net .is-total-label { color: var(--accent3); font-size: .72rem; }
.is-net .is-total-val   { color: var(--accent3); font-size: .88rem; }

/* Multi-branch section grid */
.is-sections-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: flex-start;
}
.is-sections-grid .is-card {
  flex: 1 1 340px;
  max-width: 400px;
  margin-bottom: 0;
}
.is-card-footer {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #f8f9fb;
  border-top: 1px solid var(--border);
}
.is-remove-btn {
  cursor: pointer;
  color: rgba(255,255,255,.6);
  font-size: .7rem;
  margin-left: 8px;
  padding: 1px 6px;
  border-radius: 4px;
}
.is-remove-btn:hover { color: #fff; background: rgba(255,255,255,.15); }
.is-storename-input {
  background: rgba(255,255,255,.08);
  border: none;
  border-bottom: 1px solid rgba(255,255,255,.35);
  color: #fff;
  font-family: inherit;
  font-size: .68rem;
  letter-spacing: .04em;
  text-transform: none;
  padding: 1px 5px;
  border-radius: 3px;
  outline: none;
  width: 150px;
  vertical-align: middle;
}
.is-storename-input:focus { border-bottom-color: #fff; background: rgba(255,255,255,.15); }
.is-storename-input::placeholder { color: rgba(255,255,255,.4); }
.is-storename-input.is-duplicate { border-bottom-color: #ff8a8a; background: rgba(255,80,80,.18); }
.is-save-btn:disabled { opacity: .5; cursor: not-allowed; }

/* Controls */
.is-controls {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.flash.toast {
  position: fixed; top: 68px; right: 22px;
  z-index: 9999; max-width: 320px;
  animation: fadeSlideDown .3s ease;
}

/* Comparison table */
.bs-compare-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 16px;
}
.bs-compare-head {
  background: #1e3a5f;
  padding: 10px 18px;
  font-size: .8rem;
  font-weight: 700;
  color: #fff;
  letter-spacing: -.01em;
}
.bs-compare-scroll { overflow-x: auto; }
.bs-compare-table { border-collapse: collapse; width: max-content; min-width: 100%; font-family: var(--font-m); }
.bs-compare-table th, .bs-compare-table td {
  padding: 6px 12px;
  border: 1px solid #e3e6ea;
  white-space: nowrap;
  font-size: .68rem;
}
.bs-compare-table thead th {
  background: #1e3a5f;
  color: #fff;
  text-align: center;
  font-size: .6rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  min-width: 100px;
}
.bs-compare-table td.bs-label {
  text-align: left;
  font-weight: 600;
  color: var(--subtext2);
  background: #f8f9fb;
  position: sticky; left: 0;
}
.bs-compare-table td.bs-val { text-align: right; }
.bs-compare-table tr.bs-section-row td {
  background: #f0f2f5;
  font-weight: 700;
  text-transform: uppercase;
  font-size: .58rem;
  letter-spacing: .08em;
  color: var(--subtext2);
}
.bs-compare-table tr.bs-subtotal-row td {
  background: #eef1f5;
  font-weight: 700;
}
.bs-compare-table tr.bs-profit-row td {
  background: #fffbeb;
  font-weight: 800;
  color: var(--accent3);
}
</style>

<!-- Controls -->
<div class="is-controls">
  <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="date" name="date" class="form-control" style="max-width:180px"
           value="<?= htmlspecialchars($stmtDate) ?>" onchange="this.form.submit()">
  </form>
  <button class="btn btn-primary" onclick="saveAllSections()">💾 Save All</button>
  <button class="btn btn-ghost" onclick="addSection()">➕ Add Branch Section</button>
  <span id="saveStatus" style="font-family:var(--font-m);font-size:.72rem;color:var(--subtext)"></span>
  <a href="demiclab_income_statement.php?date=<?= htmlspecialchars($stmtDate) ?>"
     class="btn btn-ghost btn-sm" style="margin-left:auto">← Income Statement</a>
</div>

<?php if (count($compareRows) > 1): ?>
<div class="is-wrap" style="max-width:100%">
  <div class="bs-compare-card">
    <div class="bs-compare-head">📊 Monthly Comparison — Last <?= count($compareRows) ?> Entries</div>
    <div class="bs-compare-scroll">
      <table class="bs-compare-table">
        <thead>
          <tr>
            <th style="text-align:left;position:sticky;left:0;background:#1e3a5f">&nbsp;</th>
            <?php foreach ($compareRows as $cr): ?>
            <th><?= date('M j, Y', strtotime($cr['stmt_date'])) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <tr class="bs-section-row"><td class="bs-label">Bank Balances</td><?php foreach ($compareRows as $cr): ?><td></td><?php endforeach; ?></tr>
          <?php foreach ([
            ['operation_bank_balance','Operation Bank Balance'],
            ['cash_for_depo','Cash for Depo'],
            ['revolving_fund','Revolving Fund / Petty Cash'],
          ] as [$f, $label]): ?>
          <tr>
            <td class="bs-label"><?= $label ?></td>
            <?php foreach ($compareRows as $cr): ?><td class="bs-val"><?= $cf($cr, $f) ?></td><?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
          <tr class="bs-subtotal-row">
            <td class="bs-label">Total</td>
            <?php foreach ($compareRows as $cr): ?>
            <td class="bs-val"><?= number_format($cr['operation_bank_balance'] + $cr['cash_for_depo'] + $cr['revolving_fund'], 2) ?></td>
            <?php endforeach; ?>
          </tr>

          <tr class="bs-section-row"><td class="bs-label">Account Receivables (A/R)</td><?php foreach ($compareRows as $cr): ?><td></td><?php endforeach; ?></tr>
          <?php foreach ([
            ['hmo_ar','HMO'],
            ['staff_cash_advances','Staff Cash Advances'],
            ['ending_inventory','Ending Inventory'],
          ] as [$f, $label]): ?>
          <tr>
            <td class="bs-label"><?= $label ?></td>
            <?php foreach ($compareRows as $cr): ?><td class="bs-val"><?= $cf($cr, $f) ?></td><?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
          <tr class="bs-subtotal-row">
            <td class="bs-label">Total</td>
            <?php foreach ($compareRows as $cr): ?>
            <td class="bs-val"><?= number_format($cr['hmo_ar'] + $cr['staff_cash_advances'] + $cr['ending_inventory'], 2) ?></td>
            <?php endforeach; ?>
          </tr>

          <tr class="bs-section-row"><td class="bs-label">Payables</td><?php foreach ($compareRows as $cr): ?><td></td><?php endforeach; ?></tr>
          <?php foreach ([
            ['for_releasing_payment','For Releasing Payment'],
            ['payables','Payables'],
          ] as [$f, $label]): ?>
          <tr>
            <td class="bs-label"><?= $label ?></td>
            <?php foreach ($compareRows as $cr): ?><td class="bs-val"><?= $cf($cr, $f) ?></td><?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
          <tr class="bs-subtotal-row">
            <td class="bs-label">Total</td>
            <?php foreach ($compareRows as $cr): ?>
            <td class="bs-val"><?= number_format($cr['for_releasing_payment'] + $cr['payables'], 2) ?></td>
            <?php endforeach; ?>
          </tr>

          <tr>
            <td class="bs-label">Depreciation</td>
            <?php foreach ($compareRows as $cr): ?><td class="bs-val"><?= $cf($cr, 'depreciation') ?></td><?php endforeach; ?>
          </tr>

          <tr class="bs-profit-row">
            <td class="bs-label">Total Profit</td>
            <?php foreach ($compareRows as $cr):
              $bank = $cr['operation_bank_balance'] + $cr['cash_for_depo'] + $cr['revolving_fund'];
              $ar   = $cr['hmo_ar'] + $cr['staff_cash_advances'] + $cr['ending_inventory'];
              $pay  = $cr['for_releasing_payment'] + $cr['payables'];
              $profit = $bank + $ar - $pay - $cr['depreciation'];
            ?>
            <td class="bs-val"><?= number_format($profit, 2) ?></td>
            <?php endforeach; ?>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="is-wrap" style="max-width:100%">
  <div class="is-sections-grid" id="bs-sections">
    <?php
    $bsYear = date('Y', strtotime($stmtDate));
    foreach ($orderedStores as $storeName):
        $suffix = bs_suffix($storeName);
        $row    = $sections[$storeName];
        $label  = $row['display_name'] ?? $storeName;
        echo render_bs_card($storeName, $label, $suffix, $row, $bsYear, $displayDate, false);
    endforeach;
    ?>
  </div>
</div>

<!-- Hidden template used by addSection() to create a new branch card -->
<template id="bs-section-template"><?= render_bs_card('__STORE_KEY__', '__STORE_LABEL__', '__SUFFIX__', null, $bsYear, $displayDate, true) ?></template>

<script>
function gv(id) { return parseFloat(document.getElementById(id)?.value) || 0; }
function fmt(n)  { return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function getSectionSuffixes() {
  return Array.from(document.querySelectorAll('.is-card[data-section]')).map(el => el.dataset.section);
}

function recalcSection(suffix) {
  const bankFields = ['operation_bank_balance','cash_for_depo','revolving_fund'];
  let bankTotal = 0;
  bankFields.forEach(f => bankTotal += gv(f + '__' + suffix));
  document.getElementById('tot_bank__' + suffix).textContent = fmt(bankTotal);

  const arFields = ['hmo_ar','staff_cash_advances','ending_inventory'];
  let arTotal = 0;
  arFields.forEach(f => arTotal += gv(f + '__' + suffix));
  document.getElementById('tot_ar__' + suffix).textContent = fmt(arTotal);

  const payFields = ['for_releasing_payment','payables'];
  let payTotal = 0;
  payFields.forEach(f => payTotal += gv(f + '__' + suffix));
  document.getElementById('tot_payables__' + suffix).textContent = fmt(payTotal);

  const depreciation = gv('depreciation__' + suffix);

  const totalProfit = bankTotal + arTotal - payTotal - depreciation;
  const el = document.getElementById('tot_profit__' + suffix);
  el.textContent = fmt(totalProfit);
  el.style.color = totalProfit >= 0 ? 'var(--accent3)' : 'var(--accent2)';
}

function recalcAll() {
  getSectionSuffixes().forEach(recalcSection);
}

async function saveSection(suffix) {
  const card = document.querySelector('.is-card[data-section="' + suffix + '"]');
  if (!card) return;

  const storeKey = (card.dataset.store || '').trim();
  const label    = (card.dataset.label || storeKey || 'Branch').trim();
  if (!storeKey) return;

  const btn    = card.querySelector('.is-save-btn');
  const status = card.querySelector('.is-save-status');
  if (btn) { btn.textContent = '…'; btn.disabled = true; }

  const fd = new FormData();
  fd.append('ajax_save', '1');
  fd.append('stmt_date', <?= json_encode($stmtDate) ?>);
  fd.append('store_name', storeKey);
  fd.append('display_name', label);

  card.querySelectorAll('.is-input').forEach(el => {
    fd.append(el.dataset.field, el.value || '0');
  });

  try {
    const res  = await fetch('demic_bs.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.ok) {
      if (status) { status.textContent = '✓ Saved'; status.style.color = 'var(--accent)'; }
      showToast('✓ ' + label + ' balance sheet saved', 'success');
    } else {
      showToast('❌ ' + data.msg, 'error');
      if (status) status.textContent = '❌ Error';
    }
  } catch (e) {
    showToast('❌ Network error', 'error');
  }
  if (btn) { btn.textContent = '💾 Save ' + label; btn.disabled = false; }
  if (status) setTimeout(() => { status.textContent = ''; }, 4000);
}

async function saveAllSections() {
  const status = document.getElementById('saveStatus');
  status.textContent = 'Saving…';
  for (const suffix of getSectionSuffixes()) {
    await saveSection(suffix);
  }
  status.textContent = '✓ All sections saved';
  status.style.color = 'var(--accent)';
  setTimeout(() => { status.textContent = ''; }, 4000);
}

let newSectionCounter = 0;

function onStoreNameInput(inputEl) {
  const card  = inputEl.closest('.is-card');
  const label = inputEl.value.trim() || 'New Branch';
  card.dataset.label = label; // display name only — the hidden storeKey (data-store) never changes
  const saveBtn = card.querySelector('.is-save-btn');
  if (saveBtn) saveBtn.textContent = '💾 Save ' + label;
}

function addSection() {
  newSectionCounter++;
  const defaultLabel = 'New Branch ' + newSectionCounter;
  // storeKey is a hidden, auto-generated unique ID — it is what's actually
  // saved to the DB, so duplicate labels never collide.
  const storeKey = 'branch_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
  const suffix   = 'new_' + newSectionCounter + '_' + Date.now().toString(36);

  const tpl  = document.getElementById('bs-section-template').innerHTML;
  const html = tpl
    .split('__SUFFIX__').join(suffix)
    .split('__STORE_KEY__').join(storeKey)
    .split('__STORE_LABEL__').join(defaultLabel);

  const wrap = document.createElement('div');
  wrap.innerHTML = html.trim();
  document.getElementById('bs-sections').appendChild(wrap.firstElementChild);

  recalcSection(suffix);

  const nameInput = document.querySelector('.is-card[data-section="' + suffix + '"] .is-storename-input');
  if (nameInput) { nameInput.focus(); nameInput.select(); }
}

function removeSection(suffix) {
  const card = document.querySelector('.is-card[data-section="' + suffix + '"]');
  if (card && confirm('Remove this section? Unsaved changes will be lost.')) {
    card.remove();
  }
}

function showToast(msg, type) {
  const t = document.createElement('div');
  t.className = 'flash flash-'+(type||'success')+' toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

document.addEventListener('DOMContentLoaded', recalcAll);
</script>
</body>
</html>