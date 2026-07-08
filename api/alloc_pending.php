<?php
/**
 * alloc_pending.php
 * Trả về danh sách vật tư chưa được cấp phát (sl = 0) của 1 nhân viên
 * bằng 1 truy vấn JOIN duy nhất thay vì N+1 requests.
 *
 * GET params:
 *   manv     (bắt buộc) - mã nhân viên
 *   to_date  (tùy chọn) - lọc chứng từ đến ngày này (YYYY-MM-DD)
 *
 * Response: { success: true, data: [ { mact, manv, tennhanvien, mavt, tenvt, dvt, dmtg, sl, ngnhan, ngnhantt } ] }
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Chỉ hỗ trợ GET', 405);
    exit;
}

if (empty($_GET['manv'])) {
    sendError('Thiếu tham số manv');
    exit;
}

$manv    = mysqli_real_escape_string($conn, trim($_GET['manv']));
$to_date = isset($_GET['to_date']) ? mysqli_real_escape_string($conn, trim($_GET['to_date'])) : '';

// --- Kiểm tra các cột tuỳ chọn 1 lần duy nhất ---
$optionalCols = ['so_luong_yeu_cau', 'so_luong_cap', 'size_label', 'mau_label', 'loai_label', 'quycach_label'];
$existingExtra = [];
foreach ($optionalCols as $col) {
    $colEsc   = mysqli_real_escape_string($conn, $col);
    $checkSql = "SELECT 1 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name   = 'bhld_ctctu'
                   AND column_name  = '$colEsc' LIMIT 1";
    $r = mysqli_query($conn, $checkSql);
    if ($r && mysqli_num_rows($r) > 0) {
        $existingExtra[] = "ctd.$col";
    }
}

$selectExtra = empty($existingExtra) ? '' : ', ' . implode(', ', $existingExtra);

$dateFilter = '';
if (!empty($to_date)) {
    $dateFilter = " AND ctu.ngct <= '$to_date'";
}

$sql = "SELECT
            ctd.mact,
            ctu.manv,
            nv.tennhanvien,
            ctd.mavt,
            vt.tenvt,
            vt.dvt,
            ctd.dmtg,
            ctd.sl,
            ctd.ngnhan,
            ctd.ngnhantt
            $selectExtra
        FROM bhld_ctctu ctd
        JOIN bhld_ctu   ctu ON ctd.mact  = ctu.mact
        LEFT JOIN bhld_nhanvien  nv  ON ctu.manv  = nv.manv
        LEFT JOIN bhld_dmvattu   vt  ON ctd.mavt  = vt.mavt
        WHERE ctu.manv = '$manv'
          AND ctd.sl   = 0
          $dateFilter
        ORDER BY ctd.mact ASC, ctd.mavt ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    sendError('Lỗi truy vấn: ' . mysqli_error($conn));
    exit;
}

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

sendSuccess($data, 'OK');
