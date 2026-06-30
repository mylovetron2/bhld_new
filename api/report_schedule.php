<?php
/**
 * API Báo cáo chi tiết vật tư cần cấp phát theo nhân viên / bộ phận
 * GET /report_schedule.php
 *
 * Tham số:
 *   months  = số tháng dự báo (mặc định 3, tối đa 12)
 *   mapb    = lọc theo mã phòng ban (tuỳ chọn)
 *   manv    = lọc theo mã nhân viên (tuỳ chọn)
 *   group   = 'employee' (mặc định) | 'department' | 'month'
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') { http_response_code(200); exit; }
if ($method !== 'GET') { sendError('Method không được hỗ trợ', 405); }

$months  = max(1, min(12, intval(isset($_GET['months']) ? $_GET['months'] : 3)));
$mapb    = isset($_GET['mapb'])   ? mysqli_real_escape_string($conn, trim($_GET['mapb']))   : '';
$manv    = isset($_GET['manv'])   ? mysqli_real_escape_string($conn, trim($_GET['manv']))   : '';
$group   = isset($_GET['group'])  ? trim($_GET['group'])  : 'employee';

$today    = date('Y-m-d');
$deadline = date('Y-m-d', strtotime("+$months months"));

// ---------------------------------------------------------------
// Base WHERE
// ---------------------------------------------------------------
$where = "ct.sl = 1
          AND ct.ngnhantt != '1911-11-11'
          AND ct.ngnhantt >= '$today'
          AND ct.ngnhantt <= '$deadline'";

if ($mapb !== '') $where .= " AND nv.mapb = '$mapb'";
if ($manv !== '') $where .= " AND nv.manv = '$manv'";

// ---------------------------------------------------------------
// Lấy chi tiết từng dòng (nhân viên x vật tư)
// ---------------------------------------------------------------
$sqlDetail = "SELECT
        nv.manv,
        nv.tennhanvien,
        nv.mapb,
        pb.tenphong                                  AS tenphongban,
        ct.mavt,
        d.tenvt,
        d.dvt,
        ct.ngnhan,
        ct.ngnhantt,
        DATEDIFF(ct.ngnhantt, CURDATE())             AS con_lai_ngay,
        DATE_FORMAT(ct.ngnhantt, '%m/%Y')            AS thang_cap
    FROM bhld_ctctu ct
    JOIN bhld_ctu   ctu ON ctu.mact = ct.mact
    JOIN bhld_nhanvien nv ON nv.manv = ctu.manv
    LEFT JOIN bhld_phongban pb ON pb.mapb = nv.mapb
    JOIN bhld_dmvattu d ON d.mavt = ct.mavt
    WHERE $where
    ORDER BY nv.mapb, nv.manv, ct.ngnhantt ASC
    LIMIT 2000";

$resDetail = mysqli_query($conn, $sqlDetail);
if (!$resDetail) sendError('Lỗi truy vấn: ' . mysqli_error($conn), 500);

$rows = [];
while ($r = mysqli_fetch_assoc($resDetail)) {
    $r['con_lai_ngay'] = intval($r['con_lai_ngay']);
    $rows[] = $r;
}

// ---------------------------------------------------------------
// Nhóm dữ liệu theo yêu cầu
// ---------------------------------------------------------------
$grouped = [];

foreach ($rows as $r) {
    switch ($group) {
        case 'department':
            $key   = $r['mapb'];
            $label = $r['tenphongban'] ?: $r['mapb'];
            break;
        case 'month':
            $key   = $r['thang_cap'];
            $label = 'Tháng ' . $r['thang_cap'];
            break;
        default: // employee
            $key   = $r['manv'];
            $label = $r['tennhanvien'] . ' [' . $r['manv'] . ']';
            break;
    }

    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'key'      => $key,
            'label'    => $label,
            'mapb'     => $r['mapb'],
            'tenphongban' => $r['tenphongban'],
            'items'    => [],
            'tong'     => 0,
        ];
    }
    $grouped[$key]['items'][] = $r;
    $grouped[$key]['tong']++;
}

// Sắp xếp nhóm
ksort($grouped);

// ---------------------------------------------------------------
// Thống kê tổng hợp
// ---------------------------------------------------------------
$tongNhanVien  = count(array_unique(array_column($rows, 'manv')));
$tongPhongBan  = count(array_unique(array_column($rows, 'mapb')));
$tongLoaiVT    = count(array_unique(array_column($rows, 'mavt')));
$tongSuatCap   = count($rows);

// Danh sách phòng ban để lọc
$sqlPb = "SELECT DISTINCT nv.mapb, pb.tenphong
          FROM bhld_ctctu ct
          JOIN bhld_ctu ctu ON ctu.mact = ct.mact
          JOIN bhld_nhanvien nv ON nv.manv = ctu.manv
          LEFT JOIN bhld_phongban pb ON pb.mapb = nv.mapb
          WHERE ct.sl = 1 AND ct.ngnhantt != '1911-11-11'
            AND ct.ngnhantt >= '$today' AND ct.ngnhantt <= '$deadline'
          ORDER BY nv.mapb";
$resPb = mysqli_query($conn, $sqlPb);
$phongBanList = [];
if ($resPb) {
    while ($pb = mysqli_fetch_assoc($resPb)) $phongBanList[] = $pb;
}

sendSuccess([
    'months'        => $months,
    'from_date'     => $today,
    'to_date'       => $deadline,
    'group'         => $group,
    'tong_nhan_vien'=> $tongNhanVien,
    'tong_phong_ban'=> $tongPhongBan,
    'tong_loai_vt'  => $tongLoaiVT,
    'tong_suat_cap' => $tongSuatCap,
    'phong_ban_list'=> $phongBanList,
    'grouped'       => array_values($grouped),
    'detail'        => $rows,
], "Báo cáo lịch cấp phát $months tháng tới");

mysqli_close($conn);
?>
