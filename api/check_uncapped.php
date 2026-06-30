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

$mapb     = isset($_GET['mapb']) ? mysqli_real_escape_string($conn, trim($_GET['mapb'])) : '';
$pbFilter = $mapb !== '' ? "AND nv.mapb = '$mapb'" : '';

// ---------------------------------------------------------------
// Nhóm 1: NV chưa có chứng từ nào trong tháng
// ---------------------------------------------------------------
$sqlNoCert = "SELECT
        nv.manv,
        nv.tennhanvien,
        nv.mapb,
        pb.tenphong AS tenphongban,
        'no_cert'   AS ly_do,
        NULL        AS mact,
        NULL        AS ngct
    FROM bhld_nhanvien nv
    LEFT JOIN bhld_phongban pb ON pb.mapb = nv.mapb
    WHERE NOT EXISTS (
        SELECT 1 FROM bhld_ctu ct
        WHERE ct.manv = nv.manv
          AND ct.ngct >= '$fromDate'
          AND ct.ngct <= '$toDate'
    )
    $pbFilter
    ORDER BY nv.mapb, nv.tennhanvien";

// ---------------------------------------------------------------
// Nhóm 2: NV có chứng từ trong tháng nhưng CHƯA cấp phát vật tư nào (sl = 0 toàn bộ)
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
    WHERE NOT EXISTS (
        SELECT 1 FROM bhld_ctctu ctu
        WHERE ctu.mact = ct.mact AND ctu.sl = 1
    )
    $pbFilter
    ORDER BY nv.mapb, nv.tennhanvien";

$noCertList     = [];
$noAllocateList = [];

$r1 = mysqli_query($conn, $sqlNoCert);
if (!$r1) sendError('Lỗi truy vấn: ' . mysqli_error($conn), 500);
while ($r = mysqli_fetch_assoc($r1)) $noCertList[] = $r;

$r2 = mysqli_query($conn, $sqlNoAllocate);
if (!$r2) sendError('Lỗi truy vấn: ' . mysqli_error($conn), 500);
while ($r = mysqli_fetch_assoc($r2)) $noAllocateList[] = $r;

// Danh sách phòng ban
$resPb  = mysqli_query($conn, "SELECT mapb, tenphong FROM bhld_phongban ORDER BY mapb");
$pbList = [];
if ($resPb) while ($pb = mysqli_fetch_assoc($resPb)) $pbList[] = $pb;

// Tổng NV
$qTong  = "SELECT COUNT(*) AS tong FROM bhld_nhanvien" . ($mapb !== '' ? " WHERE mapb = '$mapb'" : '');
$rTong  = mysqli_query($conn, $qTong);
$tongNV = $rTong ? intval(mysqli_fetch_assoc($rTong)['tong']) : 0;

sendSuccess([
    'month'            => $monthParam,
    'from_date'        => $fromDate,
    'to_date'          => $toDate,
    'tong_nv'          => $tongNV,
    'tong_no_cert'     => count($noCertList),
    'tong_no_allocate' => count($noAllocateList),
    'no_cert'          => $noCertList,
    'no_allocate'      => $noAllocateList,
    'phong_ban_list'   => $pbList,
], "Kiểm tra cấp phát tháng $monthParam");

mysqli_close($conn);
?>
