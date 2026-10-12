<?php
// ============================================================
//  commissary_print_check.php — Print a single check
//  Pulls a row from commissary_ap_released_checks (or
//  commissary_ap_payables) and lays out PAY TO THE ORDER OF /
//  PESOS (amount in words) / DATE / AMOUNT (₱ box) so it can be
//  printed directly onto pre-printed Metrobank AccountOne check
//  stock. Calibration offsets are adjustable on screen and saved
//  per-browser (localStorage) so alignment only needs to be
//  tuned once for a given printer.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();

if (isBranch() && currentBranch() !== 'Commissary') {
    http_response_code(403); exit('Access denied.');
}

$pdo = getPDO();

$tbl   = ($_GET['tbl'] ?? 'released') === 'payables' ? 'payables' : 'released';
$table = $tbl === 'payables' ? 'commissary_ap_payables' : 'commissary_ap_released_checks';
$id    = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id=?");
$stmt->execute([$id]);
$check = $stmt->fetch();
if (!$check) { http_response_code(404); exit('Check row not found.'); }

$bankLabel = 'PNB-' . ($check['bank_account'] ?: '2424');
$vendor    = $check['vendor_name'] ?? '';
$amount    = (float)($check['amount'] ?? 0);
$dateVal   = $check['entry_date'] ?? date('Y-m-d');

// ── Number-to-words (Philippine peso) ───────────────────────
function numberToWords($number) {
    $number = (int)$number;
    if ($number === 0) return 'Zero';
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten',
              'Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $scales = ['', ' Thousand', ' Million', ' Billion'];

    $convertHundreds = function($n) use ($ones, $tens) {
        $str = '';
        if ($n >= 100) { $str .= $ones[intdiv($n,100)] . ' Hundred '; $n %= 100; }
        if ($n >= 20)  { $str .= $tens[intdiv($n,10)] . ' '; $n %= 10; if ($n > 0) $str .= $ones[$n] . ' '; }
        elseif ($n > 0) { $str .= $ones[$n] . ' '; }
        return $str;
    };

    $groups = [];
    while ($number > 0) { $groups[] = $number % 1000; $number = intdiv($number, 1000); }

    $result = '';
    for ($i = count($groups) - 1; $i >= 0; $i--) {
        if ($groups[$i] > 0) $result .= $convertHundreds($groups[$i]) . $scales[$i] . ' ';
    }
    return trim(preg_replace('/\s+/', ' ', $result));
}

function amountToPesoWords($amount) {
    $amount   = round((float)$amount, 2);
    $pesos    = (int)floor($amount);
    $centavos = (int)round(($amount - $pesos) * 100);
    return numberToWords($pesos) . ' Pesos and ' . str_pad((string)$centavos, 2, '0', STR_PAD_LEFT) . '/100 Only';
}

$amountWords  = amountToPesoWords($amount);
$amountNumber = number_format($amount, 2);
$dt           = $dateVal ? strtotime($dateVal) : time();
$dateMM = date('m', $dt); $dateDD = date('d', $dt); $dateYYYY = date('Y', $dt);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Print Check — <?=htmlspecialchars($vendor)?></title>
<style>
  * { box-sizing:border-box; }
  body { font-family:Arial,Helvetica,sans-serif; margin:0; background:#e5e7eb; }

  /* ── Calibration panel (hidden when printing) ── */
  .panel {
    max-width:720px; margin:18px auto; background:#fff; border:1px solid #d1d5db; border-radius:10px;
    padding:16px 20px; box-shadow:0 1px 4px rgba(0,0,0,.08);
  }
  .panel h1 { font-size:1rem; margin:0 0 4px; }
  .panel .sub { font-size:.78rem; color:#6b7280; margin-bottom:14px; }
  .cal-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:10px 16px; margin-bottom:14px; }
  .cal-field { display:flex; flex-direction:column; gap:3px; }
  .cal-field label { font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; font-weight:600; }
  .cal-field input, .cal-field select { padding:6px 8px; border:1px solid #d1d5db; border-radius:6px; font-size:.8rem; width:100%; background:#fff; }
  .panel-actions { display:flex; gap:10px; flex-wrap:wrap; }
  .btn { padding:9px 16px; border-radius:8px; border:none; font-size:.82rem; font-weight:700; cursor:pointer; }
  .btn-primary { background:#0f7b5c; color:#fff; }
  .btn-primary:hover { background:#0a6649; }
  .btn-secondary { background:#eef2f7; color:#1a1d23; }
  .btn-secondary:hover { background:#e2e8f0; }
  .note { font-size:.72rem; color:#9ca3af; margin-top:10px; line-height:1.5; }

  /* ── Check sheet ── */
  .sheet-outer { max-width:900px; margin:0 auto 40px; padding:0 18px; }
  .check-sheet {
    position:relative; background:#fff; border:1px dashed #9ca3af;
    width:8.5in; height:3.5in; margin:0 auto;
  }
  .fld { position:absolute; font-family:'Courier New',monospace; font-size:13px; color:#111; white-space:nowrap; }
  .date-gap { display:inline-block; }

  @media print {
    body { background:#fff; }
    .panel { display:none; }
    .sheet-outer { margin:0; padding:0; }
    .check-sheet { border:none; margin:0; }
    @page { size:8.5in 3.5in; margin:0; }
  }
</style>
</head>
<body>

  <div class="panel">
    <h1>Print Check — <?=htmlspecialchars($vendor)?> (<?=htmlspecialchars($bankLabel)?>)</h1>
    <div class="sub">Amount: ₱<?=$amountNumber?> · Adjust the offsets below until the preview lines up with a blank check held against your screen, then Print. Values are saved in this browser for next time.</div>

    <div class="cal-field" style="max-width:260px;margin-bottom:14px;">
      <label>Check Stock</label>
      <select id="bank-select">
        <option value="metrobank">Metrobank (AccountOne)</option>
        <option value="pbb">PBB — Philippine Business Bank</option>
      </select>
    </div>

    <div class="cal-grid">
      <div class="cal-field"><label>Pay To — X (mm)</label><input type="number" step="0.5" id="cal-payto-x"></div>
      <div class="cal-field"><label>Pay To — Y (mm)</label><input type="number" step="0.5" id="cal-payto-y"></div>
      <div class="cal-field"><label>Pesos — X (mm)</label><input type="number" step="0.5" id="cal-pesos-x"></div>
      <div class="cal-field"><label>Pesos — Y (mm)</label><input type="number" step="0.5" id="cal-pesos-y"></div>
      <div class="cal-field"><label>Amount Box — X (mm)</label><input type="number" step="0.5" id="cal-amt-x"></div>
      <div class="cal-field"><label>Amount Box — Y (mm)</label><input type="number" step="0.5" id="cal-amt-y"></div>
      <div class="cal-field"><label>Date — X (mm)</label><input type="number" step="0.5" id="cal-date-x"></div>
      <div class="cal-field"><label>Date — Y (mm)</label><input type="number" step="0.5" id="cal-date-y"></div>
      <div class="cal-field"><label>Date Segment Gap (mm)</label><input type="number" step="0.5" id="cal-date-gap"></div>
    </div>

    <div class="panel-actions">
      <button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>
      <button class="btn btn-secondary" onclick="saveCalibration()">💾 Save Calibration</button>
      <button class="btn btn-secondary" onclick="resetCalibration()">↺ Reset to Default</button>
    </div>
    <div class="note">Load blank check stock into the rear/single-sheet feed of your printer, set a custom paper size of 8.5" × 3.5" in the print dialog, and print a test on plain paper first to fine-tune alignment before using real check stock.</div>
  </div>

  <div class="sheet-outer">
    <div class="check-sheet" id="check-sheet">
      <div class="fld" id="f-payto" style="left:0;top:0;width:6.2in">
        <?=htmlspecialchars(strtoupper($vendor))?>
      </div>
      <div class="fld" id="f-pesos" style="left:0;top:0;width:6.2in">
        <?=htmlspecialchars(strtoupper($amountWords))?>
      </div>
      <div class="fld" id="f-amount" style="left:0;top:0;font-weight:700">
        <?=$amountNumber?>
      </div>
      <div class="fld" id="f-date" style="left:0;top:0;letter-spacing:2px">
        <span id="date-mm"><?=$dateMM?></span><span class="date-gap" id="date-gap-1"></span><span id="date-dd"><?=$dateDD?></span><span class="date-gap" id="date-gap-2"></span><span id="date-yyyy"><?=$dateYYYY?></span>
      </div>
    </div>
  </div>

<script>
// Default offsets (mm, from top-left of the 8.5in x 3.5in sheet) — one
// starting-point preset per check stock. These are only rough starting
// guesses; every printer/check-stock combo is slightly different, so
// nudge the fields above until the preview lines up with a real blank
// check held against your screen, then hit Save Calibration. Each check
// stock below remembers its own numbers separately.
const BANK_DEFAULTS = {
  metrobank: {
    paytoX: 33, paytoY: 34,
    pesosX: 33, pesosY: 50,
    amtX: 178,  amtY: 34,
    dateX: 198, dateY: 22, dateGap: 6,
  },
  pbb: {
    paytoX: 33, paytoY: 32,
    pesosX: 33, pesosY: 48,
    amtX: 180,  amtY: 32,
    dateX: 200, dateY: 20, dateGap: 6,
  },
};

const STORAGE_PREFIX = 'commissary_check_calibration_v1_';
const BANK_STORAGE_KEY = 'commissary_check_bank_v1';

function currentBank() {
  return document.getElementById('bank-select').value;
}

function loadCalibration(bank) {
  try {
    const saved = localStorage.getItem(STORAGE_PREFIX + bank);
    return saved ? JSON.parse(saved) : { ...BANK_DEFAULTS[bank] };
  } catch { return { ...BANK_DEFAULTS[bank] }; }
}

function applyCalibration(cal) {
  document.getElementById('cal-payto-x').value = cal.paytoX;
  document.getElementById('cal-payto-y').value = cal.paytoY;
  document.getElementById('cal-pesos-x').value = cal.pesosX;
  document.getElementById('cal-pesos-y').value = cal.pesosY;
  document.getElementById('cal-amt-x').value   = cal.amtX;
  document.getElementById('cal-amt-y').value   = cal.amtY;
  document.getElementById('cal-date-x').value  = cal.dateX;
  document.getElementById('cal-date-y').value  = cal.dateY;
  document.getElementById('cal-date-gap').value = cal.dateGap;
  renderFields(cal);
}

function renderFields(cal) {
  const mm = v => v + 'mm';
  document.getElementById('f-payto').style.left  = mm(cal.paytoX);
  document.getElementById('f-payto').style.top   = mm(cal.paytoY);
  document.getElementById('f-pesos').style.left  = mm(cal.pesosX);
  document.getElementById('f-pesos').style.top   = mm(cal.pesosY);
  document.getElementById('f-amount').style.left = mm(cal.amtX);
  document.getElementById('f-amount').style.top  = mm(cal.amtY);
  document.getElementById('f-date').style.left   = mm(cal.dateX);
  document.getElementById('f-date').style.top    = mm(cal.dateY);
  document.getElementById('date-gap-1').style.width = mm(cal.dateGap);
  document.getElementById('date-gap-2').style.width = mm(cal.dateGap);
}

function readInputs() {
  return {
    paytoX: parseFloat(document.getElementById('cal-payto-x').value) || 0,
    paytoY: parseFloat(document.getElementById('cal-payto-y').value) || 0,
    pesosX: parseFloat(document.getElementById('cal-pesos-x').value) || 0,
    pesosY: parseFloat(document.getElementById('cal-pesos-y').value) || 0,
    amtX:   parseFloat(document.getElementById('cal-amt-x').value) || 0,
    amtY:   parseFloat(document.getElementById('cal-amt-y').value) || 0,
    dateX:  parseFloat(document.getElementById('cal-date-x').value) || 0,
    dateY:  parseFloat(document.getElementById('cal-date-y').value) || 0,
    dateGap: parseFloat(document.getElementById('cal-date-gap').value) || 0,
  };
}

function saveCalibration() {
  const bank = currentBank();
  const cal = readInputs();
  localStorage.setItem(STORAGE_PREFIX + bank, JSON.stringify(cal));
  renderFields(cal);
}

function resetCalibration() {
  const bank = currentBank();
  localStorage.removeItem(STORAGE_PREFIX + bank);
  applyCalibration({ ...BANK_DEFAULTS[bank] });
}

function switchBank() {
  const bank = currentBank();
  localStorage.setItem(BANK_STORAGE_KEY, bank);
  applyCalibration(loadCalibration(bank));
}

document.querySelectorAll('.cal-grid input').forEach(el => {
  const update = () => renderFields(readInputs());
  el.addEventListener('input', update);
  el.addEventListener('change', update);
  el.addEventListener('keyup', update);
  el.addEventListener('keydown', () => setTimeout(update, 0));
  el.addEventListener('paste', () => setTimeout(update, 0));
  el.addEventListener('click', update); // catches the up/down spinner arrows
});

document.getElementById('bank-select').addEventListener('change', switchBank);

// Restore the last-used check stock, then load its saved (or default) calibration.
const savedBank = localStorage.getItem(BANK_STORAGE_KEY);
if (savedBank && BANK_DEFAULTS[savedBank]) {
  document.getElementById('bank-select').value = savedBank;
}
applyCalibration(loadCalibration(currentBank()));
</script>

</body>
</html>
