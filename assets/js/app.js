/* ===== Aptech Portal — App JS ===== */

// ─── TOAST ──────────────────────────────────────────────────
let toastTimer;
function showToast(title, body, type = 'success') {
  const el = document.getElementById('toast');
  if (!el) return;
  el.style.borderLeftColor = type === 'error' ? 'var(--red)' : 'var(--cyan)';
  document.getElementById('toast-title').textContent = title;
  document.getElementById('toast-body').textContent  = body;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3800);
}

// ─── MODAL ──────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
});

// ─── ATTENDANCE PILLS ───────────────────────────────────────
function setAtt(studentId, val) {
  ['P','L','A'].forEach(v => {
    const btn = document.getElementById('pb-' + v + '-' + studentId);
    if (!btn) return;
    btn.className = 'att-pill';
    if (v === val) btn.className = 'att-pill ' + (v==='P'?'p-on':v==='A'?'a-on':'l-on');
  });
  // update hidden input
  const inp = document.getElementById('att-' + studentId);
  if (inp) inp.value = val;
  updateAttCounts();
}

function updateAttCounts() {
  const inputs = document.querySelectorAll('input[name^="attendance["]');
  let p=0, a=0, l=0;
  inputs.forEach(i => { if(i.value==='P') p++; else if(i.value==='A') a++; else if(i.value==='L') l++; });
  const pc=document.getElementById('cnt-present');
  const ac=document.getElementById('cnt-absent');
  const lc=document.getElementById('cnt-late');
  const tc=document.getElementById('cnt-total');
  if(pc) pc.textContent = p + ' present';
  if(ac) ac.textContent = a + ' absent';
  if(lc) lc.textContent = l + ' late';
  if(tc) tc.textContent = (p+a+l) + ' students';
}

function markAll(val) {
  document.querySelectorAll('input[name^="attendance["]').forEach(inp => {
    setAtt(inp.dataset.sid, val);
  });
}

// ─── STUDENT SEARCH/FILTER (client-side) ────────────────────
function filterTable(inputEl, tableId) {
  const q = inputEl.value.toLowerCase();
  const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function filterByModule(chipEl, mod, tableId) {
  document.querySelectorAll('.chip[data-filter]').forEach(c => c.classList.remove('active'));
  chipEl.classList.add('active');
  const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
  rows.forEach(row => {
    row.style.display = (mod === 'all' || row.dataset.mod === mod) ? '' : 'none';
  });
}

// ─── CONFIRM DELETE ─────────────────────────────────────────
function confirmDelete(msg, formId) {
  if (confirm(msg || 'Are you sure? This cannot be undone.')) {
    document.getElementById(formId).submit();
  }
}

// ─── FLASH AUTO-DISMISS ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const flash = document.querySelector('.flash');
  if (flash) setTimeout(() => flash.style.opacity = '0', 4000);

  // Animate progress bars
  document.querySelectorAll('.prog-fill[data-pct]').forEach(el => {
    const pct = parseFloat(el.dataset.pct) || 0;
    setTimeout(() => { el.style.width = pct + '%'; }, 100);
  });
});
