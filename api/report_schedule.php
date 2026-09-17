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

// Các thuộc tính chi tiết được bổ sung theo từng phiên bản CSDL.
$detailColumns = ['size_label', 'mau_label', 'loai_label', 'quycach_label'];
$detailSelect = [];
$detailGroupExpressions = [];
$existingDetailColumns = [];
$hasQtyRequired = false;
$hasQtyIssued = false;
foreach ($detailColumns as $column) {
    $check = mysqli_query($conn, "SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'bhld_ctctu'
          AND column_name = '$column' LIMIT 1");
    if ($check && mysqli_num_rows($check) > 0) {
        $existingDetailColumns[] = $column;
    } else {
        $detailSelect[$column] = "'' AS $column";
    }
}
$profileTableCheck = mysqli_query($conn, "SELECT 1 FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'bhld_nhanvien_hoso' LIMIT 1");
$hasEmployeeProfile = $profileTableCheck && mysqli_num_rows($profileTableCheck) > 0;

// Dữ liệu cũ thường lưu size trong hồ sơ nhân viên thay vì trên dòng cấp phát.
$equipmentName = "LOWER(REPLACE(REPLACE(REPLACE(d.tenvt, ' ', ''), 'đ', 'd'), 'Đ', 'D'))";
$profileJoin = $hasEmployeeProfile
    ? 'LEFT JOIN bhld_nhanvien_hoso hs ON hs.manv = nv.manv'
    : '';
$ctSizeExpr = in_array('size_label', $existingDetailColumns, true) ? 'ct.size_label' : "''";
$ctColorExpr = in_array('mau_label', $existingDetailColumns, true) ? 'ct.mau_label' : "''";
$ctTypeExpr = in_array('loai_label', $existingDetailColumns, true) ? 'ct.loai_label' : "''";
$ctSpecExpr = in_array('quycach_label', $existingDetailColumns, true) ? 'ct.quycach_label' : "''";
$sizeEligible = "(LOWER(d.tenvt) LIKE '%giày%' OR $equipmentName LIKE '%giay%'
    OR LOWER(d.tenvt) LIKE '%ủng%' OR $equipmentName LIKE '%ung%'
    OR LOWER(d.tenvt) LIKE '%quần áo%' OR LOWER(d.tenvt) LIKE '%quầnáo%'
    OR LOWER(d.tenvt) LIKE '%áo quần%' OR LOWER(d.tenvt) LIKE '%áoquần%'
    OR $equipmentName LIKE '%quanao%')";
$sizeExpr = $hasEmployeeProfile
    ? "CASE WHEN $sizeEligible THEN COALESCE(NULLIF($ctSizeExpr, ''), CASE
            WHEN LOWER(d.tenvt) LIKE '%giày%' OR $equipmentName LIKE '%giay%' OR LOWER(d.tenvt) LIKE '%ủng%' OR $equipmentName LIKE '%ung%' THEN hs.giay_size
            WHEN LOWER(d.tenvt) LIKE '%quần áo%' OR LOWER(d.tenvt) LIKE '%quầnáo%'
                OR LOWER(d.tenvt) LIKE '%áo quần%' OR LOWER(d.tenvt) LIKE '%áoquần%'
                OR $equipmentName LIKE '%quanao%' THEN hs.quanao_size
            ELSE NULL END)
        ELSE NULL END"
    : "CASE WHEN $sizeEligible THEN $ctSizeExpr ELSE NULL END";
$colorExpr = $hasEmployeeProfile
    ? "COALESCE(NULLIF($ctColorExpr, ''), CASE
            WHEN LOWER(d.tenvt) LIKE '%mũ%' OR $equipmentName LIKE '%mu%' OR LOWER(d.tenvt) LIKE '%nón%' OR $equipmentName LIKE '%non%' THEN hs.mu_mau
            ELSE NULL END)"
    : $ctColorExpr;
$typeExpr = $ctTypeExpr;
$specExpr = $ctSpecExpr;
if ($hasEmployeeProfile) {
    $specExpr = "COALESCE(NULLIF($ctSpecExpr, ''), CONCAT_WS(' - ',
        CASE WHEN $sizeExpr IS NOT NULL AND $sizeExpr <> '' THEN CONCAT('Size ', $sizeExpr) END,
        CASE WHEN $colorExpr IS NOT NULL AND $colorExpr <> '' THEN CONCAT('Mau ', $colorExpr) END,
        CASE WHEN $typeExpr IS NOT NULL AND $typeExpr <> '' THEN CONCAT('Loai ', $typeExpr) END))";
}
$detailExpressions = [
    'size_label' => $sizeExpr,
    'mau_label' => $colorExpr,
    'loai_label' => $typeExpr,
    'quycach_label' => $specExpr,
];
foreach ($detailColumns as $column) {
    if (in_array($column, $existingDetailColumns, true) || $hasEmployeeProfile) {
        $detailSelect[$column] = $detailExpressions[$column] . " AS $column";
        $detailGroupExpressions[] = $detailExpressions[$column];
    }
}
$qtyCheck = mysqli_query($conn, "SELECT column_name FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'bhld_ctctu'
      AND column_name IN ('so_luong_yeu_cau', 'so_luong_cap')");
if ($qtyCheck) {
    while ($qtyColumn = mysqli_fetch_assoc($qtyCheck)) {
        if ($qtyColumn['column_name'] === 'so_luong_yeu_cau') $hasQtyRequired = true;
        if ($qtyColumn['column_name'] === 'so_luong_cap') $hasQtyIssued = true;
    }
}
$quantityExpr = '1';
if ($hasQtyRequired && $hasQtyIssued) {
    $quantityExpr = 'COALESCE(NULLIF(ct.so_luong_yeu_cau, 0), NULLIF(ct.so_luong_cap, 0), 1)';
} elseif ($hasQtyRequired) {
    $quantityExpr = 'COALESCE(NULLIF(ct.so_luong_yeu_cau, 0), 1)';
} elseif ($hasQtyIssued) {
    $quantityExpr = 'COALESCE(NULLIF(ct.so_luong_cap, 0), 1)';
}
$detailSelectSql = implode(",\n        ", $detailSelect);
$detailGroupBySql = empty($detailGroupExpressions)
    ? ''
    : ', ' . implode(', ', $detailGroupExpressions);

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
        $detailSelectSql,
        $quantityExpr AS so_luong_can_cap,
        ct.ngnhan,
        ct.ngnhantt,
        DATEDIFF(ct.ngnhantt, CURDATE())             AS con_lai_ngay,
        DATE_FORMAT(ct.ngnhantt, '%m/%Y')            AS thang_cap
    FROM bhld_ctctu ct
    JOIN bhld_ctu   ctu ON ctu.mact = ct.mact
    JOIN bhld_nhanvien nv ON nv.manv = ctu.manv
    LEFT JOIN bhld_phongban pb ON pb.mapb = nv.mapb
    $profileJoin
    JOIN bhld_dmvattu d ON d.mavt = ct.mavt
    WHERE $where
    ORDER BY nv.mapb, nv.manv, ct.ngnhantt ASC";

$resDetail = mysqli_query($conn, $sqlDetail);
if (!$resDetail) sendError('Lỗi truy vấn: ' . mysqli_error($conn), 500);

$rows = [];
while ($r = mysqli_fetch_assoc($resDetail)) {
    $r['con_lai_ngay'] = intval($r['con_lai_ngay']);
    $r['so_luong_can_cap'] = max(1, intval($r['so_luong_can_cap']));
    $rows[] = $r;
}

// ---------------------------------------------------------------
// Thống kê chi tiết theo từng loại vật tư
// ---------------------------------------------------------------
$sqlByType = "SELECT
        ct.mavt,
        d.tenvt,
        d.dvt,
        $detailSelectSql,
        SUM($quantityExpr) AS tong_suat,
        COUNT(DISTINCT ctu.manv) AS so_nhan_vien,
        COUNT(DISTINCT ctu.mact) AS so_chung_tu,
        MIN(ct.ngnhantt) AS ngay_cap_gan_nhat
    FROM bhld_ctctu ct
    JOIN bhld_ctu ctu ON ctu.mact = ct.mact
    JOIN bhld_nhanvien nv ON nv.manv = ctu.manv
    $profileJoin
    JOIN bhld_dmvattu d ON d.mavt = ct.mavt
    WHERE $where
    GROUP BY ct.mavt, d.tenvt, d.dvt$detailGroupBySql
    ORDER BY tong_suat DESC, d.tenvt ASC";

$resByType = mysqli_query($conn, $sqlByType);
if (!$resByType) sendError('Lỗi truy vấn thống kê theo loại: ' . mysqli_error($conn), 500);

$byType = [];
while ($r = mysqli_fetch_assoc($resByType)) {
    $r['tong_suat'] = intval($r['tong_suat']);
    $r['so_nhan_vien'] = intval($r['so_nhan_vien']);
    $r['so_chung_tu'] = intval($r['so_chung_tu']);
    $byType[] = $r;
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
    $grouped[$key]['tong'] += $r['so_luong_can_cap'];
}

// Sắp xếp nhóm
ksort($grouped);

// ---------------------------------------------------------------
// Thống kê tổng hợp
// ---------------------------------------------------------------
$tongNhanVien  = count(array_unique(array_column($rows, 'manv')));
$tongPhongBan  = count(array_unique(array_column($rows, 'mapb')));
$tongLoaiVT    = count($byType);
$tongSuatCap   = array_sum(array_column($rows, 'so_luong_can_cap'));

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
    'by_type'       => $byType,
    'grouped'       => array_values($grouped),
    'detail'        => $rows,
], "Báo cáo lịch cấp phát $months tháng tới");

mysqli_close($conn);
?>
