<?php
/**
 * API Kiểm tra nhân viên chưa được cấp phát trong tháng
 * GET /check_uncapped.php
 *
 * Tham số:
 *   month = tháng kiểm tra, định dạng YYYY-MM (mặc định: tháng hiện tại)
 *   mapb  = lọc theo mã phòng ban (tuỳ chọn)
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') { http_response_code(200); exit; }
if ($method !== 'GET') { sendError('Method không được hỗ trợ', 405); }

$monthParam = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) $monthParam = date('Y-m');
$fromDate = $monthParam . '-01';
$toDate   = date('Y-m-t', strtotime($fromDate));

$mapb     = isset($_GET['mapb']) ? trim($_GET['mapb']) : '';
$pbFilter = $mapb !== '' ? "AND nv.mapb = ?" : '';

// ---------------------------------------------------------------
// Nhóm 2: NV có chứng từ trong tháng nhưng CHƯA cấp phát vật tư nào (sl > 0)
// Dùng LEFT JOIN + IS NULL thay vì NOT EXISTS cho hiệu năng tốt hơn.
// ---------------------------------------------------------------
$sqlNoAllocate = "SELECT
        nv.manv,
        nv.tennhanvien,
        nv.mapb,
        pb.tenphong   AS tenphongban,
        'no_allocate' AS ly_do,
        ct.mact,
        ct.ngct
    FROM bhld_nhanvien nv
    LEFT JOIN bhld_phongban pb ON pb.mapb = nv.mapb
    JOIN bhld_ctu ct ON ct.manv = nv.manv
              AND ct.ngct >= '$fromDate'
              AND ct.ngct <= '$toDate'
    LEFT JOIN bhld_ctctu ctu_chk ON ctu_chk.mact = ct.mact AND ctu_chk.sl > 0
    WHERE ctu_chk.mact IS NULL
    $pbFilter
    ORDER BY nv.mapb, nv.tennhanvien";

$noAllocateList = [];
// Hàm thực thi query có hoặc không bind param mapb
function execQuery($conn, $sql, $mapb) {
    if ($mapb !== '') {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 's', $mapb);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt);
    }
    return mysqli_query($conn, $sql);
}

$r2 = execQuery($conn, $sqlNoAllocate, $mapb);
if (!$r2) sendError('Lỗi truy vấn: ' . mysqli_error($conn), 500);
while ($r = mysqli_fetch_assoc($r2)) $noAllocateList[] = $r;

// Danh sách phòng ban
$resPb  = mysqli_query($conn, "SELECT mapb, tenphong FROM bhld_phongban ORDER BY mapb");
$pbList = [];
if ($resPb) while ($pb = mysqli_fetch_assoc($resPb)) $pbList[] = $pb;

// Tổng NV
if ($mapb !== '') {
    $stmtTong = mysqli_prepare($conn, "SELECT COUNT(*) AS tong FROM bhld_nhanvien WHERE mapb = ?");
    mysqli_stmt_bind_param($stmtTong, 's', $mapb);
    mysqli_stmt_execute($stmtTong);
    $rTong = mysqli_stmt_get_result($stmtTong);
} else {
    $rTong = mysqli_query($conn, "SELECT COUNT(*) AS tong FROM bhld_nhanvien");
}
$tongNV = $rTong ? intval(mysqli_fetch_assoc($rTong)['tong']) : 0;

sendSuccess([
    'month'            => $monthParam,
    'from_date'        => $fromDate,
    'to_date'          => $toDate,
    'tong_nv'          => $tongNV,
    'tong_no_allocate' => count($noAllocateList),
    'no_allocate'      => $noAllocateList,
    'phong_ban_list'   => $pbList,
], "Kiểm tra cấp phát tháng $monthParam");

mysqli_close($conn);
?>
