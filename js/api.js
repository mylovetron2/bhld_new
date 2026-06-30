/**
 * BHLD Web App - API Service
 * Kết nối với backend PHP tại diavatly.com/projectBHLD/api
 */

// Tự động lấy base URL từ trang đang chạy, không hardcode domain
const API_BASE = window.location.origin + '/projectBHLD/api';
const API_KEY = 'BHLD_INTERNAL_2026';

async function apiFetch(endpoint, options = {}) {
  const url = `${API_BASE}${endpoint}`;
  const method = (options.method || 'GET').toUpperCase();
  const requestUrl = method === 'GET'
    ? `${url}${url.includes('?') ? '&' : '?'}_t=${Date.now()}`
    : url;

  // Chỉ gửi Content-Type khi có body (POST/PUT/DELETE)
  // GET không gửi Content-Type để tránh CORS preflight không cần thiết
  const headers = method === 'GET'
    ? { 'Accept': 'application/json', 'X-BHLD-KEY': API_KEY }
    : { 'Content-Type': 'application/json; charset=UTF-8', 'Accept': 'application/json', 'X-BHLD-KEY': API_KEY };

  try {
    const response = await fetch(requestUrl, {
      ...options,
      headers,
      credentials: 'include',
    });

    if (response.status === 404) throw new Error('Không tìm thấy dữ liệu');
    if (response.status === 500) throw new Error('Lỗi máy chủ');
    if (!response.ok) throw new Error(`Lỗi HTTP: ${response.status}`);

    const data = await response.json();
    if (method !== 'GET' && data && data.success) {
      window.dispatchEvent(new CustomEvent('bhld:data-changed', {
        detail: { endpoint, method, at: Date.now() }
      }));
    }
    return data;
  } catch (err) {
    if (err instanceof TypeError) {
      // TypeError xảy ra khi: mạng thực sự lỗi, CORS bị chặn, hoặc mở file://
      const isFileProtocol = location.protocol === 'file:';
      if (isFileProtocol) {
        throw new Error(
          'Không thể gọi API khi mở file trực tiếp (file://). ' +
          'Vui lòng deploy lên web server hoặc dùng Live Server.'
        );
      }
      throw new Error(
        `Lỗi kết nối tới server (${API_BASE}). ` +
        'Có thể do CORS, server chưa bật, hoặc mất mạng. ' +
        `Chi tiết: ${err.message}`
      );
    }
    throw err;
  }
}

const API = {
  // ===== AUTH =====
  login(username, password) {
    return apiFetch('/auth_login.php', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    });
  },
  logout() {
    return apiFetch('/auth_logout.php', {
      method: 'POST',
    });
  },
  me() {
    return apiFetch('/auth_me.php');
  },

  // ===== ĐỊNH MỨC =====
  getDinhMuc() {
    return apiFetch('/dinhmuc.php');
  },

  // ===== NHÂN VIÊN =====
  getEmployees(search) {
    const params = search ? `?search=${encodeURIComponent(search)}` : '';
    return apiFetch(`/employees.php${params}`);
  },
  getEmployee(manv) {
    return apiFetch(`/employees.php?manv=${encodeURIComponent(manv)}`);
  },
  addEmployee(employee) {
    return apiFetch('/employees.php', {
      method: 'POST',
      body: JSON.stringify(employee),
    });
  },
  updateEmployeeName(manv, tennhanvien) {
    return apiFetch('/employees.php', {
      method: 'PUT',
      body: JSON.stringify({ manv, tennhanvien }),
    });
  },
  updateEmployee(payload) {
    return apiFetch('/employees.php', {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },
  deleteEmployee(manv) {
    return apiFetch('/employees.php', {
      method: 'DELETE',
      body: JSON.stringify({ manv }),
    });
  },

  // ===== POLICY THEO NHAN VIEN =====
  getEmployeePolicy(manv, includeInactive = false) {
    const q = new URLSearchParams();
    if (manv) q.set('manv', manv);
    if (includeInactive) q.set('include_inactive', '1');
    const qs = q.toString();
    return apiFetch(`/employee_equipment_policy.php${qs ? '?' + qs : ''}`);
  },
  saveEmployeePolicyBatch(manv, items, sync = true) {
    return apiFetch('/employee_equipment_policy.php', {
      method: 'PUT',
      body: JSON.stringify({ manv, items, sync: sync ? 1 : 0 }),
    });
  },
  addEmployeePolicy(item) {
    return apiFetch('/employee_equipment_policy.php', {
      method: 'POST',
      body: JSON.stringify(item),
    });
  },
  disableEmployeePolicy(manv, mavt) {
    return apiFetch('/employee_equipment_policy.php', {
      method: 'DELETE',
      body: JSON.stringify({ manv, mavt }),
    });
  },

  // ===== CHỨNG TỪ =====
  getCertificates({ manv, mact_search, from_date, to_date, limit } = {}) {
    const q = new URLSearchParams();
    if (manv) q.set('manv', manv);
    if (mact_search) q.set('mact_search', mact_search);
    if (from_date) q.set('from_date', from_date);
    if (to_date) q.set('to_date', to_date);
    if (limit) q.set('limit', limit);
    const qs = q.toString();
    return apiFetch(`/certificates.php${qs ? '?' + qs : ''}`);
  },
  createCertificate(cert) {
    return apiFetch('/certificates.php', {
      method: 'POST',
      body: JSON.stringify(cert),
    });
  },
  updateCertificate(cert) {
    return apiFetch('/certificates.php', {
      method: 'PUT',
      body: JSON.stringify(cert),
    });
  },
  deleteCertificate(mact) {
    return apiFetch('/certificates.php', {
      method: 'DELETE',
      body: JSON.stringify({ mact }),
    });
  },

  // ===== CHI TIẾT CHỨNG TỪ =====
  getCertificateDetails(mact) {
    return apiFetch(`/certificate_details.php?mact=${encodeURIComponent(mact)}`);
  },
  createCertificateDetail(detail) {
    return apiFetch('/certificate_details.php', {
      method: 'POST',
      body: JSON.stringify(detail),
    });
  },
  updateCertificateDetail(detail) {
    return apiFetch('/certificate_details.php', {
      method: 'PUT',
      body: JSON.stringify(detail),
    });
  },
  deleteCertificateDetail(mact, mavt) {
    return apiFetch('/certificate_details.php', {
      method: 'DELETE',
      body: JSON.stringify({ mact, mavt }),
    });
  },

  // ===== VẬT TƯ =====
  getEquipment(search) {
    const params = search ? `?search=${encodeURIComponent(search)}` : '';
    return apiFetch(`/equipment.php${params}`);
  },
  addEquipment(item) {
    return apiFetch('/equipment.php', {
      method: 'POST',
      body: JSON.stringify(item),
    });
  },
  updateEquipment(item) {
    return apiFetch('/equipment.php', {
      method: 'PUT',
      body: JSON.stringify(item),
    });
  },
  deleteEquipment(mavt) {
    return apiFetch('/equipment.php', {
      method: 'DELETE',
      body: JSON.stringify({ mavt }),
    });
  },

  // ===== CẤP PHÁT / TRẢ =====
  allocate(mact, mavt, ngnhan) {
    return apiFetch('/allocate_new.php', {
      method: 'POST',
      body: JSON.stringify({ mact, mavt, ngnhan }),
    });
  },
  allocateFirst({ mact, manv, ngct, mapb, madm, vattu }) {
    return apiFetch('/allocate_first.php', {
      method: 'POST',
      body: JSON.stringify({ mact, manv, ngct, mapb, madm, vattu }),
    });
  },
  deallocate(mact, mavt) {
    return apiFetch('/deallocate_v2.php', {
      method: 'POST',
      body: JSON.stringify({ mact, mavt }),
    });
  },

  // ===== LỊCH SỬ CẤP PHÁT =====
  getAllocationHistory({ manv, mavt, from_date, to_date, status } = {}) {
    const q = new URLSearchParams();
    if (manv) q.set('manv', manv);
    if (mavt) q.set('mavt', mavt);
    if (from_date) q.set('from_date', from_date);
    if (to_date) q.set('to_date', to_date);
    if (status) q.set('status', status);
    const qs = q.toString();
    return apiFetch(`/allocation_history.php${qs ? '?' + qs : ''}`);
  },

  getChangeHistory({ table, id, action, from_date, to_date, limit, page } = {}) {
    const q = new URLSearchParams();
    if (table)     q.set('table', table);
    if (id)        q.set('id', id);
    if (action)    q.set('action', action);
    if (from_date) q.set('from_date', from_date);
    if (to_date)   q.set('to_date', to_date);
    if (limit)     q.set('limit', limit);
    if (page)      q.set('page', page);
    return apiFetch(`/change_history.php?${q.toString()}`);
  },

  // ===== BÁO CÁO THÁNG =====
  getMonthlyReport(month) {
    return apiFetch(`/monthly_report.php?month=${encodeURIComponent(month)}`);
  },

  // ===== BACKUP DATABASE =====
  async downloadDatabaseBackup() {
    const res = await fetch(`${API_BASE}/database_backup.php`, {
      method: 'GET',
      credentials: 'include',
      headers: { 'Accept': 'application/sql,application/octet-stream,*/*' },
    });

    if (!res.ok) {
      let msg = `Lỗi HTTP: ${res.status}`;
      try {
        const j = await res.json();
        if (j && j.message) msg = j.message;
      } catch (_) {
        // Ignore non-json error body.
      }
      throw new Error(msg);
    }

    const blob = await res.blob();
    const dispo = res.headers.get('Content-Disposition') || '';
    const m = dispo.match(/filename="?([^";]+)"?/i);
    const fileName = m && m[1] ? m[1] : `bhld_backup_${Date.now()}.sql`;

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);

    return { fileName };
  },

  async restoreDatabaseBackup(sqlFile, confirmText) {
    const form = new FormData();
    form.append('sql_file', sqlFile);
    form.append('confirm', confirmText || '');

    const res = await fetch(`${API_BASE}/database_restore.php`, {
      method: 'POST',
      body: form,
      credentials: 'include',
      headers: { 'Accept': 'application/json' },
    });

    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.success) {
      throw new Error(data?.message || `Lỗi HTTP: ${res.status}`);
    }
    return data;
  },
};
