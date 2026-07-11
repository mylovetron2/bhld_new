<?php
/**
 * API Kiểm tra nhân viên có chứng từ nhưng chưa được cấp phát đến tháng chọn
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

$detailLimit = isset($_GET['detail_limit']) ? intval($_GET['detail_limit']) : 300;
if ($detailLimit < 1) $detailLimit = 300;
if ($detailLimit > 5000) $detailLimit = 5000;

$mapb     = isset($_GET['mapb']) ? trim($_GET['mapb']) : '';
// Điều kiện NV dùng dạng index-friendly (không bọc cột trong hàm)
$empFilter = "(nv.trangthai IS NULL OR nv.trangthai = 1) AND nv.tennhanvien IS NOT NULL AND nv.tennhanvien <> ''";
$pbFilterStr = $mapb !== '' ? "AND nv.mapb = '$mapbEsc'" : '';

// ---------------------------------------------------------------
// Derived table: các mact có ít nhất 1 dòng chi tiết nhưng KHÔNG CÓ sl > 0
// Tính 1 lần, JOIN vào thay vì correlated subquery / LEFT JOIN+IS NULL mỗi dòng
// → MySQL chỉ scan bhld_ctctu 1 lần, kết quả được cache trong derived table
// ---------------------------------------------------------------
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

$mapbEsc = mysqli_real_escape_string($conn, $mapb);
$pbFilterStr = $mapbEsc !== '' ? "AND nv.mapb = '$mapbEsc'" : '';

// Lấy tập mact chưa cấp phát (có chi tiết nhưng tất cả sl = 0)
$sqlNoAllocBase = "SELECT DISTINCT ct.manv, nv.tennhanvien, nv.mapb,
        pb.tenphong AS tenphongban, ct.mact, ct.ngct
    FROM bhld_ctu ct
    JOIN (
        SELECT mact
        FROM bhld_ctctu
        GROUP BY mact
        HAVING SUM(CASE WHEN sl > 0 THEN 1 ELSE 0 END) = 0
    ) ct_noalloc ON ct_noalloc.mact = ct.mact
    JOIN bhld_nhanvien nv ON nv.manv = ct.manv
    LEFT JOIN bhld_phongban pb ON pb.mapb = nv.mapb
    WHERE ct.ngct <= '$toDate'
      AND $empFilter
      $pbFilterStr";

// COUNT nhanh: dùng cùng derived table
$sqlNoAllocateCount = "SELECT COUNT(DISTINCT ct.manv) AS tong_no_allocate_nv
    FROM bhld_ctu ct
    JOIN (
        SELECT mact
        FROM bhld_ctctu
        GROUP BY mact
        HAVING SUM(CASE WHEN sl > 0 THEN 1 ELSE 0 END) = 0
    ) ct_noalloc ON ct_noalloc.mact = ct.mact
    JOIN bhld_nhanvien nv ON nv.manv = ct.manv
    WHERE ct.ngct <= '$toDate'
      AND $empFilter
      $pbFilterStr";

$rNoAllocCount = mysqli_query($conn, $sqlNoAllocateCount);
if (!$rNoAllocCount) sendError('Lỗi truy vấn tổng chưa cấp phát: ' . mysqli_error($conn), 500);
$_rowCount = mysqli_fetch_assoc($rNoAllocCount);
$tongNoAllocateNv = isset($_rowCount['tong_no_allocate_nv']) ? intval($_rowCount['tong_no_allocate_nv']) : 0;

$sqlNoAllocate = $sqlNoAllocBase . " ORDER BY ct.ngct DESC LIMIT $detailLimit";

$noAllocateList = [];
$r2 = mysqli_query($conn, $sqlNoAllocate);
if (!$r2) sendError('Lỗi truy vấn: ' . mysqli_error($conn), 500);
while ($r = mysqli_fetch_assoc($r2)) $noAllocateList[] = $r;

// ---------------------------------------------------------------
// Thống kê đã cấp phát theo từng loại vật tư (sl > 0)
// Chỉ tính trong THÁNG đang chọn để bám đúng kỳ thống kê hiển thị.
// ---------------------------------------------------------------
// Dùng ctd.ngnhan (ngày thực tế nhận) thay vì ct.ngct (ngày chứng từ)
// để thống kê đúng theo tháng cấp phát thực tế, không phụ thuộc ngày tạo chứng từ.
$sqlIssuedByType = "SELECT
        ctd.mavt,
        COALESCE(vt.tenvt, CONCAT('Mã ', ctd.mavt)) AS tenvt,
        COALESCE(vt.dvt, '') AS dvt,
        SUM(ctd.sl) AS tong_sl_cap,
        COUNT(DISTINCT ct.manv) AS so_nv,
        COUNT(DISTINCT ct.mact) AS so_ct
    FROM bhld_ctctu ctd
    JOIN bhld_ctu ct ON ct.mact = ctd.mact
    JOIN bhld_nhanvien nv ON nv.manv = ct.manv
    LEFT JOIN bhld_dmvattu vt ON vt.mavt = ctd.mavt
    WHERE ctd.ngnhan >= '$fromDate'
      AND ctd.ngnhan <= '$toDate'
      AND ctd.ngnhan <> '1911-11-11'
      AND ctd.sl > 0
      AND $empFilter
      $pbFilterStr
    GROUP BY ctd.mavt, vt.tenvt, vt.dvt
    ORDER BY tong_sl_cap DESC, tenvt ASC";

$issuedByType = [];
$rIssued = mysqli_query($conn, $sqlIssuedByType);
if (!$rIssued) sendError('Lỗi truy vấn thống kê cấp phát: ' . mysqli_error($conn), 500);
while ($r = mysqli_fetch_assoc($rIssued)) {
    $r['tong_sl_cap'] = intval($r['tong_sl_cap']);
    $r['so_nv'] = intval($r['so_nv']);
    $r['so_ct'] = intval($r['so_ct']);
    $issuedByType[] = $r;
}

$tongSlDaCap = 0;
foreach ($issuedByType as $it) {
    $tongSlDaCap += intval($it['tong_sl_cap']);
}

// Danh sách phòng ban
$resPb  = mysqli_query($conn, "SELECT mapb, tenphong FROM bhld_phongban ORDER BY mapb");
$pbList = [];
if ($resPb) while ($pb = mysqli_fetch_assoc($resPb)) $pbList[] = $pb;

// Tổng NV (dùng điều kiện index-friendly)
$sqlTongNV = "SELECT COUNT(*) AS tong FROM bhld_nhanvien nv WHERE $empFilter $pbFilterStr";
$rTong = mysqli_query($conn, $sqlTongNV);
$tongNV = $rTong ? intval(mysqli_fetch_assoc($rTong)['tong']) : 0;

sendSuccess([
    'month'            => $monthParam,
    'from_date'        => $fromDate,
    'to_date'          => $toDate,
    'tong_nv'          => $tongNV,
    'tong_no_allocate' => count($noAllocateList),
    'tong_no_allocate_nv' => $tongNoAllocateNv,
    'tong_no_allocate_ct' => count($noAllocateList),
    'no_allocate_limit' => $detailLimit,
    'tong_sl_da_cap'   => $tongSlDaCap,
    'no_allocate'      => $noAllocateList,
    'issued_by_type'   => $issuedByType,
    'phong_ban_list'   => $pbList,
], "Kiểm tra cấp phát đến tháng $monthParam");

mysqli_close($conn);
?>
