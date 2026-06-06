/**
 * BHLD Web App - Main Application Logic
 * Bảo Hộ Lao Động - Web SPA
 */

// ===== STATE =====
const State = {
  employees: [],
  equipment: [],
  certificates: [],
  certDetailsMap: {}, // mact -> []
  selectedEquipment: new Map(), // key "mact-mavt" -> {mact, mavt, tenvt}
  currentCertModal: null, // cert being edited
  currentCertDetailMgmt: null, // mact for detail management
  currentEmpEdit: null, // employee being edited
  currentConfirmCallback: null,
  currentBulkAllocateItems: [],
  lastReportData: null,
  lastReportMonthStr: null,
  dinhMucData: [], // cache danh sách định mức + chitiet
};

// ===== UTILS =====
function today() {
  return new Date().toISOString().split('T')[0];
}

function formatDate(str) {
  if (!str || str === '1911-11-11') return '—';
  try {
    const d = new Date(str);
    if (isNaN(d)) return str;
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
  } catch { return str; }
}

function calcNgayTiepTheo(ngnhan, dmtg) {
  if (!ngnhan || ngnhan === '1911-11-11' || !dmtg) return '—';
  try {
    const d = new Date(ngnhan);
    if (isNaN(d)) return '—';
    d.setMonth(d.getMonth() + parseInt(dmtg));
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
  } catch { return '—'; }
}

function escHtml(str) {
  if (str == null) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function showToast(message, type = 'success') {
  const container = document.getElementById('toastContainer');
  const id = 'toast_' + Date.now();
  const icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
  const colors = { success: 'text-bg-success', danger: 'text-bg-danger', warning: 'text-bg-warning', info: 'text-bg-info' };
  const html = `
    <div id="${id}" class="toast align-items-center ${colors[type] || 'text-bg-secondary'} border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2">
          <i class="bi ${icons[type] || 'bi-info-circle-fill'}"></i>
          <span>${escHtml(message)}</span>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>`;
  container.insertAdjacentHTML('beforeend', html);
  const el = document.getElementById(id);
  const t = new bootstrap.Toast(el, { delay: 4000 });
  t.show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
}

function showConfirm(message, onConfirm) {
  document.getElementById('confirmModalMsg').textContent = message;
  State.currentConfirmCallback = onConfirm;
  const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmModal'));
  m.show();
}

function setLoading(id, show) {
  const el = document.getElementById(id);
  if (el) el.classList.toggle('d-none', !show);
}

function getModal(id) {
  return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

// ===== CLOCK =====
function updateClock() {
  const el = document.getElementById('navClock');
  if (el) el.textContent = new Date().toLocaleString('vi-VN');
}
setInterval(updateClock, 1000);
updateClock();

// ===== TAB NAVIGATION =====
function initTabs() {
  document.querySelectorAll('#mainTabs .nav-link').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const tab = link.dataset.tab;
      document.querySelectorAll('#mainTabs .nav-link').forEach(l => l.classList.remove('active'));
      link.classList.add('active');
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('d-none'));
      document.getElementById(`tab-${tab}`).classList.remove('d-none');
      if (tab === 'certificates') initCertificatesTab();
      if (tab === 'management') initManagementTab();
      if (tab === 'employees') initEmployeesTab();
      if (tab === 'reports') initReportsTab();
      if (tab === 'changelog') initChangelogTab();
      if (tab === 'allocate') initAllocateTab();
    });
  });
}

// ====================================================================
// TAB 1: CHỨNG TỪ
// ====================================================================
let ctInitialized = false;

async function initCertificatesTab() {
  if (!ctInitialized) {
    ctInitialized = true;
    // Load employees for the select dropdown
    await loadEmployeesForSelect();
    // Set default dates (current month)
    const now = new Date();
    const firstDay = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
    document.getElementById('ct-from-date').value = firstDay;
    document.getElementById('ct-to-date').value = today();
    // Event listeners
    document.getElementById('ct-search-btn').addEventListener('click', loadCertificates);
    document.getElementById('ct-clear-btn').addEventListener('click', () => {
      document.getElementById('ct-emp-select').value = '';
      document.getElementById('ct-mact-search').value = '';
      document.getElementById('ct-from-date').value = '';
      document.getElementById('ct-to-date').value = '';
      loadCertificates();
    });
    document.getElementById('ct-bulk-allocate-btn').addEventListener('click', openBulkAllocateModal);
  }
  loadCertificates();
}

async function loadEmployeesForSelect() {
  try {
    const res = await API.getEmployees();
    if (res.success && res.data) {
      State.employees = res.data;
      const select = document.getElementById('ct-emp-select');
      const current = select.value;
      select.innerHTML = '<option value="">-- Tất cả nhân viên --</option>';
      res.data.forEach(emp => {
        select.insertAdjacentHTML('beforeend',
          `<option value="${escHtml(emp.manv)}">${escHtml(emp.manv)} - ${escHtml(emp.tennhanvien)}</option>`);
      });
      select.value = current;
    }
  } catch (err) {
    console.warn('Không thể tải danh sách nhân viên:', err.message);
  }
}

async function loadCertificates() {
  setLoading('ct-loading', true);
  document.getElementById('ct-list').innerHTML = '';
  document.getElementById('ct-empty').classList.add('d-none');
  State.selectedEquipment.clear();
  updateBulkBtn();

  const params = {
    manv: document.getElementById('ct-emp-select').value,
    mact_search: document.getElementById('ct-mact-search').value,
    from_date: document.getElementById('ct-from-date').value,
    to_date: document.getElementById('ct-to-date').value,
  };

  try {
    const res = await API.getCertificates(params);
    if (res.success && res.data) {
      State.certificates = res.data;
      if (res.data.length === 0) {
        document.getElementById('ct-empty').classList.remove('d-none');
      } else {
        renderCertificates(res.data);
        // Load details for all
        for (const cert of res.data) {
          loadCertDetailsForView(cert.mact);
        }
      }
    }
  } catch (err) {
    showToast('Lỗi tải chứng từ: ' + err.message, 'danger');
  } finally {
    setLoading('ct-loading', false);
  }
}

function renderCertificates(certs) {
  const container = document.getElementById('ct-list');
  container.innerHTML = '';
  certs.forEach(cert => {
    const card = document.createElement('div');
    card.className = 'cert-card card shadow-sm mb-3';
    card.dataset.mact = cert.mact;
    card.innerHTML = `
      <div class="card-header cert-card-header d-flex align-items-center gap-2 flex-wrap">
        <div class="d-flex align-items-center gap-2 flex-grow-1">
          <i class="bi bi-file-earmark-text text-primary"></i>
          <span class="fw-semibold text-primary">${escHtml(cert.mact)}</span>
          <span class="badge bg-secondary ms-1">${escHtml(cert.madm)}</span>
        </div>
        <div class="d-flex gap-3 text-muted small flex-wrap align-items-center">
          <span><i class="bi bi-calendar3 me-1"></i>${formatDate(cert.ngct)}</span>
          <span><i class="bi bi-person me-1"></i>${escHtml(cert.manv)} ${cert.tennhanvien ? '– ' + escHtml(cert.tennhanvien) : ''}</span>
          <span><i class="bi bi-building me-1"></i>${escHtml(cert.mapb)}${cert.tenphongban ? ' – ' + escHtml(cert.tenphongban) : ''}</span>
          <button class="btn btn-sm btn-outline-primary py-0 ms-1" onclick="printCertificate('${escHtml(cert.mact)}')" title="In chứng từ">
            <i class="bi bi-printer me-1"></i>In
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div id="details-${escHtml(cert.mact)}" class="cert-details-wrap">
          <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-secondary"></div></div>
        </div>
      </div>`;
    container.appendChild(card);
  });
}

async function loadCertDetailsForView(mact) {
  try {
    const res = await API.getCertificateDetails(mact);
    if (res.success && res.data) {
      State.certDetailsMap[mact] = res.data;
      renderCertDetails(mact, res.data);
    }
  } catch (err) {
    const wrap = document.getElementById(`details-${mact}`);
    if (wrap) wrap.innerHTML = `<div class="text-danger small p-3">Lỗi tải chi tiết: ${escHtml(err.message)}</div>`;
  }
}

function renderCertDetails(mact, details) {
  const wrap = document.getElementById(`details-${mact}`);
  if (!wrap) return;
  if (!details || details.length === 0) {
    wrap.innerHTML = '<div class="text-muted small p-3 text-center">Chưa có vật tư trong chứng từ này</div>';
    return;
  }

  const rows = details.map(d => {
    const key = `${mact}-${d.mavt}`;
    const isAllocated = d.sl > 0;
    const isChecked = State.selectedEquipment.has(key);
    const now = new Date();

    let statusBadge;
    if (!isAllocated) {
      statusBadge = '<span class="badge bg-secondary">Chưa cấp</span>';
    } else {
      const due = new Date(d.ngnhan);
      due.setMonth(due.getMonth() + parseInt(d.dmtg || 0));
      if (d.ngnhan && !isNaN(due) && now > due) statusBadge = '<span class="badge bg-success">Đã nhận</span>';
      else statusBadge = '<span class="badge bg-success">Đã nhận</span>';
    }

    let actionBtn = '';
    if (isAllocated) {
      actionBtn = `<button class="btn btn-sm btn-outline-danger" onclick="confirmDeallocate('${escHtml(mact)}',${d.mavt},'${escHtml(d.tenvt||'')}')">
        <i class="bi bi-box-arrow-up me-1"></i>Trả lại
      </button>`;
    } else {
      actionBtn = `<button class="btn btn-sm btn-outline-success" onclick="openAllocateModal('${escHtml(mact)}',${d.mavt},'${escHtml(d.tenvt||'')}')">
        <i class="bi bi-box-arrow-in-down me-1"></i>Cấp phát
      </button>`;
    }

    const checkboxHtml = !isAllocated
      ? `<input type="checkbox" class="form-check-input equip-check" data-key="${escHtml(key)}" data-mact="${escHtml(mact)}" data-mavt="${d.mavt}" data-tenvt="${escHtml(d.tenvt||'')}" ${isChecked ? 'checked' : ''} />`
      : '';

    return `<tr>
      <td class="ps-3">${checkboxHtml}</td>
      <td>
        <div class="fw-medium">${escHtml(d.tenvt || 'Mã: ' + d.mavt)}</div>
        <div class="text-muted small">${escHtml(d.dvt || '')}</div>
      </td>
      <td class="text-center">${d.sl}</td>
      <td class="text-center">${d.dmtg} tháng</td>
      <td>${formatDate(d.ngnhan)}</td>
      <td>${calcNgayTiepTheo(d.ngnhan, d.dmtg)}</td>
      <td>${statusBadge}</td>
      <td class="text-end pe-3">${actionBtn}</td>
    </tr>`;
  }).join('');

  wrap.innerHTML = `
    <table class="table table-sm align-middle mb-0 cert-detail-table">
      <thead class="table-light">
        <tr>
          <th class="ps-3" style="width:36px"></th>
          <th>Vật tư</th>
          <th class="text-center">SL</th>
          <th class="text-center">ĐMTG</th>
          <th>Ngày nhận</th>
          <th>Ngày nhận tiếp theo</th>
          <th>Trạng thái</th>
          <th class="text-end pe-3">Thao tác</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>`;

  // Attach checkbox events
  wrap.querySelectorAll('.equip-check').forEach(cb => {
    cb.addEventListener('change', () => {
      const k = cb.dataset.key;
      if (cb.checked) {
        State.selectedEquipment.set(k, { mact: cb.dataset.mact, mavt: parseInt(cb.dataset.mavt), tenvt: cb.dataset.tenvt });
      } else {
        State.selectedEquipment.delete(k);
      }
      updateBulkBtn();
    });
  });
}

function updateBulkBtn() {
  const count = State.selectedEquipment.size;
  document.getElementById('ct-selected-count').textContent = count;
  document.getElementById('ct-bulk-allocate-btn').disabled = count === 0;
}

function openAllocateModal(mact, mavt, tenvt) {
  document.getElementById('allocateModalTitle').textContent = `Cấp phát: ${tenvt || 'Mã ' + mavt}`;
  document.getElementById('allocate-info').innerHTML =
    `<strong>Chứng từ:</strong> ${escHtml(mact)}<br><strong>Vật tư:</strong> ${escHtml(tenvt || 'mavt: ' + mavt)}`;
  document.getElementById('allocate-date').value = today();

  const btn = document.getElementById('allocate-confirm-btn');
  btn.onclick = async () => {
    const ngnhan = document.getElementById('allocate-date').value;
    if (!ngnhan) { showToast('Vui lòng chọn ngày nhận', 'warning'); return; }
    btn.disabled = true;
    try {
      const res = await API.allocate(mact, mavt, ngnhan);
      if (res.success) {
        showToast('Cấp phát thành công!', 'success');
        getModal('allocateModal').hide();
        loadCertDetailsForView(mact);
      } else {
        showToast(res.message || 'Cấp phát thất bại', 'danger');
      }
    } catch (err) {
      showToast('Lỗi: ' + err.message, 'danger');
    } finally {
      btn.disabled = false;
    }
  };
  getModal('allocateModal').show();
}

function confirmDeallocate(mact, mavt, tenvt) {
  showConfirm(`Xác nhận trả lại: "${tenvt}" thuộc chứng từ "${mact}"?`, async () => {
    try {
      const res = await API.deallocate(mact, mavt);
      if (res.success) {
        showToast('Đã trả thiết bị thành công', 'success');
        loadCertDetailsForView(mact);
      } else {
        showToast(res.message || 'Thao tác thất bại', 'danger');
      }
    } catch (err) {
      showToast('Lỗi: ' + err.message, 'danger');
    }
  });
}

function openBulkAllocateModal() {
  if (State.selectedEquipment.size === 0) return;
  document.getElementById('bulk-alloc-date').value = today();

  // Group by mact
  const grouped = {};
  State.selectedEquipment.forEach((item, key) => {
    if (!grouped[item.mact]) grouped[item.mact] = [];
    grouped[item.mact].push(item);
  });

  const listHtml = Object.entries(grouped).map(([mact, items]) => `
    <div class="alert alert-secondary py-2 mb-2">
      <strong><i class="bi bi-file-earmark-text me-1"></i>${escHtml(mact)}</strong>
      <ul class="mb-0 mt-1">
        ${items.map(i => `<li class="small">${escHtml(i.tenvt || 'Mã: ' + i.mavt)}</li>`).join('')}
      </ul>
    </div>`).join('');

  document.getElementById('bulk-alloc-list').innerHTML = listHtml;

  const btn = document.getElementById('bulk-alloc-confirm-btn');
  btn.onclick = async () => {
    const ngnhan = document.getElementById('bulk-alloc-date').value;
    if (!ngnhan) { showToast('Vui lòng chọn ngày nhận', 'warning'); return; }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
    let successCount = 0, failCount = 0;
    for (const [key, item] of State.selectedEquipment) {
      try {
        const res = await API.allocate(item.mact, item.mavt, ngnhan);
        if (res.success) successCount++; else failCount++;
      } catch { failCount++; }
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Cấp phát tất cả';

    showToast(`Thành công: ${successCount}, Thất bại: ${failCount}`, successCount > 0 ? 'success' : 'danger');
    getModal('bulkAllocateModal').hide();
    State.selectedEquipment.clear();
    updateBulkBtn();

    // Reload all affected certs
    const affectedMacts = new Set([...State.selectedEquipment.values()].map(i => i.mact));
    Object.keys(grouped).forEach(mact => loadCertDetailsForView(mact));
  };

  getModal('bulkAllocateModal').show();
}

// ====================================================================
// TAB 2: QUẢN LÝ
// ====================================================================
let mgInitialized = false;
let mgAllCerts = [];

async function initManagementTab() {
  if (!mgInitialized) {
    mgInitialized = true;
    document.getElementById('mg-search-btn').addEventListener('click', filterMgTable);
    document.getElementById('mg-search').addEventListener('keydown', e => { if (e.key === 'Enter') filterMgTable(); });
    document.getElementById('mg-reload-btn').addEventListener('click', loadMgCerts);
    document.getElementById('mg-add-btn').addEventListener('click', () => openCertModal(null));
    document.getElementById('cert-save-btn').addEventListener('click', saveCert);
  }
  loadMgCerts();
}

async function loadMgCerts() {
  setLoading('mg-loading', true);
  document.getElementById('mg-tbody').innerHTML = '';
  document.getElementById('mg-empty').classList.add('d-none');
  try {
    const res = await API.getCertificates({ limit: 10000 });
    if (res.success && res.data) {
      mgAllCerts = res.data;
      renderMgTable(mgAllCerts);
    }
  } catch (err) {
    showToast('Lỗi tải chứng từ: ' + err.message, 'danger');
  } finally {
    setLoading('mg-loading', false);
  }
}

function filterMgTable() {
  const q = document.getElementById('mg-search').value.toLowerCase().trim();
  if (!q) { renderMgTable(mgAllCerts); return; }
  const filtered = mgAllCerts.filter(c =>
    (c.mact||'').toLowerCase().includes(q) ||
    (c.manv||'').toLowerCase().includes(q) ||
    (c.tennhanvien||'').toLowerCase().includes(q) ||
    (c.mapb||'').toLowerCase().includes(q) ||
    (c.madm||'').toLowerCase().includes(q)
  );
  renderMgTable(filtered);
}

function renderMgTable(certs) {
  const tbody = document.getElementById('mg-tbody');
  document.getElementById('mg-empty').classList.toggle('d-none', certs.length > 0);
  if (certs.length === 0) { tbody.innerHTML = ''; return; }

  tbody.innerHTML = certs.map(c => `
    <tr>
      <td><span class="fw-semibold text-primary">${escHtml(c.mact)}</span></td>
      <td>${formatDate(c.ngct)}</td>
      <td>${escHtml(c.manv)}</td>
      <td>${escHtml(c.tennhanvien || '—')}</td>
      <td>${escHtml(c.mapb)}${c.tenphongban ? ` <span class="text-muted small">- ${escHtml(c.tenphongban)}</span>` : ''}</td>
      <td><span class="badge bg-info text-dark">${escHtml(c.madm)}</span></td>
      <td class="text-muted small">${escHtml(c.ghichu || '')}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-success me-1" onclick="printCertificate('${escHtml(c.mact)}')" title="In chứng từ">
          <i class="bi bi-printer"></i>
        </button>
        <button class="btn btn-sm btn-outline-primary me-1" onclick="openCertDetailMgmt('${escHtml(c.mact)}')">
          <i class="bi bi-list-ul"></i> Chi tiết
        </button>
        <button class="btn btn-sm btn-outline-secondary me-1" onclick="openCertModal(${JSON.stringify(escHtml(c.mact))})">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-sm btn-outline-danger" onclick="deleteCert('${escHtml(c.mact)}')">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    </tr>`).join('');
}

function openCertModal(mactOrNull) {
  const cert = mactOrNull ? mgAllCerts.find(c => c.mact === mactOrNull) : null;
  State.currentCertModal = cert;
  document.getElementById('certModalTitle').textContent = cert ? 'Sửa chứng từ' : 'Thêm chứng từ mới';
  document.getElementById('cert-mact').value = cert?.mact || '';
  document.getElementById('cert-mact').disabled = !!cert;
  document.getElementById('cert-manv').value = cert?.manv || '';
  document.getElementById('cert-ngct').value = cert?.ngct?.split(' ')[0] || today();
  document.getElementById('cert-mapb').value = cert?.mapb || '';
  document.getElementById('cert-madm').value = cert?.madm || '';
  document.getElementById('cert-ghichu').value = cert?.ghichu || '';
  getModal('certModal').show();
}

async function saveCert() {
  const mact = document.getElementById('cert-mact').value.trim();
  const manv = document.getElementById('cert-manv').value.trim();
  const ngct = document.getElementById('cert-ngct').value;
  const mapb = document.getElementById('cert-mapb').value.trim();
  const madm = document.getElementById('cert-madm').value.trim();
  const ghichu = document.getElementById('cert-ghichu').value.trim();

  if (!mact || !manv || !ngct || !mapb || !madm) {
    showToast('Vui lòng điền đầy đủ các trường bắt buộc (*)', 'warning');
    return;
  }

  const btn = document.getElementById('cert-save-btn');
  btn.disabled = true;
  try {
    const payload = { mact, manv, ngct, mapb, madm, ghichu: ghichu || undefined };
    const isEdit = !!State.currentCertModal;
    const res = isEdit ? await API.updateCertificate(payload) : await API.createCertificate(payload);
    if (res.success) {
      showToast(isEdit ? 'Cập nhật chứng từ thành công!' : 'Tạo chứng từ thành công!', 'success');
      getModal('certModal').hide();
      loadMgCerts();
    } else {
      showToast(res.message || 'Thao tác thất bại', 'danger');
    }
  } catch (err) {
    showToast('Lỗi: ' + err.message, 'danger');
  } finally {
    btn.disabled = false;
  }
}

function deleteCert(mact) {
  showConfirm(`Xóa chứng từ "${mact}"? Tất cả vật tư liên quan cũng sẽ bị xóa.`, async () => {
    try {
      const res = await API.deleteCertificate(mact);
      if (res.success) {
        showToast('Đã xóa chứng từ', 'success');
        loadMgCerts();
      } else {
        showToast(res.message || 'Xóa thất bại', 'danger');
      }
    } catch (err) {
      showToast('Lỗi: ' + err.message, 'danger');
    }
  });
}

// ===== CERT DETAIL MANAGEMENT =====
let cdmEquipmentList = [];

async function openCertDetailMgmt(mact) {
  State.currentCertDetailMgmt = mact;
  const cert = mgAllCerts.find(c => c.mact === mact);
  document.getElementById('certDetailMgmtTitle').textContent = `Chi tiết: ${mact}`;
  document.getElementById('certDetailMgmtInfo').textContent =
    cert ? `${cert.manv} – ${cert.tennhanvien || ''} | ${cert.mapb}` : '';
  document.getElementById('cdm-tbody').innerHTML = '';
  setLoading('cdm-loading', true);

  // Load equipment list for the add form
  if (cdmEquipmentList.length === 0) {
    try {
      const res = await API.getEquipment();
      if (res.success && res.data) cdmEquipmentList = res.data;
    } catch {}
  }

  getModal('certDetailMgmtModal').show();
  await loadCdmDetails(mact);
  setLoading('cdm-loading', false);
}

async function loadCdmDetails(mact) {
  setLoading('cdm-loading', true);
  try {
    const res = await API.getCertificateDetails(mact);
    if (res.success && res.data) renderCdmTable(mact, res.data);
  } catch (err) {
    showToast('Lỗi tải chi tiết: ' + err.message, 'danger');
  } finally {
    setLoading('cdm-loading', false);
  }
}

function renderCdmTable(mact, details) {
  const tbody = document.getElementById('cdm-tbody');
  if (!details || details.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Chưa có vật tư</td></tr>';
    return;
  }
  tbody.innerHTML = details.map(d => {
    const isAllocated = d.sl > 0;
    const now = new Date();
    let status;
    if (!isAllocated) {
      status = '<span class="badge bg-secondary">Chưa cấp</span>';
    } else {
      const due = new Date(d.ngnhan);
      due.setMonth(due.getMonth() + parseInt(d.dmtg || 0));
      if (d.ngnhan && !isNaN(due) && now > due) status = '<span class="badge bg-success">Đã nhận</span>';
      else status = '<span class="badge bg-success">Đã nhận</span>';
    }
    return `<tr>
      <td>${escHtml(d.tenvt || 'Mã: ' + d.mavt)}</td>
      <td>${escHtml(d.dvt || '')}</td>
      <td class="text-center">${d.dmtg}</td>
      <td class="text-center">${d.sl}</td>
      <td>${formatDate(d.ngnhan)}</td>
      <td>${calcNgayTiepTheo(d.ngnhan, d.dmtg)}</td>
      <td>${status}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-secondary me-1" onclick="openCdiModal('${escHtml(mact)}',${d.mavt})">
          <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-sm btn-outline-danger" onclick="deleteCdItem('${escHtml(mact)}',${d.mavt},'${escHtml(d.tenvt||'')}')">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    </tr>`;
  }).join('');
}

function openCdiModal(mact, mavtOrNull) {
  const details = [];
  // Find existing detail
  let existingDetail = null;
  if (mavtOrNull) {
    const allDetails = document.querySelectorAll(`#cdm-tbody tr`);
    // We'll just use state from current load - reload details
  }

  document.getElementById('cdiModalTitle').textContent = mavtOrNull ? 'Sửa vật tư' : 'Thêm vật tư';
  // Populate equipment select
  const select = document.getElementById('cdi-mavt');
  select.innerHTML = cdmEquipmentList.map(eq =>
    `<option value="${eq.mavt}" ${mavtOrNull === eq.mavt ? 'selected' : ''}>${escHtml(eq.tenvt)} (${escHtml(eq.dvt || 'cái')})</option>`
  ).join('');
  if (mavtOrNull) select.disabled = true; else select.disabled = false;

  document.getElementById('cdi-dmtg').value = 12;
  document.getElementById('cdi-sl').value = 1;
  document.getElementById('cdi-ngnhan').value = today();
  document.getElementById('cdi-ngnhantt').value = '1911-11-11';

  // If editing, find in cached details
  const cachedDetails = State.certDetailsMap[mact] || [];
  const existing = cachedDetails.find(d => d.mavt === mavtOrNull);
  if (existing) {
    document.getElementById('cdi-dmtg').value = existing.dmtg;
    document.getElementById('cdi-sl').value = existing.sl;
    document.getElementById('cdi-ngnhan').value = existing.ngnhan !== '1911-11-11' ? existing.ngnhan : today();
    document.getElementById('cdi-ngnhantt').value = existing.ngnhantt !== '1911-11-11' ? existing.ngnhantt : '';
  }

  const btn = document.getElementById('cdi-save-btn');
  btn.onclick = async () => {
    const payload = {
      mact,
      mavt: parseInt(select.value),
      dmtg: parseInt(document.getElementById('cdi-dmtg').value) || 12,
      sl: parseInt(document.getElementById('cdi-sl').value) || 1,
      ngnhan: document.getElementById('cdi-ngnhan').value || today(),
      ngnhantt: document.getElementById('cdi-ngnhantt').value || '1911-11-11',
    };

    btn.disabled = true;
    try {
      const res = mavtOrNull ? await API.updateCertificateDetail(payload) : await API.createCertificateDetail(payload);
      if (res.success) {
        showToast('Đã lưu vật tư', 'success');
        getModal('certDetailItemModal').hide();
        loadCdmDetails(mact);
        // Also refresh view tab if open
        loadCertDetailsForView(mact);
      } else {
        showToast(res.message || 'Thao tác thất bại', 'danger');
      }
    } catch (err) {
      showToast('Lỗi: ' + err.message, 'danger');
    } finally {
      btn.disabled = false;
    }
  };
  getModal('certDetailItemModal').show();
}

function deleteCdItem(mact, mavt, tenvt) {
  showConfirm(`Xóa vật tư "${tenvt}" khỏi chứng từ "${mact}"?`, async () => {
    try {
      const res = await API.deleteCertificateDetail(mact, mavt);
      if (res.success) {
        showToast('Đã xóa vật tư', 'success');
        loadCdmDetails(mact);
        loadCertDetailsForView(mact);
      } else {
        showToast(res.message || 'Xóa thất bại', 'danger');
      }
    } catch (err) {
      showToast('Lỗi: ' + err.message, 'danger');
    }
  });
}

// Hook up cdm-add-btn
document.getElementById('cdm-add-btn').addEventListener('click', () => {
  if (State.currentCertDetailMgmt) openCdiModal(State.currentCertDetailMgmt, null);
});

// ====================================================================
// TAB 3: NHÂN VIÊN
// ====================================================================
let empInitialized = false;
let empAllList = [];
let empRenderedList = []; // list đang render (để tra theo index)

function openEmpModalByIdx(idx) {
  const emp = empRenderedList[idx];
  if (emp) openEmpModal(emp);
}

async function initEmployeesTab() {
  if (!empInitialized) {
    empInitialized = true;
    document.getElementById('emp-pb-filter').addEventListener('change', applyEmpFilter);
    document.getElementById('emp-search-btn').addEventListener('click', applyEmpFilter);
    document.getElementById('emp-search').addEventListener('keydown', e => {
      if (e.key === 'Enter') applyEmpFilter();
    });
    document.getElementById('emp-reload-btn').addEventListener('click', () => loadEmployees());
    document.getElementById('emp-add-btn').addEventListener('click', () => openEmpModal(null));
    document.getElementById('emp-save-btn').addEventListener('click', saveEmployee);
    // Restore các field bị ẩn khi modal đóng
    document.getElementById('empModal').addEventListener('hidden.bs.modal', () => {
      ['emp-manv-input','emp-ten-input','emp-mapb-input'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.closest('.col-md-6').classList.remove('d-none'); el.disabled = false; }
      });
      document.getElementById('emp-first-ct-section').classList.remove('d-none');
    });
  }
  loadEmployees();
}

function applyEmpFilter() {
  const q = document.getElementById('emp-search').value.trim().toLowerCase();
  const pb = document.getElementById('emp-pb-filter').value;
  let list = empAllList;
  if (pb) list = list.filter(e => e.mapb === pb);
  if (q) list = list.filter(e =>
    (e.manv||'').toLowerCase().includes(q) ||
    (e.tennhanvien||'').toLowerCase().includes(q)
  );
  renderEmployeeTable(list);
}

async function loadEmployees(search) {
  setLoading('emp-loading', true);
  document.getElementById('emp-tbody').innerHTML = '';
  document.getElementById('emp-empty').classList.add('d-none');
  try {
    const res = await API.getEmployees(search);
    if (res.success && res.data) {
      empAllList = res.data;
      // Populate phòng ban dropdown (chỉ lần đầu)
      const sel = document.getElementById('emp-pb-filter');
      if (sel.options.length <= 1) {
        const pbs = [...new Map(res.data.filter(e => e.mapb).map(e => [e.mapb, e])).values()]
          .sort((a, b) => (a.mapb||'').localeCompare(b.mapb||''));
        pbs.forEach(e => {
          sel.insertAdjacentHTML('beforeend',
            `<option value="${escHtml(e.mapb)}">${escHtml(e.mapb)} - ${escHtml(e.tenphongban||e.mapb)}</option>`);
        });
        // Populate modal phòng ban select
        const modalPbSel = document.getElementById('emp-mapb-input');
        if (modalPbSel && modalPbSel.tagName === 'SELECT' && modalPbSel.options.length <= 1) {
          pbs.forEach(e => {
            modalPbSel.insertAdjacentHTML('beforeend',
              `<option value="${escHtml(e.mapb)}">${escHtml(e.mapb)} - ${escHtml(e.tenphongban||e.mapb)}</option>`);
          });
        }
      }
      renderEmployeeTable(res.data);
    }
  } catch (err) {
    showToast('Lỗi tải nhân viên: ' + err.message, 'danger');
  } finally {
    setLoading('emp-loading', false);
  }
}

function renderEmployeeTable(list) {
  const tbody = document.getElementById('emp-tbody');
  list = list.filter(emp => emp.tennhanvien && emp.tennhanvien.trim() !== '');
  empRenderedList = list;
  document.getElementById('emp-empty').classList.toggle('d-none', list.length > 0);
  if (list.length === 0) { tbody.innerHTML = ''; return; }
  tbody.innerHTML = list.map((emp, idx) => {
    const key = `emp-row-${idx}`;
    return `
    <tr data-emp-idx="${idx}">
      <td><span class="badge bg-primary bg-opacity-10 text-primary fw-semibold">${escHtml(emp.manv)}</span></td>
      <td>${escHtml(emp.tennhanvien)}</td>
      <td>${escHtml(emp.mapb)}</td>
      <td>${escHtml(emp.tenphongban || '—')}</td>
      <td>${emp.dinhmuc ? `<span class="badge bg-secondary">${escHtml(emp.dinhmuc)}</span>` : '—'}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-info me-1" onclick="openEmpHistory('${escHtml(emp.manv)}','${escHtml(emp.tennhanvien)}')">
          <i class="bi bi-clock-history"></i> Lịch sử
        </button>
        <button class="btn btn-sm btn-outline-primary me-1" onclick="openEmpModalByIdx(${idx})">
          <i class="bi bi-pencil"></i> Sửa
        </button>

      </td>
    </tr>`;
  }).join('');
}

function openEmpModal(emp) {
  State.currentEmpEdit = emp;
  document.getElementById('empModalTitle').textContent = emp ? 'Sửa nhân viên' : 'Thêm nhân viên mới';
  document.getElementById('emp-manv-input').value = emp?.manv || '';
  document.getElementById('emp-manv-input').disabled = !!emp;
  document.getElementById('emp-ten-input').value = emp?.tennhanvien || '';

  const modalPbSel = document.getElementById('emp-mapb-input');
  const dmSel = document.getElementById('emp-dinhmuc-input');
  const setValues = () => {
    modalPbSel.value = emp?.mapb || '';
    dmSel.value = emp?.dinhmuc || '';
  };

  const loadPb = modalPbSel.options.length <= 1
    ? API.getEmployees('').then(res => {
        if (res.success && res.data) {
          const pbs = [...new Map(res.data.filter(e => e.mapb).map(e => [e.mapb, e])).values()]
            .sort((a, b) => (a.mapb||'').localeCompare(b.mapb||''));
          pbs.forEach(e => {
            modalPbSel.insertAdjacentHTML('beforeend',
              `<option value="${escHtml(e.mapb)}">${escHtml(e.mapb)} - ${escHtml(e.tenphongban||e.mapb)}</option>`);
          });
          const filterSel = document.getElementById('emp-pb-filter');
          if (filterSel && filterSel.options.length <= 1) {
            pbs.forEach(e => filterSel.insertAdjacentHTML('beforeend',
              `<option value="${escHtml(e.mapb)}">${escHtml(e.mapb)} - ${escHtml(e.tenphongban||e.mapb)}</option>`));
          }
        }
      }).catch(() => {})
    : Promise.resolve();

  const loadDm = dmSel.options.length <= 1
    ? API.getDinhMuc().then(res => {
        if (res.success && res.data) {
          State.dinhMucData = res.data;
          res.data.forEach(d => {
            const label = d.mota ? `${escHtml(d.madm)} - ${escHtml(d.mota)}` : escHtml(d.madm);
            dmSel.insertAdjacentHTML('beforeend', `<option value="${escHtml(d.madm)}">${label}</option>`);
          });
        }
      }).catch(() => {})
    : Promise.resolve();

  Promise.all([loadPb, loadDm]).then(() => {
    setValues();
    document.getElementById('emp-first-ct-date').value = today();
    if (!emp) {
      // Thêm mới: auto check cấp phát lần đầu, hiện ngày
      document.getElementById('emp-create-ct-check').checked = true;
      document.getElementById('emp-first-ct-date-row').classList.remove('d-none');
      document.getElementById('emp-first-ct-section').classList.remove('d-none');
    } else {
      // Sửa: ẩn section cấp phát lần đầu
      document.getElementById('emp-create-ct-check').checked = false;
      document.getElementById('emp-first-ct-date-row').classList.add('d-none');
      document.getElementById('emp-first-ct-section').classList.add('d-none');
    }
    // Preview vật tư nếu đã có định mức
    if (document.getElementById('emp-dinhmuc-input').value) {
      onDinhMucChange(document.getElementById('emp-dinhmuc-input').value);
    } else {
      document.getElementById('emp-dm-preview').classList.add('d-none');
    }
  });
  getModal('empModal').show();
}

function onDinhMucChange(madm) {
  const preview = document.getElementById('emp-dm-preview');
  const tbody = document.getElementById('emp-dm-preview-tbody');
  if (!madm) { preview.classList.add('d-none'); return; }

  const dm = State.dinhMucData.find(d => d.madm === madm);
  if (!dm || !dm.chitiet || dm.chitiet.length === 0) { preview.classList.add('d-none'); return; }

  tbody.innerHTML = dm.chitiet.map(r => `
    <tr>
      <td>${escHtml(r.tenvt || 'Mã: ' + r.mavt)}</td>
      <td class="text-center" style="width:80px">
        <input type="number" min="0" max="99" value="1"
          class="form-control form-control-sm text-center p-0"
          data-mavt="${r.mavt}" data-dmtg="${r.dmtg}" />
      </td>
      <td class="text-center">${escHtml(r.dvt || '')}</td>
      <td class="text-center">${r.dmtg} tháng</td>
    </tr>`).join('');
  preview.classList.remove('d-none');
}

function toggleFirstCtDate(checked) {
  document.getElementById('emp-first-ct-date-row').classList.toggle('d-none', !checked);
}

function openFirstAllocateModal(manv, tennhanvien, mapb, dinhmuc) {
  // Mở modal ở chế độ "cấp lần đầu" cho NV đã có
  const emp = { manv, tennhanvien, mapb, dinhmuc, isFirstAllocate: true };
  State.currentEmpEdit = emp;

  document.getElementById('empModalTitle').textContent = `Cấp phát lần đầu: ${tennhanvien} (${manv})`;
  document.getElementById('emp-manv-input').value = manv;
  document.getElementById('emp-manv-input').disabled = true;
  document.getElementById('emp-ten-input').value = tennhanvien;
  // Ẩn các row không cần thiết
  document.getElementById('emp-manv-input').closest('.col-md-6').classList.add('d-none');
  document.getElementById('emp-ten-input').closest('.col-md-6').classList.add('d-none');
  document.getElementById('emp-mapb-input').closest('.col-md-6').classList.add('d-none');

  const dmSel = document.getElementById('emp-dinhmuc-input');
  const doOpen = () => {
    dmSel.value = dinhmuc || '';
    document.getElementById('emp-first-ct-date').value = today();
    document.getElementById('emp-create-ct-check').checked = true;
    document.getElementById('emp-first-ct-date-row').classList.remove('d-none');
    document.getElementById('emp-dm-preview').classList.remove('d-none');
    if (dinhmuc) onDinhMucChange(dinhmuc);
  };

  if (dmSel.options.length <= 1) {
    API.getDinhMuc().then(res => {
      if (res.success && res.data) {
        State.dinhMucData = res.data;
        res.data.forEach(d => {
          const label = d.mota ? `${escHtml(d.madm)} - ${escHtml(d.mota)}` : escHtml(d.madm);
          dmSel.insertAdjacentHTML('beforeend', `<option value="${escHtml(d.madm)}">${label}</option>`);
        });
      }
      doOpen();
    }).catch(() => doOpen());
  } else {
    doOpen();
  }
  getModal('empModal').show();
}

function openEmpEditName(manv, tennhanvien) {
  // Quick inline edit via modal
  State.currentEmpEdit = { manv, tennhanvien, isNameOnlyEdit: true };
  document.getElementById('empModalTitle').textContent = 'Đổi tên nhân viên';
  document.getElementById('emp-manv-input').value = manv;
  document.getElementById('emp-manv-input').disabled = true;
  document.getElementById('emp-ten-input').value = tennhanvien;
  document.getElementById('emp-mapb-input').value = '';
  document.getElementById('emp-dinhmuc-input').value = '';
  // Hide irrelevant fields
  getModal('empModal').show();
}

async function saveEmployee() {
  const btn = document.getElementById('emp-save-btn');
  const emp = State.currentEmpEdit;

  if (emp?.isNameOnlyEdit) {
    const newName = document.getElementById('emp-ten-input').value.trim();
    if (!newName) { showToast('Vui lòng nhập tên', 'warning'); return; }
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
    try {
      const res = await API.updateEmployeeName(emp.manv, newName);
      if (res.success) {
        showToast('Cập nhật tên thành công!', 'success');
        getModal('empModal').hide();
        loadEmployees(document.getElementById('emp-search').value.trim());
        loadEmployeesForSelect();
      } else {
        showToast(res.message || 'Cập nhật thất bại', 'danger');
      }
    } catch (err) {
      showToast('Lỗi: ' + err.message, 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = origText;
    }
    return;
  }

  // Chế độ "Cấp lần đầu" cho NV đã tồn tại
  if (emp?.isFirstAllocate) {
    const dinhmuc = document.getElementById('emp-dinhmuc-input').value.trim();
    const ngct = document.getElementById('emp-first-ct-date').value || today();
    if (!dinhmuc) { showToast('Vui lòng chọn định mức', 'warning'); return; }
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
    try {
      const manvFmt = /^\d{4}$/.test(emp.manv) ? '0' + emp.manv : emp.manv;
      const ym = ngct.substring(0, 7);
      const mact = `${ym}-${emp.mapb}-${manvFmt}`;
      const slInputs = document.querySelectorAll('#emp-dm-preview-tbody input[data-mavt]');
      const vattu = [];
      slInputs.forEach(inp => {
        const qty = parseInt(inp.value) || 0;
        if (qty > 0) vattu.push({ mavt: inp.dataset.mavt, dmtg: parseInt(inp.dataset.dmtg)||0 });
      });
      if (vattu.length === 0) { showToast('Vui lòng chọn ít nhất 1 vật tư (SL > 0)', 'warning'); btn.disabled = false; return; }
      const allocRes = await API.allocateFirst({ mact, manv: emp.manv, ngct, mapb: emp.mapb, madm: dinhmuc, vattu });
      if (allocRes.success) {
        showToast(`Đã tạo CT ${mact} và cấp phát ${allocRes.data?.allocated || 0} vật tư`, 'success');
        getModal('empModal').hide();
      } else {
        showToast('Lỗi: ' + (allocRes.message||''), 'danger');
      }
    } catch (err) {
      showToast('Lỗi: ' + err.message, 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = origText;
    }
    return;
  }

  const manv = document.getElementById('emp-manv-input').value.trim();
  const tennhanvien = document.getElementById('emp-ten-input').value.trim();
  const mapb = document.getElementById('emp-mapb-input').value.trim();
  const dinhmuc = document.getElementById('emp-dinhmuc-input').value.trim();

  if (!manv || !tennhanvien || !mapb) {
    showToast('Vui lòng điền Mã NV, Tên và Mã PB', 'warning');
    return;
  }

  const origText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
  try {
    const payload = { manv, tennhanvien, mapb, dinhmuc: dinhmuc || '' };
    const isEdit = !!emp; // emp đã có = đang sửa
    const res = isEdit ? await API.updateEmployee(payload) : await API.addEmployee(payload);
    if (res.success) {
      // Tạo CT lần đầu nếu checkbox được chọn (chỉ khi thêm mới)
      const createCt = !isEdit && document.getElementById('emp-create-ct-check')?.checked;
      if (createCt && dinhmuc) {
        const ngct = document.getElementById('emp-first-ct-date').value || today();
        const ym = ngct.substring(0, 7);
        const manvFmt = /^\d{4}$/.test(manv) ? '0' + manv : manv;
        const mact = `${ym}-${mapb}-${manvFmt}`;
        const slInputs = document.querySelectorAll('#emp-dm-preview-tbody input[data-mavt]');
        const vattu = [];
        slInputs.forEach(inp => {
          const qty = parseInt(inp.value) || 0;
          if (qty > 0) vattu.push({ mavt: inp.dataset.mavt, dmtg: parseInt(inp.dataset.dmtg)||0 });
        });
        const allocRes = await API.allocateFirst({ mact, manv, ngct, mapb, madm: dinhmuc, vattu });
        if (allocRes.success) {
          showToast(`Đã tạo CT ${mact} và cấp phát ${allocRes.data?.allocated || 0} vật tư`, 'success');
        } else {
          showToast('Lưu NV OK nhưng cấp phát lỗi: ' + (allocRes.message||''), 'warning');
        }
      } else {
        showToast('Thêm nhân viên thành công!', 'success');
      }
      getModal('empModal').hide();
      loadEmployees();
      loadEmployeesForSelect();
    } else {
      showToast(res.message || 'Thêm thất bại', 'danger');
    }
  } catch (err) {
    showToast('Lỗi: ' + err.message, 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = origText;
  }
}

async function openEmpHistory(manv, tennhanvien) {
  document.getElementById('historyModalTitle').textContent = `Lịch sử: ${tennhanvien} (${manv})`;
  document.getElementById('history-tbody').innerHTML = '';
  document.getElementById('history-empty').classList.add('d-none');
  setLoading('history-loading', true);
  getModal('historyModal').show();

  try {
    const res = await API.getAllocationHistory({ manv });
    setLoading('history-loading', false);
    if (res.success && res.data) {
      const list = res.data;
      if (list.length === 0) {
        document.getElementById('history-empty').classList.remove('d-none');
        return;
      }
      document.getElementById('history-tbody').innerHTML = list.map(h => {
        const isAllocated = h.sl == 1;
        const now = new Date();
        let status, statusClass;
        if (!isAllocated) {
          status = 'Chưa cấp'; statusClass = 'bg-secondary';
        } else {
          const due = new Date(h.ngnhan);
          due.setMonth(due.getMonth() + parseInt(h.dmtg || 0));
          if (h.ngnhan && !isNaN(due) && now > due) { status = 'Đã nhận'; statusClass = 'bg-success'; }
          else { status = 'Đã nhận'; statusClass = 'bg-success'; }
        }
        return `<tr>
          <td>${escHtml(h.mact)}</td>
          <td>${escHtml(h.tenvt || 'Mã: ' + h.mavt)}</td>
          <td class="text-center">
            <input type="number" min="0" max="1" step="1"
              class="form-control form-control-sm text-center p-0"
              style="width:50px;display:inline-block"
              value="${h.sl}"
              data-mact="${escHtml(h.mact)}" data-mavt="${h.mavt}"
              onchange="updateHistorySl(this)" />
          </td>
          <td>
            <input type="date" class="form-control form-control-sm p-0"
              style="min-width:120px"
              value="${h.ngnhan && h.ngnhan !== '1911-11-11' ? h.ngnhan : ''}"
              data-mact="${escHtml(h.mact)}" data-mavt="${h.mavt}" data-field="ngnhan"
              onchange="updateHistoryDate(this)" />
          </td>
          <td>${calcNgayTiepTheo(h.ngnhan, h.dmtg)}</td>
          <td class="text-center">${h.dmtg} tháng</td>
          <td><span class="badge ${statusClass}" id="hist-status-${escHtml(h.mact)}-${h.mavt}">${status}</span></td>
        </tr>`;
      }).join('');
    }
  } catch (err) {
    setLoading('history-loading', false);
    showToast('Lỗi tải lịch sử: ' + err.message, 'danger');
  }
}

async function updateHistoryDate(input) {
  const mact = input.dataset.mact;
  const mavt = input.dataset.mavt;
  const field = input.dataset.field;
  const value = input.value || '1911-11-11';
  try {
    const payload = { mact, mavt, [field]: value };
    const res = await API.updateCertificateDetail(payload);
    if (res.success) {
      showToast('Đã cập nhật ngày', 'success');
    } else {
      showToast('Lỗi: ' + (res.message || 'Không thể cập nhật'), 'danger');
    }
  } catch (err) {
    showToast('Lỗi: ' + err.message, 'danger');
  }
}

async function updateHistorySl(input) {
  const mact = input.dataset.mact;
  const mavt = input.dataset.mavt;
  const sl = parseInt(input.value);
  if (isNaN(sl) || sl < 0 || sl > 1) { input.value = input.defaultValue; return; }
  try {
    const res = await API.updateCertificateDetail({ mact, mavt, sl });
    if (res.success) {
      input.defaultValue = sl;
      // Cập nhật badge trạng thái
      const badge = document.getElementById(`hist-status-${mact}-${mavt}`);
      if (badge) {
        if (sl === 0) {
          badge.className = 'badge bg-secondary'; badge.textContent = 'Chưa cấp';
        } else {
          badge.className = 'badge bg-success'; badge.textContent = 'Đã nhận';
        }
      }
      showToast('Đã cập nhật SL', 'success');
    } else {
      showToast('Lỗi: ' + (res.message || 'Không thể cập nhật'), 'danger');
      input.value = input.defaultValue;
    }
  } catch (err) {
    showToast('Lỗi: ' + err.message, 'danger');
    input.value = input.defaultValue;
  }
}

// ====================================================================
// TAB 4: BÁO CÁO
// ====================================================================
let rptInitialized = false;

function initReportsTab() {
  if (!rptInitialized) {
    rptInitialized = true;
    // Populate year select
    const now = new Date();
    const yearSel = document.getElementById('rpt-year');
    for (let y = now.getFullYear(); y >= 2020; y--) {
      yearSel.insertAdjacentHTML('beforeend', `<option value="${y}" ${y === now.getFullYear() ? 'selected' : ''}>${y}</option>`);
    }
    // Set current month
    document.getElementById('rpt-month').value = String(now.getMonth() + 1).padStart(2, '0');

    document.getElementById('rpt-load-btn').addEventListener('click', loadMonthlyReport);
    document.getElementById('rpt-print-btn').addEventListener('click', () => window.print());
    document.getElementById('rpt-print-total-btn').addEventListener('click', printMonthlySummary);
    document.getElementById('rpt-export-word-btn').addEventListener('click', exportWordReport);
  }
  loadMonthlyReport();
}

async function loadMonthlyReport() {
  const month = document.getElementById('rpt-month').value;
  const year = document.getElementById('rpt-year').value;
  const monthStr = `${month}/${year}`;

  setLoading('rpt-loading', true);
  document.getElementById('rpt-content').innerHTML = '';

  try {
    const res = await API.getMonthlyReport(monthStr);
    if (res.success && res.data) {
      State.lastReportData = res.data;
      State.lastReportMonthStr = monthStr;
      renderMonthlyReport(res.data, monthStr);
    } else {
      State.lastReportData = null;
      document.getElementById('rpt-content').innerHTML =
        '<div class="alert alert-info">Không có dữ liệu báo cáo cho tháng này.</div>';
    }
  } catch (err) {
    document.getElementById('rpt-content').innerHTML =
      `<div class="alert alert-danger">Lỗi: ${escHtml(err.message)}</div>`;
  } finally {
    setLoading('rpt-loading', false);
  }
}

function renderMonthlyReport(data, monthStr) {
  const container = document.getElementById('rpt-content');

  // Summary section
  let summaryHtml = '';
  if (data.summary && data.summary.items && data.summary.items.length > 0) {
    summaryHtml = `
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-semibold">
          <i class="bi bi-pie-chart me-2"></i>Thống kê vật tư – Tháng ${escHtml(monthStr)}
        </div>
        <div class="card-body p-0">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Vật tư</th>
                <th class="text-center">ĐVT</th>
                <th class="text-center">Tổng số lượng</th>
              </tr>
            </thead>
            <tbody>
              ${data.summary.items.map(item => `
                <tr>
                  <td>${escHtml(item.tenvt || item.mavt)}</td>
                  <td class="text-center">${escHtml(item.dvt || '')}</td>
                  <td class="text-center fw-semibold">${item.quantity || item.sl || 0}</td>
                </tr>`).join('')}
              <tr class="table-active fw-bold">
                <td colspan="2">Tổng cộng</td>
                <td class="text-center">${data.summary.totalQuantity || 0}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>`;
  }

  // Departments section
  let deptsHtml = '';
  if (data.departments && data.departments.length > 0) {
    // Collect all equipment names
    const allEquip = new Set();
    data.departments.forEach(dept => {
      dept.employees.forEach(emp => {
        Object.keys(emp.equipmentStatus || emp.equipment || {}).forEach(k => allEquip.add(k));
      });
    });
    const equipNames = [...allEquip];

    deptsHtml = data.departments.map(dept => {
      const empRows = (dept.employees || []).map(emp => {
        const eqStatus = emp.equipmentStatus || emp.equipment || {};
        const eqCells = equipNames.map(name => {
          const s = eqStatus[name];
          if (!s) return '<td class="text-center text-muted">—</td>';
          const rec = s.received || 0;
          const req = s.required || 0;
          const cls = rec >= req ? 'text-success' : rec > 0 ? 'text-warning' : 'text-muted';
          return `<td class="text-center ${cls}">${rec}${req > 0 ? '/' + req : ''}</td>`;
        }).join('');
        return `<tr>
          <td class="ps-3">${escHtml(emp.employeeCode || emp.manv)}</td>
          <td>${escHtml(emp.employeeName || emp.tennhanvien)}</td>
          ${eqCells}
        </tr>`;
      }).join('');

      return `
        <div class="card shadow-sm mb-3">
          <div class="card-header bg-light fw-semibold">
            <i class="bi bi-building me-2 text-primary"></i>${escHtml(dept.departmentCode || dept.mapb)} – ${escHtml(dept.departmentName || dept.tenphongban)}
            <span class="badge bg-primary ms-2">${(dept.employees||[]).length} NV</span>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3">Mã NV</th>
                    <th>Họ tên</th>
                    ${equipNames.map(n => `<th class="text-center small">${escHtml(n)}</th>`).join('')}
                  </tr>
                </thead>
                <tbody>${empRows}</tbody>
              </table>
            </div>
          </div>
        </div>`;
    }).join('');
  } else {
    deptsHtml = '<div class="alert alert-info">Không có dữ liệu phòng ban.</div>';
  }

  container.innerHTML = summaryHtml + deptsHtml;
}

// ====================================================================
// IN CHỨNG TỪ CẤP PHÁT
// ====================================================================
async function printCertificate(mact) {
  // Lấy thông tin chứng từ từ state hoặc fetch
  let cert = State.certificates.find(c => c.mact === mact)
           || mgAllCerts.find(c => c.mact === mact);

  // Lấy chi tiết từ state hoặc fetch
  let details = State.certDetailsMap[mact];
  if (!details) {
    try {
      const res = await API.getCertificateDetails(mact);
      if (res.success && res.data) {
        details = res.data;
        State.certDetailsMap[mact] = details;
      } else {
        details = [];
      }
    } catch (err) {
      showToast('Lỗi tải chi tiết để in: ' + err.message, 'danger');
      return;
    }
  }

  const win = window.open('', '_blank', 'width=900,height=700');
  if (!win) {
    showToast('Trình duyệt đã chặn cửa sổ in. Vui lòng cho phép popup cho trang này.', 'warning');
    return;
  }

  const ngct     = cert ? formatDate(cert.ngct) : '—';
  const manv     = cert ? escHtml(cert.manv) : escHtml(mact);
  const ten      = cert ? escHtml(cert.tennhanvien || '—') : '—';
  const mapb     = cert ? escHtml(cert.mapb) : '—';
  const tenpb    = cert ? escHtml(cert.tenphongban || '') : '';
  const madm     = cert ? escHtml(cert.madm) : '—';
  const ghichu   = cert ? escHtml(cert.ghichu || '') : '';
  const ngayIn   = new Date().toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });

  const rows = (details || []).map((d, i) => {
    const ngnhanKH  = (d.ngnhan && d.ngnhan !== '1911-11-11') ? formatDate(d.ngnhan) : '&nbsp;';
    const ngnhanTT  = calcNgayTiepTheo(d.ngnhan, d.dmtg);
    const slDa = d.sl > 0 ? d.sl : 0;
    return `
      <tr>
        <td style="text-align:center">${i + 1}</td>
        <td>${escHtml(d.tenvt || 'Mã: ' + d.mavt)}</td>
        <td style="text-align:center">${escHtml(d.dvt || '')}</td>
        <td style="text-align:center">${d.dmtg}</td>
        <td style="text-align:center">${slDa}</td>
        <td style="text-align:center">${ngnhanKH}</td>
        <td style="text-align:center">${ngnhanTT}</td>
        <td style="text-align:center">&nbsp;</td>
      </tr>`;
  }).join('');

  const html = `<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Chứng từ cấp phát – ${escHtml(mact)}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Times New Roman', Times, serif; font-size: 13pt; color: #000; background: #fff; }
    .page { padding: 15mm 20mm; }
    .top-row { display: flex; justify-content: space-between; font-size: 11pt; margin-bottom: 6px; }
    .center { text-align: center; }
    .doc-title { font-size: 17pt; font-weight: bold; text-transform: uppercase; margin: 10px 0 3px; }
    .doc-mact  { font-size: 12pt; margin-bottom: 14px; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 3px 40px; margin-bottom: 10px; }
    .info-row  { font-size: 12pt; margin-bottom: 3px; }
    .label     { font-weight: bold; }
    .ghichu    { font-size: 11pt; font-style: italic; margin-bottom: 10px; }
    table      { width: 100%; border-collapse: collapse; font-size: 11.5pt; margin-bottom: 20px; }
    th, td     { border: 1px solid #333; padding: 5px 6px; vertical-align: middle; }
    th         { background: #f0f0f0; font-weight: bold; text-align: center; }
    .sigs      { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0; margin-top: 10px; }
    .sig       { text-align: center; padding: 0 6px; }
    .sig-title { font-weight: bold; margin-bottom: 3px; font-size: 12pt; }
    .sig-sub   { font-size: 10.5pt; font-style: italic; color: #444; margin-bottom: 55px; }
    .sig-name  { font-style: italic; font-size: 11pt; border-top: 1px solid #999; padding-top: 4px; }
    .print-btn-bar { text-align: right; padding: 10px 20mm 6px; }
    @media print {
      .print-btn-bar { display: none !important; }
      body { font-size: 12pt; }
    }
  </style>
</head>
<body>
  <div class="print-btn-bar">
    <button onclick="window.print()" style="padding:6px 18px;font-size:12pt;cursor:pointer;background:#1d4ed8;color:#fff;border:none;border-radius:4px;margin-right:8px;">🖨&nbsp;In</button>
    <button onclick="window.close()" style="padding:6px 14px;font-size:12pt;cursor:pointer;border:1px solid #aaa;border-radius:4px;background:#f5f5f5;">Đóng</button>
  </div>
  <div class="page">
    <div class="top-row">
      <div></div>
      <div style="font-style:italic">Ngày in: ${ngayIn}</div>
    </div>
    <div class="center">
      <div class="doc-title">Phiếu cấp phát bảo hộ lao động</div>
      <div class="doc-mact">Mã chứng từ: <strong>${escHtml(mact)}</strong></div>
    </div>

    <div class="info-grid">
      <div>
        <div class="info-row"><span class="label">Nhân viên:</span> ${manv} – ${ten}</div>
        <div class="info-row"><span class="label">Phòng ban:</span> ${mapb}${tenpb ? ' – ' + tenpb : ''}</div>
      </div>
      <div>
        <div class="info-row"><span class="label">Ngày chứng từ:</span> ${ngct}</div>
        <div class="info-row"><span class="label">Mã danh mục:</span> ${madm}</div>
      </div>
    </div>
    ${ghichu ? `<div class="ghichu">Ghi chú: ${ghichu}</div>` : ''}

    <table>
      <thead>
        <tr>
          <th style="width:36px">STT</th>
          <th>Tên vật tư / BHLĐ</th>
          <th style="width:60px">ĐVT</th>
          <th style="width:80px">Định mức<br>(tháng)</th>
          <th style="width:70px">Số<br>lượng</th>
          <th style="width:110px">Ngày nhận<br>(kế hoạch)</th>
          <th style="width:110px">Ngày nhận<br>thực tế</th>
          <th style="width:80px">Ký nhận</th>
        </tr>
      </thead>
      <tbody>
        ${rows || '<tr><td colspan="8" style="text-align:center;font-style:italic;color:#666">Chưa có vật tư</td></tr>'}
      </tbody>
    </table>

    <div class="sigs">
      <div class="sig">
        <div class="sig-title">Người nhận</div>
        <div class="sig-sub">(Ký, ghi rõ họ tên)</div>
        <div class="sig-name">&nbsp;</div>
      </div>
      <div class="sig">
        <div class="sig-title">Người cấp phát</div>
        <div class="sig-sub">(Ký, ghi rõ họ tên)</div>
        <div class="sig-name">&nbsp;</div>
      </div>
      <div class="sig">
        <div class="sig-title">Trưởng phòng / Xác nhận</div>
        <div class="sig-sub">(Ký, đóng dấu)</div>
        <div class="sig-name">&nbsp;</div>
      </div>
    </div>
  </div>
  <script>
    window.onload = function() { setTimeout(function() { window.print(); }, 400); };
  <\/script>
</body>
</html>`;

  win.document.write(html);
  win.document.close();
  win.focus();
}

// ====================================================================
// IN TỔNG HỢP THÁNG
// ====================================================================
// Mapping cột cố định theo mẫu in (khớp với standardEquipment trong monthly_report.php)
const EQUIP_COLS = [
  { key: 'Giày',    label: 'Giày' },
  { key: 'Mũ',      label: 'Mũ' },
  { key: 'Áo quần', label: 'Quần<br>áo' },
  { key: 'Kính',    label: 'Kính' },
  { key: 'Áo mưa',  label: 'Áo<br>mưa' },
  { key: 'Nút tai', label: 'Nút<br>tai' },
  { key: 'Phim',    label: 'Phin<br>Lọc' },
];

function printMonthlySummary() {
  const data     = State.lastReportData;
  const monthStr = State.lastReportMonthStr;

  if (!data) {
    showToast('Vui lòng xem báo cáo trước khi in', 'warning');
    return;
  }

  const win = window.open('', '_blank', 'width=1000,height=700');
  if (!win) {
    showToast('Trình duyệt đã chặn cửa sổ in. Vui lòng cho phép popup.', 'warning');
    return;
  }

  const ngayIn = new Date().toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
  const deps   = (data.departments && data.departments.length > 0) ? data.departments : [];

  const deptTables = deps.map(dept => {
    const deptCode = escHtml(dept.mapb || dept.departmentCode || '');
    const deptName = escHtml(dept.tenphongban || dept.departmentName || '');

    const empRows = (dept.employees || []).map((emp, idx) => {
      const eq = emp.equipment || emp.equipmentStatus || {};
      const cells = EQUIP_COLS.map(col => {
        const val = eq[col.key] ? (eq[col.key].received || 0) : 0;
        return `<td>${val}</td>`;
      }).join('');
      return `<tr>
        <td>${escHtml(emp.manv || emp.employeeCode || '')}</td>
        <td style="text-align:left;padding-left:6px">${escHtml(emp.tennhanvien || emp.employeeName || '')}</td>
        ${cells}
        <td></td>
      </tr>`;
    }).join('');

    return `
      <div style="margin-bottom:24px;page-break-inside:avoid">
        <div style="font-weight:bold;font-size:12.5pt;margin-bottom:5px">
          ${deptCode}${deptName ? ' – ' + deptName : ''}
        </div>
        <table>
          <thead>
            <tr>
              <th style="width:68px">Danh<br>Số</th>
              <th>Tên</th>
              ${EQUIP_COLS.map(c => `<th style="width:50px">${c.label}</th>`).join('')}
              <th style="width:72px">Ký nhận</th>
            </tr>
          </thead>
          <tbody>
            ${empRows || '<tr><td colspan="10" style="text-align:center;font-style:italic;color:#888">Không có dữ liệu</td></tr>'}
          </tbody>
        </table>
      </div>`;
  }).join('');

  const html = `<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Tổng hợp cấp phát BHLĐ – Tháng ${escHtml(monthStr)}</title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Times New Roman',Times,serif; font-size:12pt; color:#000; background:#fff; }
    .page { padding:12mm 15mm; }
    table { width:100%; border-collapse:collapse; font-size:11pt; margin-bottom:0; }
    th, td { border:1px solid #333; padding:4px 5px; text-align:center; vertical-align:middle; }
    th { font-weight:bold; background:#fff; }
    tbody tr:hover { background:#f9f9f9; }
    .print-bar { text-align:right; padding:8px 15mm 4px; }
    @media print {
      .print-bar { display:none !important; }
      @page { margin:10mm 12mm; }
    }
  </style>
</head>
<body>
  <div class="print-bar">
    <button onclick="window.print()" style="padding:5px 16px;font-size:11pt;cursor:pointer;background:#1d4ed8;color:#fff;border:none;border-radius:4px;margin-right:6px;">🖨&nbsp;In</button>
    <button onclick="window.close()" style="padding:5px 12px;font-size:11pt;cursor:pointer;border:1px solid #aaa;border-radius:4px;background:#f5f5f5;">Đóng</button>
  </div>
  <div class="page">
    <div style="text-align:center;margin-bottom:14px">
      <div style="font-size:15pt;font-weight:bold;text-transform:uppercase">Bảng tổng hợp cấp phát bảo hộ lao động</div>
      <div style="font-size:12pt;margin-top:3px">Tháng <strong>${escHtml(monthStr)}</strong></div>
      <div style="font-size:10pt;font-style:italic;color:#555">Ngày in: ${ngayIn}</div>
    </div>

    ${deptTables || '<p style="text-align:center;color:#888;font-style:italic">Không có dữ liệu</p>'}

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;margin-top:28px;text-align:center">
      <div>
        <div style="font-weight:bold">Người lập báo cáo</div>
        <div style="font-size:10.5pt;font-style:italic;color:#555;margin-bottom:52px">(Ký, ghi rõ họ tên)</div>
        <div style="border-top:1px solid #999;padding-top:3px;font-style:italic">&nbsp;</div>
      </div>
      <div>
        <div style="font-weight:bold">Trưởng phòng / Lãnh đạo</div>
        <div style="font-size:10.5pt;font-style:italic;color:#555;margin-bottom:52px">(Ký, đóng dấu)</div>
        <div style="border-top:1px solid #999;padding-top:3px;font-style:italic">&nbsp;</div>
      </div>
    </div>
  </div>
  <script>
    window.onload = function() { setTimeout(function() { window.print(); }, 400); };
  <\/script>
</body>
</html>`;

  win.document.write(html);
  win.document.close();
  win.focus();
}

// ====================================================================
// XUẤT WORD
// ====================================================================
function exportWordReport() {
  const month = document.getElementById('rpt-month').value;
  const year  = document.getElementById('rpt-year').value;
  if (!month || !year) {
    showToast('Vui lòng chọn tháng/năm trước.', 'warning');
    return;
  }
  const monthStr = `${month}/${year}`;
  const url = `${API_BASE}/export_word.php?month=${encodeURIComponent(monthStr)}`;
  window.open(url, '_blank');
}

// ====================================================================
// CONFIRM MODAL
// ====================================================================
document.getElementById('confirmModalOk').addEventListener('click', () => {
  getModal('confirmModal').hide();
  if (State.currentConfirmCallback) {
    State.currentConfirmCallback();
    State.currentConfirmCallback = null;
  }
});

// ====================================================================
// TAB 5: LỊCH SỬ THAY ĐỔI
// ====================================================================
let clInitialized = false;
let clCurrentPage = 1;

function initChangelogTab() {
  if (!clInitialized) {
    clInitialized = true;
    document.getElementById('cl-search-btn').addEventListener('click', () => {
      clCurrentPage = 1;
      loadChangelog();
    });
    document.getElementById('cl-clear-btn').addEventListener('click', () => {
      document.getElementById('cl-id').value = '';
      document.getElementById('cl-action').value = '';
      document.getElementById('cl-table').value = 'bhld_ctu';
      document.getElementById('cl-from-date').value = '';
      document.getElementById('cl-to-date').value = '';
      clCurrentPage = 1;
      loadChangelog();
    });
    document.getElementById('cl-table').addEventListener('change', () => {
      clCurrentPage = 1;
      loadChangelog();
    });
  }
  loadChangelog();
}

async function loadChangelog() {
  setLoading('cl-loading', true);
  document.getElementById('cl-tbody').innerHTML = '';
  document.getElementById('cl-empty').classList.add('d-none');
  document.getElementById('cl-pagination').innerHTML = '';
  document.getElementById('cl-stats').textContent = '';

  const params = {
    table:     document.getElementById('cl-table').value,
    id:        document.getElementById('cl-id').value.trim(),
    action:    document.getElementById('cl-action').value,
    from_date: document.getElementById('cl-from-date').value,
    to_date:   document.getElementById('cl-to-date').value,
    limit:     document.getElementById('cl-limit').value,
    page:      clCurrentPage,
  };

  try {
    const res = await API.getChangeHistory(params);
    setLoading('cl-loading', false);
    if (!res.success) { showToast(res.message || 'Lỗi tải dữ liệu', 'danger'); return; }
    if (!res.data.table_exists && res.data.table_exists === false) {
      document.getElementById('cl-empty').classList.remove('d-none');
      document.getElementById('cl-empty').textContent = 'Bảng history chưa tồn tại trên server';
      return;
    }
    const list = res.data.data || [];
    document.getElementById('cl-stats').textContent =
      `Tổng: ${res.data.total} bản ghi | Trang ${res.data.page}/${res.data.pages}`;
    if (list.length === 0) {
      document.getElementById('cl-empty').classList.remove('d-none');
      return;
    }
    const isCtctu = params.table === 'bhld_ctctu';
    let stt = (clCurrentPage - 1) * parseInt(params.limit) + 1;
    document.getElementById('cl-tbody').innerHTML = list.map(r => {
      const displayId = isCtctu
        ? `${escHtml(r.record_id_mact||'')} / ${r.record_id_mavt||''}`
        : escHtml(r.record_id||'');
      const badgeClass = r.action_type === 'INSERT' ? 'bg-success'
        : r.action_type === 'DELETE' ? 'bg-danger' : 'bg-warning text-dark';
      const rowClass = r.action_type === 'INSERT' ? 'table-success'
        : r.action_type === 'DELETE' ? 'table-danger' : 'table-warning';
      let dataHtml = '';
      if (r.old_data) {
        try { dataHtml += `<div><strong>Cũ:</strong> <code style="font-size:11px">${escHtml(JSON.stringify(JSON.parse(r.old_data), null, 0))}</code></div>`; }
        catch(e) { dataHtml += `<div><strong>Cũ:</strong> ${escHtml(r.old_data)}</div>`; }
      }
      if (r.new_data) {
        try { dataHtml += `<div><strong>Mới:</strong> <code style="font-size:11px">${escHtml(JSON.stringify(JSON.parse(r.new_data), null, 0))}</code></div>`; }
        catch(e) { dataHtml += `<div><strong>Mới:</strong> ${escHtml(r.new_data)}</div>`; }
      }
      return `<tr class="${rowClass}">
        <td>${stt++}</td>
        <td><strong>${displayId}</strong></td>
        <td><span class="badge ${badgeClass}">${escHtml(r.action_type)}</span></td>
        <td style="white-space:nowrap">${r.action_time ? new Date(r.action_time).toLocaleString('vi-VN') : '—'}</td>
        <td><small class="text-primary">${escHtml(r.changed_fields||'—')}</small></td>
        <td style="max-width:400px;word-break:break-all">${dataHtml}</td>
      </tr>`;
    }).join('');

    // Pagination
    const totalPages = res.data.pages;
    if (totalPages > 1) {
      let pHtml = '';
      if (clCurrentPage > 1) pHtml += `<button class="btn btn-sm btn-outline-secondary" onclick="clGoPage(${clCurrentPage-1})">«</button>`;
      const start = Math.max(1, clCurrentPage - 3);
      const end = Math.min(totalPages, clCurrentPage + 3);
      for (let i = start; i <= end; i++) {
        pHtml += `<button class="btn btn-sm ${i === clCurrentPage ? 'btn-primary' : 'btn-outline-secondary'}" onclick="clGoPage(${i})">${i}</button>`;
      }
      if (clCurrentPage < totalPages) pHtml += `<button class="btn btn-sm btn-outline-secondary" onclick="clGoPage(${clCurrentPage+1})">»</button>`;
      document.getElementById('cl-pagination').innerHTML = pHtml;
    }
  } catch (err) {
    setLoading('cl-loading', false);
    showToast('Lỗi: ' + err.message, 'danger');
  }
}

function clGoPage(p) {
  clCurrentPage = p;
  loadChangelog();
}

// ====================================================================
// TAB: CẤP PHÁT
// ====================================================================
let allocInitialized = false;
let allocItems = []; // [{mact, mavt, tenvt, dvt, dmtg}]
let allocSelectedKeys = new Set();
let allocCurrentManv = null;

async function initAllocateTab() {
  if (!allocInitialized) {
    allocInitialized = true;
    document.getElementById('alloc-date').value = today();
    document.getElementById('alloc-pb-filter').addEventListener('change', renderAllocEmpList);
    document.getElementById('alloc-date').addEventListener('change', () => {
      if (allocCurrentManv) loadAllocateList(allocCurrentManv);
    });
    document.getElementById('alloc-bulk-btn').addEventListener('click', doAllocateBulk);
    document.getElementById('alloc-check-all').addEventListener('change', e => {
      document.querySelectorAll('.alloc-row-check').forEach(cb => {
        cb.checked = e.target.checked;
        e.target.checked ? allocSelectedKeys.add(cb.dataset.key) : allocSelectedKeys.delete(cb.dataset.key);
      });
      updateAllocBulkBtn();
    });
  }
  await loadAllocSidebar();
}

async function loadAllocSidebar() {
  if (!State.employees || !State.employees.length) {
    const res = await API.getEmployees();
    if (res.success) State.employees = res.data;
  }
  // Populate PB filter
  const pbSel = document.getElementById('alloc-pb-filter');
  const curPb = pbSel.value;
  // Build unique pb list with both mapb (value) and tenphongban (label)
  const pbMap = {};
  (State.employees || []).forEach(e => { if (e.mapb) pbMap[e.mapb] = e.tenphongban || e.mapb; });
  const pbEntries = Object.entries(pbMap).sort((a, b) => a[1].localeCompare(b[1]));
  pbSel.innerHTML = '<option value="">-- Tất cả đội --</option>';
  pbEntries.forEach(([mapb, ten]) => pbSel.insertAdjacentHTML('beforeend', `<option value="${escHtml(mapb)}">${escHtml(ten)}</option>`));
  pbSel.value = curPb;
  renderAllocEmpList();
}

function renderAllocEmpList() {
  const pb = document.getElementById('alloc-pb-filter').value;
  const filtered = (State.employees || []).filter(e => e.tennhanvien && (!pb || e.mapb === pb));

  // Group by phong ban
  const groups = {};
  filtered.forEach(emp => {
    const dept = emp.tenphongban || emp.mapb || 'Chưa phân loại';
    if (!groups[dept]) groups[dept] = [];
    groups[dept].push(emp);
  });

  const container = document.getElementById('alloc-emp-list');
  if (!filtered.length) {
    container.innerHTML = '<div class="text-center text-muted py-4 small">Không có nhân viên</div>';
    return;
  }

  container.innerHTML = Object.entries(groups).map(([dept, emps]) => `
    <div class="alloc-dept-group">
      <div class="px-3 py-1 small fw-semibold text-muted bg-light border-bottom sticky-top" style="font-size:11px;letter-spacing:.5px">
        <i class="bi bi-building me-1"></i>${escHtml(dept)} (${emps.length})
      </div>
      ${emps.map(emp => `
        <div class="alloc-emp-item px-3 py-2 border-bottom cursor-pointer d-flex align-items-center gap-2 ${emp.manv === allocCurrentManv ? 'bg-success bg-opacity-10 border-start border-success border-3' : ''}"
          onclick="selectAllocEmp('${escHtml(emp.manv)}', '${escHtml(emp.tennhanvien)}', this)">
          <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px">
            <i class="bi bi-person text-primary" style="font-size:14px"></i>
          </div>
          <div class="overflow-hidden">
            <div class="fw-medium text-truncate small">${escHtml(emp.tennhanvien)}</div>
            <div class="text-muted" style="font-size:11px">${escHtml(emp.manv)}</div>
          </div>
        </div>`).join('')}
    </div>`).join('');
}

async function selectAllocEmp(manv, tennhanvien, el) {
  allocCurrentManv = manv;
  // Highlight selected
  document.querySelectorAll('.alloc-emp-item').forEach(e => {
    e.classList.remove('bg-success', 'bg-opacity-10', 'border-start', 'border-success', 'border-3');
  });
  el.classList.add('bg-success', 'bg-opacity-10', 'border-start', 'border-success', 'border-3');
  // Show name in toolbar
  document.getElementById('alloc-emp-info').innerHTML =
    `<i class="bi bi-person-fill text-primary me-1"></i><strong>${escHtml(tennhanvien)}</strong> <span class="text-muted small">(${escHtml(manv)})</span>`;
  await loadAllocateList(manv);
}

async function loadAllocateList(manv) {
  setLoading('alloc-loading', true);
  document.getElementById('alloc-table-wrap').classList.add('d-none');
  document.getElementById('alloc-empty').classList.add('d-none');
  allocItems = [];
  allocSelectedKeys.clear();
  document.getElementById('alloc-check-all').checked = false;
  updateAllocBulkBtn();
  try {
    const certsRes = await API.getCertificates({ manv, to_date: document.getElementById('alloc-date').value || today() });
    if (!certsRes.success || !certsRes.data?.length) {
      document.getElementById('alloc-empty').classList.remove('d-none');
      return;
    }
    const detailsArr = await Promise.all(
      certsRes.data.map(c => API.getCertificateDetails(c.mact).catch(() => ({ success: false })))
    );
    certsRes.data.forEach((cert, i) => {
      const d = detailsArr[i];
      if (d.success && d.data) {
        d.data.filter(item => item.sl == 0).forEach(item => {
          allocItems.push({ mact: cert.mact, mavt: item.mavt, tenvt: item.tenvt, dvt: item.dvt, dmtg: item.dmtg });
        });
      }
    });
    if (allocItems.length === 0) {
      document.getElementById('alloc-empty').classList.remove('d-none');
      return;
    }
    renderAllocateList();
    document.getElementById('alloc-table-wrap').classList.remove('d-none');
  } catch (err) {
    showToast('Lỗi tải dữ liệu: ' + err.message, 'danger');
  } finally {
    setLoading('alloc-loading', false);
  }
}

function renderAllocateList() {
  document.getElementById('alloc-tbody').innerHTML = allocItems.map((item, idx) => {
    const key = `${item.mact}-${item.mavt}`;
    return `<tr>
      <td><input type="checkbox" class="form-check-input alloc-row-check" data-key="${escHtml(key)}" onchange="onAllocCheckChange(this)" /></td>
      <td><span class="badge bg-secondary">${escHtml(item.mact)}</span></td>
      <td><span class="fw-medium">${escHtml(item.tenvt || 'Mã: ' + item.mavt)}</span></td>
      <td class="text-center text-muted small">${escHtml(item.dvt || '—')}</td>
      <td class="text-center">${item.dmtg} tháng</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-success" onclick="doAllocateSingle(${idx}, this)">
          <i class="bi bi-box-arrow-in-down me-1"></i>Cấp phát
        </button>
      </td>
    </tr>`;
  }).join('');
}

function onAllocCheckChange(cb) {
  cb.checked ? allocSelectedKeys.add(cb.dataset.key) : allocSelectedKeys.delete(cb.dataset.key);
  updateAllocBulkBtn();
}

function updateAllocBulkBtn() {
  const n = allocSelectedKeys.size;
  document.getElementById('alloc-selected-count').textContent = n;
  document.getElementById('alloc-bulk-btn').disabled = n === 0;
}

async function doAllocateSingle(idx, btn) {
  const item = allocItems[idx];
  const ngnhan = document.getElementById('alloc-date').value || today();
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  try {
    const res = await API.allocate(item.mact, item.mavt, ngnhan);
    if (res.success) {
      showToast(`Đã cấp phát: ${item.tenvt || item.mavt}`, 'success');
      allocSelectedKeys.delete(`${item.mact}-${item.mavt}`);
      allocItems.splice(idx, 1);
      if (allocItems.length === 0) {
        document.getElementById('alloc-table-wrap').classList.add('d-none');
        document.getElementById('alloc-empty').classList.remove('d-none');
      } else {
        renderAllocateList();
      }
      updateAllocBulkBtn();
    } else {
      showToast('Lỗi: ' + (res.message || ''), 'danger');
      btn.disabled = false;
      btn.innerHTML = origHtml;
    }
  } catch (err) {
    showToast('Lỗi: ' + err.message, 'danger');
    btn.disabled = false;
    btn.innerHTML = origHtml;
  }
}

async function doAllocateBulk() {
  if (allocSelectedKeys.size === 0) return;
  const bulkBtn = document.getElementById('alloc-bulk-btn');
  const origHtml = bulkBtn.innerHTML;
  bulkBtn.disabled = true;
  bulkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xử lý...';
  const ngnhan = document.getElementById('alloc-date').value || today();
  const tasks = allocItems
    .map((item, idx) => ({ item, idx, key: `${item.mact}-${item.mavt}` }))
    .filter(t => allocSelectedKeys.has(t.key));
  const results = await Promise.allSettled(
    tasks.map(t => API.allocate(t.item.mact, t.item.mavt, ngnhan))
  );
  let success = 0, failed = 0;
  const doneKeys = new Set();
  results.forEach((r, i) => {
    if (r.status === 'fulfilled' && r.value?.success) { success++; doneKeys.add(tasks[i].key); }
    else failed++;
  });
  allocItems = allocItems.filter(item => !doneKeys.has(`${item.mact}-${item.mavt}`));
  allocSelectedKeys.clear();
  if (success) showToast(`Đã cấp phát ${success} vật tư${failed ? ', ' + failed + ' lỗi' : ''}`, failed ? 'warning' : 'success');
  if (allocItems.length === 0) {
    document.getElementById('alloc-table-wrap').classList.add('d-none');
    document.getElementById('alloc-empty').classList.remove('d-none');
  } else {
    renderAllocateList();
  }
  updateAllocBulkBtn();
  bulkBtn.disabled = false;
  bulkBtn.innerHTML = origHtml;
}

// ====================================================================
// INIT
// ====================================================================
document.addEventListener('DOMContentLoaded', () => {
  initTabs();
  initCertificatesTab();
});
