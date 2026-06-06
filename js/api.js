/**
 * BHLD Web App - API Service
 * Kết nối với backend PHP tại diavatly.com/BHLD/api
 */

const API_BASE = 'https://diavatly.cloud/projectBHLD/api';

async function apiFetch(endpoint, options = {}) {
  const url = `${API_BASE}${endpoint}`;
  const method = (options.method || 'GET').toUpperCase();

  // Chỉ gửi Content-Type khi có body (POST/PUT/DELETE)
  // GET không gửi Content-Type để tránh CORS preflight không cần thiết
  const headers = method === 'GET'
    ? { 'Accept': 'application/json' }
    : { 'Content-Type': 'application/json; charset=UTF-8', 'Accept': 'application/json' };

  try {
    const response = await fetch(url, {
      ...options,
      headers,
    });

    if (response.status === 404) throw new Error('Không tìm thấy dữ liệu');
    if (response.status === 500) throw new Error('Lỗi máy chủ');
    if (!response.ok) throw new Error(`Lỗi HTTP: ${response.status}`);

    const data = await response.json();
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
  // ===== NHÂN VIÊN =====
  getEmployees(search) {
    const params = search ? `?search=${encodeURIComponent(search)}` : '';
    return apiFetch(`/employees.php${params}`);
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

  // ===== CẤP PHÁT / TRẢ =====
  allocate(mact, mavt, ngnhan) {
    return apiFetch('/allocate_new.php', {
      method: 'POST',
      body: JSON.stringify({ mact, mavt, ngnhan }),
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
};
