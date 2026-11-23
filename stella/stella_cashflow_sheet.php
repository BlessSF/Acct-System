<?php
// ============================================================
//  stella_cashflow_sheet.php — Stella "CashFlow" workbook
//  One page, six tabs that mirror the CASHFLOW - STELLA Excel:
//    MARCH 30, 2026 · BREAKDOWN VS FS · WITHDRAWALS MONITORING
//    CF STATEMENT · CASH-FLOW · CASHFLOW/FS
//  Everything is inputable (amount boxes also accept simple math
//  like 1960965.07+386344.01) and every total / variance uses the
//  same formulas as the Excel. No data is pre-filled.
// ============================================================
require_once '../config.php';
require_once '../auth.php';
requireLogin();
enforceCashierAccess();

if (isBranch() && currentBranch() !== 'Stella') {
    header('Location: ../dashboard.php'); exit;
}

$pdo  = getPDO();
$user = currentUser();

$pdo->exec("CREATE TABLE IF NOT EXISTS `stella_cashflow_book` (
    `doc_key`    varchar(40) NOT NULL,
    `rev`        int(11) NOT NULL DEFAULT 0,
    `data`       longtext DEFAULT NULL,
    `updated_by` varchar(100) DEFAULT NULL,
    `updated_at` datetime DEFAULT NULL,
    PRIMARY KEY (`doc_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Save (AJAX) ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'save') {
    header('Content-Type: application/json');
    try {
        $data = $_POST['data'] ?? '';
        if (strlen($data) > 3000000 || json_decode($data, true) === null) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid data']); exit;
        }
        $baseRev = (int)($_POST['rev'] ?? 0);
        $pdo->beginTransaction();
        $cur = $pdo->prepare("SELECT rev FROM stella_cashflow_book WHERE doc_key='main' FOR UPDATE");
        $cur->execute();
        $row = $cur->fetch();
        if ($row === false) {
            $pdo->prepare("INSERT INTO stella_cashflow_book (doc_key, rev, data, updated_by, updated_at) VALUES ('main',1,?,?,NOW())")
                ->execute([$data, $user['name']]);
            $newRev = 1;
        } else {
            if ((int)$row['rev'] !== $baseRev) {
                $pdo->rollBack();
                echo json_encode(['ok' => false, 'conflict' => true, 'msg' => 'This sheet was changed somewhere else. Reload the page.']); exit;
            }
            $newRev = $baseRev + 1;
            $pdo->prepare("UPDATE stella_cashflow_book SET rev=?, data=?, updated_by=?, updated_at=NOW() WHERE doc_key='main'")
                ->execute([$newRev, $data, $user['name']]);
        }
        $pdo->commit();
        echo json_encode(['ok' => true, 'rev' => $newRev]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

$st = $pdo->query("SELECT rev, data, updated_by, updated_at FROM stella_cashflow_book WHERE doc_key='main'")->fetch();
$initRev  = $st ? (int)$st['rev'] : 0;
$initData = $st && $st['data'] ? $st['data'] : 'null';
$lastInfo = $st ? ($st['updated_by'] . ' · ' . date('M j, Y g:i A', strtotime($st['updated_at']))) : '';

$pageTitle  = 'CashFlow';
$activePage = 'stella_cashflow_sheet';
$bodyClass = 'sp-stella_cashflow_sheet';
include '../layout.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">

<div class="cf" id="cf">
  <div class="cf-top">
    <div class="cf-tabs" id="cfTabs"></div>
    <div class="cf-save" id="cfSave"><i></i><span><?= $lastInfo ? 'Last saved ' . htmlspecialchars($lastInfo) : 'Nothing saved yet' ?></span></div>
  </div>
  <div id="cfBody"></div>
</div>

<script>
(function () {
  var INIT = <?= $initData ?>;
  var rev  = <?= $initRev ?>;
  var SAVE_URL = location.pathname;

  // ── helpers ─────────────────────────────────────────────
  function uid() { return Math.random().toString(36).slice(2, 9); }
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
  function evalExpr(t) {
    t = String(t).replace(/[,\s₱]/g, '').replace(/^=/, '');
    if (t === '') return 0;
    if (!/^[0-9+\-*/().]+$/.test(t)) return NaN;
    try { var r = Function('"use strict";return (' + t + ')')(); return isFinite(r) ? r : NaN; } catch (e) { return NaN; }
  }
  function N(v) { if (v === '' || v == null) return 0; if (typeof v === 'number') return v; var r = evalExpr(v); return isNaN(r) ? 0 : r; }
  function has(v) { return v !== '' && v != null; }
  function fmt(v) {
    if (!isFinite(v)) v = 0;
    var s = Math.abs(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    return (v < 0 ? '-' : '') + s;
  }
  function round2(v) { return Math.round(v * 100) / 100; }
  function ym(d) { return d ? String(d).slice(0, 7) : ''; }
  var MONTHS = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE','JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];
  function monthLabel(m) { if (!m) return 'MONTH'; var p = m.split('-'); return MONTHS[+p[1] - 1] + ' ' + p[0]; }

  // ── definitions (labels follow the Excel) ───────────────
  var BANK = [['ew','EW ACCOUNT (8801)'],['sec','SEC BANK'],['land','LANDBANK ACCOUNT'],['metro','SAVING METRO/MEDREP'],['mayas','SAVINGS MAYA'],['mayac','CHECKING MAYA/WALKIN'],['gcash','GCASH/GCASH E-WALLET'],['rev','REVOLVING FUND'],['pal','PALAWAN PAY']];
  var AR   = [['medrep','MEDREP UNPAID'],['corp','CORP UNPAID'],['owner',"OWNER'S UNPAID"],['staff','STAFF UNPAID'],['advc','ADV PAYMENT COMMI'],['ca',"STAFF CA'S"],['pos','POS SUBSCRIPTION'],['egovq','MAAM QUEEN EGOV'],['egov','EGOV EXCESS']];

  var TABS = [['b1','MARCH 30, 2026'],['bd','BREAKDOWN VS FS'],['wd','WITHDRAWALS MONITORING'],['cs','CF STATEMENT'],['b2','CASH-FLOW'],['fs','CASHFLOW/FS']];
  var DEF_TITLE = {b1: 'STELLA CASH FLOW BALANCE SHEET — AS OF MARCH 30, 2026', b2: 'STELLA CASH FLOW BALANCE SHEET'};
  function tabName(k) { var n = (S.names || {})[k]; return has(n) ? n : TABS.filter(function (t) { return t[0] === k; })[0][1]; }

  function newBsCol() { return {id: uid(), date: '', v: {}, rel: [{l:'',v:''},{l:'',v:''},{l:'',v:''}], pay: [{l:'',v:''},{l:'',v:''},{l:'',v:''}], begin: '', pull: '', remarks: ''}; }
  function newWdRow() { return {d: '', p: '', a: '', r: ''}; }
  function newWdSec(asof) { var rows = []; for (var i = 0; i < 5; i++) rows.push(newWdRow()); return {id: uid(), asof: asof || '', rows: rows}; }
  function newCsCol() { return {id: uid(), date: '', begin: '', v: {}, actual: ''}; }
  function newFsBlk() { return {id: uid(), month: '', v: {}, xl: ''}; }
  function defaults() {
    var bd = []; for (var i = 0; i < 12; i++) bd.push({inv:'', bank:'', exp:'', pull:''});
    return {
      b1: {cols: [newBsCol()]}, b2: {cols: [newBsCol()]},
      bd: {title: 'STELLA REPORTS FOR INCOME STATEMENT AND PULLOUT', rows: bd},
      wd: {secs: [newWdSec()]},
      cs: {cols: [newCsCol()], lab: {}},
      fs: {blocks: [newFsBlk()]}
    };
  }
  var S = INIT || defaults();
  (function fix() { if (!S.names) S.names = {}; var d = defaults(); Object.keys(d).forEach(function (k) { if (!S[k]) S[k] = d[k]; }); })();

  var tab = (location.hash || '').replace('#', '');
  if (!TABS.some(function (t) { return t[0] === tab; })) tab = 'b1';

  // ── path get/set ────────────────────────────────────────
  function getP(path) { return path.split('.').reduce(function (o, k) { return o == null ? o : o[k]; }, S); }
  function setP(path, val) {
    var p = path.split('.'), o = S;
    for (var i = 0; i < p.length - 1; i++) { if (o[p[i]] == null) o[p[i]] = {}; o = o[p[i]]; }
    o[p[p.length - 1]] = val;
  }
  function inp(path, cls, ph, type) {
    var v = getP(path);
    if ((path === 'names.b1t' || path === 'names.b2t') && !has(v)) v = ph;
    if (cls === 'n' && has(v)) { var r = evalExpr(v); if (!isNaN(r)) v = fmt(r); }
    return '<input type="' + (type || 'text') + '" data-p="' + path + '" class="' + (cls || '') + '" value="' + esc(v == null ? '' : v) + '"' + (ph ? ' placeholder="' + esc(ph) + '"' : '') + '>';
  }
  function numIn(path, ph) { return inp(path, 'n', ph || '0.00', 'text').replace('<input ', '<input inputmode="decimal" '); }

  // ── withdrawals helpers ─────────────────────────────────
  function wdSecTotal(sec) { return sec.rows.reduce(function (a, r) { return a + N(r.a); }, 0); }
  function wdMonthTotal(m) { return S.wd.secs.filter(function (s) { return ym(s.asof) === m; }).reduce(function (a, s) { return a + wdSecTotal(s); }, 0); }

  // ── balance-sheet computations ──────────────────────────
  function bsCalc(key) {
    var cols = S[key].cols, out = [];
    cols.forEach(function (c, i) {
      var bt = BANK.reduce(function (a, b) { return a + N(c.v[b[0]]); }, 0);
      var at = AR.reduce(function (a, b) { return a + N(c.v[b[0]]); }, 0);
      var rel = c.rel.reduce(function (a, r) { return a + N(r.v); }, 0);
      var pay = c.pay.reduce(function (a, r) { return a + N(r.v); }, 0);
      var pt = N(c.v.advdep) + rel + pay;
      var ef = bt + at - pt;
      var begin = has(c.begin) ? N(c.begin) : (i > 0 ? out[i - 1].ef : 0);
      out.push({bt: bt, at: at, rel: rel, pay: pay, pt: pt, ef: ef, begin: begin, inc: ef - begin + N(c.pull)});
    });
    return out;
  }
  function cashflowLink(month) { // last Cash-Flow column falling in that month
    if (!month) return null;
    var calc = bsCalc('b2'), hit = null;
    S.b2.cols.forEach(function (c, i) { if (ym(c.date) === month) { if (!hit || c.date >= S.b2.cols[hit.i].date) hit = {i: i, r: calc[i]}; } });
    return hit ? hit.r : null;
  }

  function kpi(label, id, cls) { return '<div class="kpi' + (cls ? ' ' + cls : '') + '"><span>' + label + '</span><b data-c="' + id + '">0.00</b></div>'; }
  function setK(id, v) { var el = document.querySelector('[data-c="' + id + '"]'); if (el) { el.textContent = fmt(v); el.classList.toggle('cf-neg', v < 0); } }

  // ── render: balance sheet (MARCH 30 / CASH-FLOW) ────────
  function renderBs(key, title, sub) {
    var cols = S[key].cols, h = '';
    h += '<div class="cf-card"><div class="cf-ttl"><div><div class="sx-15 cf-h" >' + inp('names.' + key + 't', '', title) .replace('<input ', '<input class="sx-10" ') + '</div><div class="cf-sub">' + sub + '</div></div><button class="cf-btn" data-act="bsaddcol" data-k="' + key + '">+ Add column</button></div>';
    h += '<div class="cf-kpis">' + kpi('Bank balances', 'kpi:' + key + ':bt') + kpi('Receivables', 'kpi:' + key + ':at') + kpi('Payables', 'kpi:' + key + ':pt') + kpi('Extra fund', 'kpi:' + key + ':ef', 'hi') + kpi('Increase in cash', 'kpi:' + key + ':inc') + '</div>';
    h += '<div class="cf-scroll"><table class="cf-tbl"><thead><tr><th class="lab">DATE</th>';
    cols.forEach(function (c, i) { h += '<th class="sx-14 c" ><div class="sx-6">' + inp(key + '.cols.' + i + '.date', '', '', 'date') + '<button class="cf-x" title="Remove column" data-act="bsdelcol" data-k="' + key + '" data-i="' + i + '">×</button></div></th>'; });
    h += '</tr></thead><tbody>';
    function sec(t) { h += '<tr class="sec"><td class="lab">' + t + '</td>' + cols.map(function () { return '<td></td>'; }).join('') + '</tr>'; }
    function line(label, fn, cls) { h += '<tr class="' + (cls || '') + '"><td class="lab">' + label + '</td>' + cols.map(function (c, i) { return '<td>' + fn(c, i) + '</td>'; }).join('') + '</tr>'; }
    function calcRow(label, id, cls) { line(label, function (c, i) { return '<div class="sx-5 num" data-c="' + key + ':' + i + ':' + id + '"></div>'; }, cls); }

    sec('BANK BALANCES');
    BANK.forEach(function (b) { line(b[1], function (c, i) { return numIn(key + '.cols.' + i + '.v.' + b[0]); }); });
    calcRow('TOTAL', 'bt', 'tot');
    sec('ACCOUNTS RECEIVABLE');
    AR.forEach(function (b) { line(b[1], function (c, i) { return numIn(key + '.cols.' + i + '.v.' + b[0]); }); });
    calcRow('TOTAL', 'at', 'tot');
    sec('ACCOUNTS PAYABLE');
    line('ADV. DEP (MEDREP/CORP.)', function (c, i) { return numIn(key + '.cols.' + i + '.v.advdep'); });
    calcRow('RELEASED CHECK (total of list)', 'rel', 'sub');
    line('<span class="sx-13" style="color:#6b7280">Released check list</span>', function (c, i) {
      return c.rel.map(function (r, j) { return '<div class="cf-item">' + inp(key + '.cols.' + i + '.rel.' + j + '.l', '', 'Particulars') + numIn(key + '.cols.' + i + '.rel.' + j + '.v') + '<button class="cf-x" data-act="bsdelitem" data-k="' + key + '" data-i="' + i + '" data-g="rel" data-j="' + j + '">×</button></div>'; }).join('') +
        '<button class="cf-add" data-act="bsadditem" data-k="' + key + '" data-i="' + i + '" data-g="rel">+ Add row</button>';
    });
    calcRow('ACCNT PAYABLES (AS OF NOW) (total of list)', 'pay', 'sub');
    line('<span class="sx-13" style="color:#6b7280">Accounts payable list</span>', function (c, i) {
      return c.pay.map(function (r, j) { return '<div class="cf-item">' + inp(key + '.cols.' + i + '.pay.' + j + '.l', '', 'Particulars') + numIn(key + '.cols.' + i + '.pay.' + j + '.v') + '<button class="cf-x" data-act="bsdelitem" data-k="' + key + '" data-i="' + i + '" data-g="pay" data-j="' + j + '">×</button></div>'; }).join('') +
        '<button class="cf-add" data-act="bsadditem" data-k="' + key + '" data-i="' + i + '" data-g="pay">+ Add row</button>';
    });
    calcRow('TOTAL', 'pt', 'tot');
    sec('EXTRA FUND');
    calcRow('EXTRA FUND END OF MONTH', 'ef', 'tot');
    line('OP BEGINNING FUND', function (c, i) { return numIn(key + '.cols.' + i + '.begin', i > 0 ? 'auto = previous extra fund' : 'enter beginning fund').replace('data-p', 'data-bsb="1" data-p'); });
    line('PULL-OUT (added back) <button class="sx-12 cf-mini" title="Fill from Withdrawals Monitoring (same month as the date)" data-act="bspull" data-hint="1">↧ use Withdrawals</button>', function (c, i) { return numIn(key + '.cols.' + i + '.pull'); });
    calcRow('INCREASE IN CASH (end of month)', 'inc', 'big');
    line('REMARKS', function (c, i) { return '<textarea data-p="' + key + '.cols.' + i + '.remarks" placeholder="e.g. PULL OUT: 500K DEPO TO SECBANK …">' + esc(c.remarks) + '</textarea>'; });
    h += '</tbody></table></div>';
    h += '<div class="cf-note">Formulas — Totals = sum of the lines · Extra Fund = Bank total + Receivable total − Payable total · Increase in cash = Extra Fund − Beginning Fund + Pull-out. Beginning fund fills in from the previous column unless you type one.</div></div>';
    return h;
  }
  function recalcBs(key) {
    var calc = bsCalc(key);
    if (calc.length) { var L = calc[calc.length - 1]; ['bt','at','pt','ef','inc'].forEach(function (id) { setK('kpi:' + key + ':' + id, L[id]); }); }
    calc.forEach(function (r, i) {
      ['bt','at','rel','pay','pt','ef','inc'].forEach(function (id) {
        var el = document.querySelector('[data-c="' + key + ':' + i + ':' + id + '"]');
        if (el) { el.textContent = fmt(r[id]); el.classList.toggle('cf-neg', r[id] < 0 && id !== 'inc'); }
      });
      var b = document.querySelector('[data-p="' + key + '.cols.' + i + '.begin"]');
      if (b) b.placeholder = i > 0 ? 'auto = ' + fmt(calc[i - 1].ef) : 'enter beginning fund';
    });
  }

  // ── render: BREAKDOWN VS FS ─────────────────────────────
  function renderBd() {
    var h = '<div class="cf-card"><div class="cf-ttl"><div class="sx-11">' + inp('bd.title', '', 'Title').replace('<input ', '<input  ') + '<div class="sx-10 sx-9 cf-sub" >Monthly inventory, bank balance, expenses and pull-out used for the Income Statement.</div></div></div>';
    h += '<div class="cf-scroll"><table class="cf-tbl"><thead><tr><th class="lab">DATE</th><th>INVENTORY</th><th>BANK BALANCE</th><th>EXPENSES</th><th>PULL-OUT</th></tr></thead><tbody>';
    S.bd.rows.forEach(function (r, i) {
      h += '<tr><td class="lab">' + MONTHS[i] + '</td>' +
        ['inv','bank','exp','pull'].map(function (k) { return '<td>' + numIn('bd.rows.' + i + '.' + k) + '</td>'; }).join('') + '</tr>';
    });
    h += '</tbody></table></div><div class="cf-note">Tip: amount boxes accept math, e.g. <b>1960965.07+386344.01</b> — press Tab and it turns into the total.</div></div>';
    return h;
  }

  // ── render: WITHDRAWALS MONITORING ──────────────────────
  function wdTable(si, compact) {
    var s = S.wd.secs[si], h = '';
    h += '<table class="cf-mini-tbl"><thead><tr><th style="width:130px">DATE</th><th>PARTICULARS</th><th style="width:120px">AMOUNT</th><th style="width:110px">REMARKS</th><th style="width:26px"></th></tr></thead><tbody>';
    s.rows.forEach(function (r, j) {
      h += '<tr><td>' + inp('wd.secs.' + si + '.rows.' + j + '.d', '', '', 'date') + '</td><td>' + inp('wd.secs.' + si + '.rows.' + j + '.p', '', 'Particulars') + '</td><td>' + numIn('wd.secs.' + si + '.rows.' + j + '.a') + '</td><td>' + inp('wd.secs.' + si + '.rows.' + j + '.r', '', '') + '</td><td><button class="cf-x" data-act="wddelrow" data-si="' + si + '" data-j="' + j + '">×</button></td></tr>';
    });
    h += '<tr class="tot"><td></td><td class="sx-5">TOTAL</td><td class="num" data-c="wd:' + si + ':tot"></td><td colspan="2"><button class="cf-add" data-act="wdaddrow" data-si="' + si + '">+ Add row</button></td></tr></tbody></table>';
    return h;
  }
  function renderWd() {
    var h = '<div class="cf-intro"><div><div class="cf-h">WITHDRAWALS MONITORING</div><div class="sx-4 cf-sub" >One block per month — the totals feed CF Statement/Cash-Flow pull-out and the CASHFLOW/FS tab.</div></div><button class="cf-btn" data-act="wdaddsec">+ Add month</button></div>';
    S.wd.secs.forEach(function (s, si) {
      h += '<div class="cf-card"><div class="cf-ttl"><div class="sx-8"><b class="sx-7">AS OF</b><div style="width:170px">' + inp('wd.secs.' + si + '.asof', '', '', 'date') + '</div><span class="sx-4 cf-sub" >WITHDRAWALS BREAKDOWN</span><span class="cf-pill">Total<b data-c="wd:' + si + ':tot"></b></span></div><button class="cf-x" title="Remove this month" data-act="wddelsec" data-si="' + si + '">Remove ×</button></div>' + wdTable(si) + '</div>';
    });
    return h;
  }
  function recalcWd() {
    S.wd.secs.forEach(function (s, si) {
      document.querySelectorAll('[data-c="wd:' + si + ':tot"]').forEach(function (el) { el.textContent = fmt(wdSecTotal(s)); });
    });
  }

  // ── render: CF STATEMENT ────────────────────────────────
  var CS_ROWS = [
    ['h','Operations'],
    ['r','sales','Sales'],['r','osales','Other Operation Sales'],
    ['s','Cash paid for'],
    ['p','inv','Inventory Purchases'],['p','gen','General operating and administrative expenses'],['pc','opx1','Other cash paid (name it)'],['pc','opx2','Other cash paid (name it)'],
    ['t','nops','Net Cash Flow from Operations'],
    ['h','Investing Activities'],
    ['s','Cash receipts from'],
    ['r','isale','Sales of investing property & equipment'],['r','fees','Fees Income'],
    ['s','Cash paid for'],
    ['p','ppe','Purchase of property & equipment'],['pc','invx','Other cash paid (name it)'],
    ['t','ninv','Net Cash Flow from Investing Activities'],
    ['h','Financing Activities'],
    ['s','Cash receipts from'],
    ['r','dep','Depreciation'],['rc','finr','Other cash receipt (name it)'],
    ['s','Cash paid for'],
    ['p','loan','Repayment for loan & investment'],['p','ppo','Profit Pull out'],['p','car','Repayment for loan (company car)'],['p','dnd','DND taxes'],['p','own','Owners pull-out'],
    ['t','nfin','Net Cash Flow from Financing Activities']
  ];
  function csCalc() {
    var out = [];
    S.cs.cols.forEach(function (c, i) {
      var v = function (k) { return N(c.v[k]); }, ab = function (k) { return Math.abs(v(k)); };
      var nops = v('sales') + v('osales') - ab('inv') - ab('gen') - ab('opx1') - ab('opx2');
      var ninv = v('isale') + v('fees') - ab('ppe') - ab('invx');
      var nfin = v('dep') + v('finr') - ab('loan') - ab('ppo') - ab('car') - ab('dnd') - ab('own');
      var prev = i > 0 ? out[i - 1] : null;
      var begin = has(c.begin) ? N(c.begin) : (prev ? (has(S.cs.cols[i - 1].actual) ? N(S.cs.cols[i - 1].actual) : prev.end) : 0);
      var end = begin + nops + ninv + nfin;
      out.push({nops: nops, ninv: ninv, nfin: nfin, begin: begin, end: end, inc: end - begin, vari: has(c.actual) ? N(c.actual) - end : null});
    });
    return out;
  }
  function renderCs() {
    var cols = S.cs.cols, h = '';
    h += '<div class="cf-card"><div class="cf-ttl"><div><div class="cf-h">CASHFLOW STATEMENT — STELLA FUSION BUFFET</div><div class="cf-sub">Enter every amount as a positive number — “Cash paid for” rows are subtracted automatically.</div></div><button class="cf-btn" data-act="csaddcol">+ Add month</button></div>';
    h += '<div class="cf-kpis">' + kpi('Operations', 'kpi:cs:nops') + kpi('Investing', 'kpi:cs:ninv') + kpi('Financing', 'kpi:cs:nfin') + kpi('Cash at end of month', 'kpi:cs:end', 'hi') + kpi('Variance vs actual', 'kpi:cs:vari') + '</div>';
    h += '<div class="cf-scroll"><table class="cf-tbl"><thead><tr><th class="lab">For the month ending</th>' + cols.map(function (c, i) { return '<th class="c"><div class="sx-6">' + inp('cs.cols.' + i + '.date', '', '', 'date') + '<button class="cf-x" data-act="csdelcol" data-i="' + i + '">×</button></div></th>'; }).join('') + '</tr></thead><tbody>';
    function line(label, fn, cls) { h += '<tr class="' + (cls || '') + '"><td class="lab">' + label + '</td>' + cols.map(function (c, i) { return '<td>' + fn(c, i) + '</td>'; }).join('') + '</tr>'; }
    line('Cash at Beginning of Month', function (c, i) { return numIn('cs.cols.' + i + '.begin', i > 0 ? 'auto' : 'enter beginning cash'); }, 'sub');
    CS_ROWS.forEach(function (r) {
      var t = r[0];
      if (t === 'h') h += '<tr class="sec"><td class="lab">' + r[1].toUpperCase() + '</td>' + cols.map(function () { return '<td></td>'; }).join('') + '</tr>';
      else if (t === 's') h += '<tr class="sub"><td class="lab">' + r[1] + '</td>' + cols.map(function () { return '<td></td>'; }).join('') + '</tr>';
      else if (t === 'r' || t === 'p') line(r[2], function (c, i) { return numIn('cs.cols.' + i + '.v.' + r[1]); });
      else if (t === 'pc' || t === 'rc') line('<input type="text" data-p="cs.lab.' + r[1] + '" value="' + esc((S.cs.lab || {})[r[1]] || '') + '" placeholder="' + r[2] + '">', function (c, i) { return numIn('cs.cols.' + i + '.v.' + r[1]); });
      else if (t === 't') line(r[2], function (c, i) { return '<div class="sx-5" data-c="cs:' + i + ':' + r[1] + '"></div>'; }, 'tot');
    });
    line('Net Increase in Cash', function (c, i) { return '<div class="sx-5" data-c="cs:' + i + ':inc"></div>'; }, 'tot');
    line('Cash at End of Month', function (c, i) { return '<div class="sx-5" data-c="cs:' + i + ':end"></div>'; }, 'big');
    line('Actual cash at end of month (per bank / records)', function (c, i) { return numIn('cs.cols.' + i + '.actual', 'optional'); });
    line('Variance (Actual − Computed)', function (c, i) { return '<div class="sx-5" data-c="cs:' + i + ':vari"></div>'; }, 'tot');
    h += '</tbody></table></div><div class="cf-note">Formulas — Operations = Sales + Other Sales − Inventory − G&amp;A · Investing = Receipts − Purchases · Financing = Depreciation − loan/investment − Profit pull-out − car loan − DND taxes − Owners pull-out · Cash at End = Beginning + Operations + Investing + Financing. Beginning cash follows the previous month’s actual (or computed) end unless you type one.</div></div>';
    return h;
  }
  function recalcCs() {
    var cc = csCalc();
    if (cc.length) { var L = cc[cc.length - 1]; ['nops','ninv','nfin','end'].forEach(function (id) { setK('kpi:cs:' + id, L[id]); }); var kv = document.querySelector('[data-c="kpi:cs:vari"]'); if (kv) { kv.textContent = L.vari === null ? '—' : fmt(L.vari); kv.classList.toggle('cf-neg', L.vari !== null && L.vari < 0); } }
    cc.forEach(function (r, i) {
      ['nops','ninv','nfin','inc','end','vari'].forEach(function (id) {
        var el = document.querySelector('[data-c="cs:' + i + ':' + id + '"]');
        if (!el) return;
        el.textContent = (id === 'vari' && r.vari === null) ? '—' : fmt(id === 'vari' ? r.vari : r[id]);
      });
      var b = document.querySelector('[data-p="cs.cols.' + i + '.begin"]');
      if (b && i > 0) { var p = csCalc()[i - 1]; b.placeholder = 'auto = ' + fmt(has(S.cs.cols[i - 1].actual) ? N(S.cs.cols[i - 1].actual) : p.end); }
    });
  }

  // ── render: CASHFLOW/FS ─────────────────────────────────
  function fsCalc() {
    var blocks = S.fs.blocks, res = {};
    var order = blocks.map(function (b, i) { return i; }).sort(function (a, b) { return (blocks[a].month || '') < (blocks[b].month || '') ? -1 : 1; });
    order.forEach(function (i, pos) {
      var b = blocks[i], v = function (k) { return N(b.v[k]); };
      var link = cashflowLink(b.month);
      var bank = has(b.v.bank) ? v('bank') : (link ? link.bt : 0);
      var recv = has(b.v.recv) ? v('recv') : (link ? link.at : 0);
      var pay  = has(b.v.pay)  ? v('pay')  : (link ? link.pt : 0);
      var prev = pos > 0 ? res[order[pos - 1]] : null;
      var begin = has(b.v.begin) ? v('begin') : (prev ? prev.extra : 0);
      var netSale = v('sales') - v('cogs') - v('disc') - v('opex') - v('genex') - v('extraexp') - v('other');
      var totalNet = netSale + v('dep');
      var totalNet2 = totalNet - v('purch');
      var sub = bank + recv, extra = sub - pay, netInc = extra - begin;
      var pull = b.month ? wdMonthTotal(b.month) : 0;
      var noPull = netInc + pull;
      res[i] = {link: link, bank: bank, recv: recv, pay: pay, begin: begin, netSale: netSale, totalNet: totalNet, totalNet2: totalNet2, sub: sub, extra: extra, netInc: netInc, pull: pull, noPull: noPull, vari: totalNet2 - noPull, prevExtra: prev ? prev.extra : null};
    });
    return res;
  }
  function renderFs() {
    var h = '<div class="cf-intro"><div><div class="cf-h">CASHFLOW / FS — Income Statement vs Cashflow</div><div class="sx-4 cf-sub" >One block per month. Bank, receivables and payables fill in from the CASH-FLOW tab (same month) and the pull-out from WITHDRAWALS MONITORING, unless you type over them.</div></div><button class="cf-btn" data-act="fsaddblk">+ Add month</button></div>';
    S.fs.blocks.forEach(function (b, i) {
      var p = 'fs.blocks.' + i + '.v.';
      function row(label, key, ph) { return '<tr><td class="k">' + label + '</td><td>' + numIn(p + key, ph || '') + '</td></tr>'; }
      function calc(label, id, cls) { return '<tr class="' + (cls || 'tot') + '"><td class="k">' + label + '</td><td class="num" data-c="fs:' + i + ':' + id + '"></td></tr>'; }
      h += '<div class="cf-card"><div class="cf-ttl"><div class="sx-3"><b>STELLA — FOR THE MONTH OF</b><div style="width:170px">' + inp('fs.blocks.' + i + '.month', '', '', 'month') + '</div><b data-c="fs:' + i + ':lbl" style="color:var(--accent)"></b></div><button class="cf-x" data-act="fsdelblk" data-i="' + i + '">Remove ×</button></div>';
      h += '<div class="cf-kpis">' + kpi('Net sale', 'fs:' + i + ':k_netSale') + kpi('Total net', 'fs:' + i + ':k_totalNet2') + kpi('Extra fund', 'fs:' + i + ':k_extra') + kpi('Net income', 'fs:' + i + ':k_netInc') + kpi('Variance', 'fs:' + i + ':k_vari', 'hi') + '</div>';
      h += '<div class="cf-fsgrid">';
      h += '<div><table class="cf-mini-tbl"><thead><tr><th colspan="2">INCOME STATEMENT</th></tr></thead><tbody>' +
        row('NET SALES', 'sales') + row('COGS', 'cogs') + row('SALES DISCOUNT', 'disc') + row('OPERATING EXPENSES', 'opex') + row('GENERAL EXPENSES', 'genex') + row('EXTRA EXPENSES', 'extraexp') +
        '<tr><td class="k">' + inp('fs.blocks.' + i + '.xl', '', 'Other (e.g. HONDA BEAT)') + '</td><td>' + numIn(p + 'other') + '</td></tr>' +
        calc('NET SALE', 'netSale') + row('DEPRECIATION', 'dep') + calc('TOTAL NET', 'totalNet') + row('ACTUAL PURCHASES', 'purch') + calc('TOTAL NET (after purchases)', 'totalNet2', 'big') +
        '</tbody></table></div>';
      h += '<div><table class="cf-mini-tbl"><thead><tr><th colspan="2">CASHFLOW</th></tr></thead><tbody>' +
        row('BANK BALANCE', 'bank') + row('RECEIVABLES', 'recv') + calc('BANK + RECEIVABLES', 'sub') + row('PAYABLES / RELEASED CHECK', 'pay') + calc('EXTRA FUND', 'extra') + row('BEGINNING BALANCE', 'begin') + calc('NET INCOME', 'netInc') +
        calc('PULLOUT (from Withdrawals)', 'pull') + calc('TOTAL NET IF NO PULL-OUT', 'noPull') + calc('VARIANCE (Total Net − No Pull-out)', 'vari', 'big') +
        '</tbody></table></div>';
      h += '<div><table class="cf-mini-tbl"><thead><tr><th>WITHDRAWALS BREAKDOWN' + (b.month ? ' — AS OF ' + monthLabel(b.month) : '') + '</th></tr></thead></table>';
      var any = false;
      S.wd.secs.forEach(function (s, si) { if (b.month && ym(s.asof) === b.month) { any = true; h += '<div class="sx-2">' + wdTable(si) + '</div>'; } });
      if (!any) h += '<div class="cf-note">' + (b.month ? 'No withdrawals entered for this month yet.' : 'Pick the month first.') + '</div>' + (b.month ? '<button class="sx-1 cf-add" data-act="fsaddwd" data-i="' + i + '">+ Add withdrawals for this month</button>' : '');
      h += '</div></div></div>';
    });
    h += '<div class="cf-note">Formulas — Net Sale = Net Sales − COGS − Sales Discount − Operating Exp − General Exp − Extra Exp − Other · Total Net = Net Sale + Depreciation · Extra Fund = Bank + Receivables − Payables · Net Income = Extra Fund − Beginning Balance · Total Net if No Pull-out = Net Income + Pull-out · Variance = Total Net − Total Net if No Pull-out.</div>';
    return h;
  }
  function recalcFs() {
    var res = fsCalc();
    S.fs.blocks.forEach(function (b, i) {
      var r = res[i];
      ['netSale','totalNet','totalNet2','sub','extra','netInc','pull','noPull','vari'].forEach(function (id) {
        var el = document.querySelector('[data-c="fs:' + i + ':' + id + '"]');
        if (!el) return;
        el.textContent = fmt(r[id]);
        if (id === 'vari') { el.classList.toggle('cf-neg', false); el.style.color = ''; }
      });
      [['netSale','k_netSale'],['totalNet2','k_totalNet2'],['extra','k_extra'],['netInc','k_netInc'],['vari','k_vari']].forEach(function (q) { setK('fs:' + i + ':' + q[1], r[q[0]]); });
      var l = document.querySelector('[data-c="fs:' + i + ':lbl"]'); if (l) l.textContent = b.month ? monthLabel(b.month) : '';
      var set = function (k, val, text) { var e = document.querySelector('[data-p="fs.blocks.' + i + '.v.' + k + '"]'); if (e) e.placeholder = text || '0.00'; };
      set('bank', 0, r.link ? 'linked: ' + fmt(r.link.bt) : ''); set('recv', 0, r.link ? 'linked: ' + fmt(r.link.at) : ''); set('pay', 0, r.link ? 'linked: ' + fmt(r.link.pt) : '');
      set('begin', 0, r.prevExtra !== null ? 'auto: ' + fmt(r.prevExtra) : '');
    });
    recalcWd();
  }

  // ── main render ─────────────────────────────────────────
  function renderTabs() {
    document.getElementById('cfTabs').innerHTML = TABS.map(function (t) { return '<button class="cf-tab' + (t[0] === tab ? ' on' : '') + '" data-tab="' + t[0] + '" title="Double-click to rename">' + esc(tabName(t[0])) + (t[0] === tab ? ' <span class="cf-pen" data-rename="' + t[0] + '" title="Rename this tab">✎</span>' : '') + '</button>'; }).join('');
  }
  function render() {
    renderTabs();
    var body = document.getElementById('cfBody'), h = '';
    if (tab === 'b1') h = renderBs('b1', DEF_TITLE.b1, 'Bank balances, receivables and payables per date. Add a column for each cut-off date.');
    else if (tab === 'bd') h = renderBd();
    else if (tab === 'wd') h = renderWd();
    else if (tab === 'cs') h = renderCs();
    else if (tab === 'b2') h = renderBs('b2', DEF_TITLE.b2, 'Month-end balance sheet. Add a column for each month (JAN, FEB, MARCH 30, APRIL 30 …).');
    else if (tab === 'fs') h = renderFs();
    body.innerHTML = h;
    recalc();
  }
  function recalc() {
    if (tab === 'b1') recalcBs('b1'); else if (tab === 'b2') recalcBs('b2');
    else if (tab === 'wd') recalcWd(); else if (tab === 'cs') recalcCs(); else if (tab === 'fs') recalcFs();
  }

  // ── saving ──────────────────────────────────────────────
  var timer = null, saving = false, dirty = false, el = document.getElementById('cfSave');
  function status(cls, txt) { el.className = 'cf-save ' + cls; el.querySelector('span').textContent = txt; }
  function queueSave() { dirty = true; status('busy', 'Saving…'); clearTimeout(timer); timer = setTimeout(doSave, 700); }
  function doSave() {
    if (saving) { timer = setTimeout(doSave, 400); return; }
    saving = true; dirty = false;
    var fd = new FormData(); fd.append('ajax_action', 'save'); fd.append('rev', rev); fd.append('data', JSON.stringify(S));
    fetch(SAVE_URL, {method: 'POST', body: fd, credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (j) {
      saving = false;
      if (j.ok) { rev = j.rev; status(dirty ? 'busy' : '', dirty ? 'Saving…' : 'Saved ✓ ' + new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})); }
      else { status('err', j.msg || 'Could not save'); if (j.conflict) alert(j.msg); }
    }).catch(function () { saving = false; status('err', 'Not saved — check your connection'); });
  }
  window.addEventListener('beforeunload', function (e) { if (dirty || saving) { e.preventDefault(); e.returnValue = ''; } });

  // ── events ──────────────────────────────────────────────
  var body = document.getElementById('cfBody');
  body.addEventListener('input', function (e) {
    var t = e.target, p = t.getAttribute('data-p');
    if (!p) return;
    setP(p, t.value);
    if (/^fs\.blocks\.\d+\.month$/.test(p) && t.value.length === 7) { render(); queueSave(); return; } // refresh the withdrawals panel for the chosen month
    recalc(); queueSave();
    // dependent tabs re-evaluate when shown; nothing else to refresh here
  });
  body.addEventListener('focusin', function (e) {
    var t = e.target;
    if (t.classList && t.classList.contains('n') && t.value && t.value.indexOf(',') > -1) { t.value = t.value.replace(/,/g, ''); try { t.select(); } catch (x) {} }
  });
  body.addEventListener('focusout', function (e) {
    var t = e.target;
    if (!(t.classList && t.classList.contains('n')) || !t.value) return;
    var r = evalExpr(t.value);
    if (isNaN(r)) { t.value = ''; setP(t.getAttribute('data-p'), ''); recalc(); queueSave(); return; }
    r = round2(r); t.value = fmt(r); setP(t.getAttribute('data-p'), String(r)); recalc(); queueSave();
  });
  function renameTab(k) {
    var btn = document.querySelector('[data-tab="' + k + '"]'); if (!btn) return;
    var i = document.createElement('input'); i.type = 'text'; i.value = tabName(k); i.className = 'cf-tabedit';
    i.style.width = Math.max(140, i.value.length * 9 + 30) + 'px';
    btn.replaceWith(i); i.focus(); i.select();
    var done = false;
    function fin(save) { if (done) return; done = true; if (save) { S.names[k] = i.value.trim(); queueSave(); } renderTabs(); }
    i.addEventListener('keydown', function (e) { if (e.key === 'Enter') fin(true); else if (e.key === 'Escape') fin(false); });
    i.addEventListener('blur', function () { fin(true); });
  }
  document.getElementById('cfTabs').addEventListener('dblclick', function (e) { var b = e.target.closest('[data-tab]'); if (b) renameTab(b.getAttribute('data-tab')); });
  document.getElementById('cfTabs').addEventListener('click', function (e) {
    var pen = e.target.closest('[data-rename]'); if (pen) { e.stopPropagation(); renameTab(pen.getAttribute('data-rename')); return; }
    var b = e.target.closest('[data-tab]'); if (!b) return;
    tab = b.getAttribute('data-tab'); location.hash = tab; render();
  });
  body.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var a = b.getAttribute('data-act'), k = b.getAttribute('data-k'), i = +b.getAttribute('data-i'), j = +b.getAttribute('data-j'), si = +b.getAttribute('data-si'), g = b.getAttribute('data-g');
    var done = true;
    if (a === 'bsaddcol') S[k].cols.push(newBsCol());
    else if (a === 'bsdelcol') { if (S[k].cols.length < 2) { S[k].cols = [newBsCol()]; } else if (confirm('Remove this column and its data?')) S[k].cols.splice(i, 1); else done = false; }
    else if (a === 'bsadditem') S[k].cols[i][g].push({l: '', v: ''});
    else if (a === 'bsdelitem') S[k].cols[i][g].splice(j, 1);
    else if (a === 'bspull') {
      var key = tab === 'b1' ? 'b1' : 'b2';
      S[key].cols.forEach(function (c) { if (c.date) c.pull = String(round2(wdMonthTotal(ym(c.date)))); });
    }
    else if (a === 'wdaddsec') S.wd.secs.push(newWdSec());
    else if (a === 'wddelsec') { if (confirm('Remove this month and all its withdrawals?')) S.wd.secs.splice(si, 1); else done = false; }
    else if (a === 'wdaddrow') S.wd.secs[si].rows.push(newWdRow());
    else if (a === 'wddelrow') S.wd.secs[si].rows.splice(j, 1);
    else if (a === 'csaddcol') S.cs.cols.push(newCsCol());
    else if (a === 'csdelcol') { if (S.cs.cols.length < 2) { S.cs.cols = [newCsCol()]; } else if (confirm('Remove this month?')) S.cs.cols.splice(i, 1); else done = false; }
    else if (a === 'fsaddblk') S.fs.blocks.push(newFsBlk());
    else if (a === 'fsdelblk') { if (confirm('Remove this month block?')) S.fs.blocks.splice(i, 1); else done = false; }
    else if (a === 'fsaddwd') { var m = S.fs.blocks[i].month, d = new Date(+m.slice(0, 4), +m.slice(5, 7), 0); var last = m + '-' + String(d.getDate()).padStart(2, '0'); S.wd.secs.push(newWdSec(last)); }
    else done = false;
    if (done) { render(); queueSave(); }
  });

  render();
})();
</script>