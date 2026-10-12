/* ============================================================
 * keynav.js — SalesHub shared spreadsheet-style keyboard navigation
 * Drop-in, no markup changes required. Works on any <table> that
 * contains editable input/select/textarea cells.
 *
 *   Arrow Up/Down     move to the same column, previous/next row
 *   Arrow Left/Right   move to the previous/next cell (once the
 *                       text caret is at the edge of the field)
 *   Enter               move down (like Excel)
 *   Shift + Enter        move up
 *   Ctrl + Arrow Up/Down  jump 10 rows at a time (fast scan)
 *   PageUp / PageDown    jump 10 rows at a time
 *   Ctrl + Left/Right    jump to first/last cell in the row
 *   Ctrl + Home / End    jump to the very first / last cell in the table
 * ============================================================ */
(function () {
  function isEditable(el) {
    if (!el) return false;
    const tag = el.tagName;
    if (tag === 'SELECT') return !el.disabled;
    if (tag === 'TEXTAREA') return !el.disabled && !el.readOnly;
    if (tag === 'INPUT') {
      const type = (el.type || 'text').toLowerCase();
      if (['hidden', 'button', 'submit', 'reset', 'file', 'image'].includes(type)) return false;
      return !el.disabled && !el.readOnly;
    }
    return false;
  }

  function buildGrid(table) {
    const grid = [];
    table.querySelectorAll('tr').forEach(function (tr) {
      const cells = Array.prototype.filter.call(
        tr.querySelectorAll('input,select,textarea'),
        isEditable
      );
      if (cells.length) grid.push(cells);
    });
    return grid;
  }

  function findPos(grid, el) {
    for (let r = 0; r < grid.length; r++) {
      const c = grid[r].indexOf(el);
      if (c !== -1) return { r: r, c: c };
    }
    return null;
  }

  function focusCell(el) {
    if (!el) return false;
    el.focus();
    try { el.scrollIntoView({ block: 'nearest', inline: 'nearest' }); } catch (e) {}
    const t = (el.type || 'text').toLowerCase();
    if (el.tagName === 'INPUT' && ['text', 'number', 'search', 'tel', 'email', 'url'].indexOf(t) !== -1) {
      try { el.select(); } catch (e) {}
    }
    const row = el.closest('tr');
    if (row) {
      row.classList.add('keynav-row-active');
      setTimeout(function () { row.classList.remove('keynav-row-active'); }, 600);
    }
    return true;
  }

  // Only genuine free-text fields have a meaningful in-field caret to protect.
  // Number/date/select/etc either don't support selectionStart (returns null,
  // not an exception) or don't need edge-checking, so treat them as "always
  // at the edge" and let plain Left/Right move between cells immediately.
  var FREE_CARET_TYPES = ['text', 'search', 'tel', 'email', 'url'];

  function hasFreeCaret(el) {
    if (el.tagName === 'TEXTAREA') return true;
    if (el.tagName === 'INPUT') return FREE_CARET_TYPES.indexOf((el.type || 'text').toLowerCase()) !== -1;
    return false;
  }
  function atLeftEdge(el) {
    if (!hasFreeCaret(el)) return true;
    var s = el.selectionStart, en = el.selectionEnd;
    if (s === null || s === undefined || en === null || en === undefined) return true;
    return s === 0 && en === 0;
  }
  function atRightEdge(el) {
    if (!hasFreeCaret(el)) return true;
    var s = el.selectionStart, en = el.selectionEnd;
    if (s === null || s === undefined || en === null || en === undefined) return true;
    var len = (el.value || '').length;
    return s === len && en === len;
  }

  document.addEventListener('keydown', function (e) {
    const el = e.target;
    if (!isEditable(el)) return;
    const table = el.closest('table');
    if (!table) return;
    const grid = buildGrid(table);
    const pos = findPos(grid, el);
    if (!pos || grid.length < 2) return;
    const r = pos.r, c = pos.c;
    const key = e.key;

    function goRow(nr) {
      nr = Math.max(0, Math.min(nr, grid.length - 1));
      const target = grid[nr][Math.min(c, grid[nr].length - 1)];
      if (focusCell(target)) e.preventDefault();
    }

    if (key === 'ArrowDown') {
      goRow(r + (e.ctrlKey ? 10 : 1));
    } else if (key === 'ArrowUp') {
      goRow(r - (e.ctrlKey ? 10 : 1));
    } else if (key === 'PageDown') {
      goRow(r + 10);
    } else if (key === 'PageUp') {
      goRow(r - 10);
    } else if (key === 'Enter' && el.tagName !== 'SELECT') {
      goRow(r + (e.shiftKey ? -1 : 1));
    } else if (key === 'ArrowRight') {
      if (e.ctrlKey) {
        if (focusCell(grid[r][grid[r].length - 1])) e.preventDefault();
      } else if (atRightEdge(el)) {
        if (c < grid[r].length - 1) { if (focusCell(grid[r][c + 1])) e.preventDefault(); }
        else if (r < grid.length - 1) { if (focusCell(grid[r + 1][0])) e.preventDefault(); }
      }
    } else if (key === 'ArrowLeft') {
      if (e.ctrlKey) {
        if (focusCell(grid[r][0])) e.preventDefault();
      } else if (atLeftEdge(el)) {
        if (c > 0) { if (focusCell(grid[r][c - 1])) e.preventDefault(); }
        else if (r > 0) { const pr = grid[r - 1]; if (focusCell(pr[pr.length - 1])) e.preventDefault(); }
      }
    } else if (key === 'Home' && e.ctrlKey) {
      if (focusCell(grid[0][0])) e.preventDefault();
    } else if (key === 'End' && e.ctrlKey) {
      const lr = grid[grid.length - 1];
      if (focusCell(lr[lr.length - 1])) e.preventDefault();
    }
  });

  function injectStyle() {
    if (document.getElementById('keynav-style')) return;
    const s = document.createElement('style');
    s.id = 'keynav-style';
    s.textContent =
      '.keynav-row-active{background:#fff7cc !important;transition:background .6s ease;}' +
      'table input:focus,table select:focus,table textarea:focus{' +
      'outline:2px solid #2563eb !important;outline-offset:-1px;' +
      'box-shadow:0 0 0 2px rgba(37,99,235,.25) !important;position:relative;z-index:2;}' +
      '.keynav-hint{font-size:11.5px;color:#6b7280;margin:4px 0 8px;display:flex;' +
      'align-items:center;gap:5px;flex-wrap:wrap;}' +
      '.keynav-hint kbd{background:#f3f4f6;border:1px solid #d1d5db;border-bottom-width:2px;' +
      'border-radius:4px;padding:1px 6px;font-size:11px;font-family:inherit;color:#374151;}';
    document.head.appendChild(s);
  }

  function injectHints() {
    document.querySelectorAll('table').forEach(function (table) {
      if (table.dataset.keynavHinted) return;
      const grid = buildGrid(table);
      if (grid.length < 2) return;
      table.dataset.keynavHinted = '1';
      const hint = document.createElement('div');
      hint.className = 'keynav-hint';
      hint.innerHTML =
        '<kbd>&uarr;</kbd><kbd>&darr;</kbd><kbd>&larr;</kbd><kbd>&rarr;</kbd> move&nbsp;&nbsp;' +
        '<kbd>Enter</kbd> next row&nbsp;&nbsp;<kbd>Shift</kbd>+<kbd>Enter</kbd> prev row&nbsp;&nbsp;' +
        '<kbd>Ctrl</kbd>+<kbd>&darr;</kbd>/<kbd>&uarr;</kbd> jump 10 rows&nbsp;&nbsp;' +
        '<kbd>Ctrl</kbd>+<kbd>Home</kbd>/<kbd>End</kbd> first / last cell';
      if (table.parentNode) table.parentNode.insertBefore(hint, table);
    });
  }

  function init() {
    injectStyle();
    injectHints();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  // Re-scan for hints if tables are rebuilt dynamically after AJAX row-adds.
  window.keynavRescan = injectHints;
})();
