<?php
error_reporting(0); ini_set('display_errors', 0);
require_once __DIR__ . '/db_connection.php';
header('Content-Type: application/json; charset=utf-8');

// ============================================================
// Query 2: NV có cùng 1 loại vật tư với SL=0 xuất hiện >= 2 lần
// ============================================================
$sql2 = "
SELECT
    ct.manv,
    nv.tennhanvien,
    nv.mapb,
    pb.tenphong,
    GROUP_CONCAT(DISTINCT vt.tenvt ORDER BY vt.tenvt SEPARATOR ', ') AS vat_tu_bi_lap,
    COUNT(*) AS tong_dong_sl0
FROM bhld_ctctu ctct
JOIN bhld_ctu ct ON ctct.mact = ct.mact
JOIN bhld_nhanvien nv ON ct.manv = nv.manv
JOIN bhld_phongban pb ON nv.mapb = pb.mapb
LEFT JOIN bhld_dmvattu vt ON ctct.mavt = vt.mavt
WHERE ctct.sl = 0
  AND ctct.mavt IN (
      SELECT ctct2.mavt
      FROM bhld_ctctu ctct2
      JOIN bhld_ctu ct2 ON ctct2.mact = ct2.mact
      WHERE ctct2.sl = 0 AND ct2.manv = ct.manv
      GROUP BY ctct2.mavt
      HAVING COUNT(*) >= 2
  )
GROUP BY ct.manv, nv.tennhanvien, nv.mapb, pb.tenphong
ORDER BY nv.mapb, nv.tennhanvien
";

$result2 = mysqli_query($conn, $sql2);
$rows2 = [];
while ($r = mysqli_fetch_assoc($result2)) $rows2[] = $r;

echo json_encode([
    'query' => 'NV co cung 1 loai vat tu SL=0 xuat hien >= 2 lan',
    'total' => count($rows2),
    'data' => $rows2
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;


// NV có CT chưa nhận (sl=0) cho 1 loại BHLĐ nhưng đã nhận loại đó qua CT khác (sl=1)
$sql = "
SELECT DISTINCT
    ct0.manv,
    nv.tennhanvien,
    nv.mapb,
    pb.tenphong,
    ctct0.mavt,
    vt.tenvt,
    ct0.mact AS mact_chua_nhan,
    ct0.ngct  AS ngct_chua_nhan,
    ct1.mact  AS mact_da_nhan,
    ctct1.ngnhantt AS han_su_dung
FROM bhld_ctctu ctct0
JOIN bhld_ctu ct0   ON ctct0.mact = ct0.mact
JOIN bhld_nhanvien nv ON ct0.manv = nv.manv
JOIN bhld_phongban pb ON nv.mapb = pb.mapb
LEFT JOIN bhld_dmvattu vt ON ctct0.mavt = vt.mavt
JOIN bhld_ctctu ctct1 ON ctct1.mavt = ctct0.mavt AND ctct1.sl = 1
JOIN bhld_ctu ct1   ON ctct1.mact = ct1.mact AND ct1.manv = ct0.manv
WHERE ctct0.sl = 0
ORDER BY nv.mapb, ct0.manv, ctct0.mavt, ct0.ngct
";

$rows = [];
$r = mysqli_query($conn, $sql);
if ($r) while($row = mysqli_fetch_assoc($r)) $rows[] = $row;

// Đếm NV bị ảnh hưởng
$sqlCnt = "
SELECT COUNT(DISTINCT ct0.manv) as so_nv
FROM bhld_ctctu ctct0
JOIN bhld_ctu ct0 ON ctct0.mact = ct0.mact
JOIN bhld_ctctu ctct1 ON ctct1.mavt = ctct0.mavt AND ctct1.sl = 1
JOIN bhld_ctu ct1 ON ctct1.mact = ct1.mact AND ct1.manv = ct0.manv
WHERE ctct0.sl = 0
";
$cnt = 0;
$rc = mysqli_query($conn, $sqlCnt);
if ($rc) { $row = mysqli_fetch_assoc($rc); $cnt = $row['so_nv']; }

echo json_encode(['so_nv_bi_anh_huong' => $cnt, 'chi_tiet' => $rows], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

$endDate = isset($_GET['end']) ? mysqli_real_escape_string($conn, $_GET['end']) : '2026-06-30';

// Raw từ VIEW_1 (sl=0) cho NV này trước endDate
$rows1 = [];
$r1 = mysqli_query($conn, "SELECT * FROM bhld_view_chungtu_chuanhan_1 WHERE manv='$manv' AND ngct <= '$endDate' ORDER BY ngct");
if ($r1) while($row = mysqli_fetch_assoc($r1)) $rows1[] = $row;

// Sau khi GROUP BY mact trong VIEW_final
$rows2 = [];
$r2 = mysqli_query($conn, "SELECT * FROM bhld_view_chungtu_chuanhan_final WHERE manv='$manv' AND ngct <= '$endDate' ORDER BY ngct");
if ($r2) while($row = mysqli_fetch_assoc($r2)) $rows2[] = $row;

// SUM cuối cùng (như export_word.php)
$rows3 = [];
$r3 = mysqli_query($conn, "SELECT manv, tennhanvien, mact, ngct, SUM(GiayBH) as GiayBH, SUM(MuBH) as MuBH, SUM(QuanAo) as QuanAo, SUM(Kinh) as Kinh, SUM(AoMua) as AoMua, SUM(NutTai) as NutTai, SUM(PhinLoc) as PhinLoc FROM bhld_view_chungtu_chuanhan_final WHERE manv='$manv' AND ngct <= '$endDate' GROUP BY manv");
if ($r3) while($row = mysqli_fetch_assoc($r3)) $rows3[] = $row;

// Raw bhld_ctctu cho NV này (tất cả sl)
$rows4 = [];
$r4 = mysqli_query($conn, "SELECT ctct.mact, ctct.mavt, vt.tenvt, ctct.sl, ctct.ngnhan, ctct.ngnhantt, ct.ngct FROM bhld_ctctu ctct JOIN bhld_ctu ct ON ctct.mact=ct.mact LEFT JOIN bhld_dmvattu vt ON ctct.mavt=vt.mavt WHERE ct.manv='$manv' ORDER BY ct.ngct");
if ($r4) while($row = mysqli_fetch_assoc($r4)) $rows4[] = $row;

echo json_encode([
    'manv' => $manv,
    'endDate' => $endDate,
    'view1_sl0_rows' => $rows1,
    'view_final_rows' => $rows2,
    'final_grouped' => $rows3,
    'all_ctctu_raw' => $rows4,
], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

if ($r) { $row = mysqli_fetch_assoc($r); $result['view_definition'] = $row['Create View'] ?? ''; }

// Cấu trúc cột
$cols = [];
$rc = mysqli_query($conn, "DESCRIBE bhld_view_chungtu_chuanhan_1");
if ($rc) while($row = mysqli_fetch_assoc($rc)) $cols[] = $row;
$result['columns'] = $cols;

// Đếm rows
$rcnt = mysqli_query($conn, "SELECT COUNT(*) as c FROM bhld_view_chungtu_chuanhan_1");
if ($rcnt) { $row = mysqli_fetch_assoc($rcnt); $result['total_rows'] = $row['c']; }

// Sample
$sample = [];
$rs = mysqli_query($conn, "SELECT * FROM bhld_view_chungtu_chuanhan_1 ORDER BY ngct DESC LIMIT 5");
if ($rs) while($row = mysqli_fetch_assoc($rs)) $sample[] = $row;
$result['latest_sample'] = $sample;

echo json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);


// Lấy cấu trúc VIEW (các cột)
$cols = [];
$rc = mysqli_query($conn, "DESCRIBE bhld_view_chungtu_chuanhan_final");
if ($rc) while($row = mysqli_fetch_assoc($rc)) $cols[] = $row;

// Đếm tổng records
$cnt = 0;
$rcnt = mysqli_query($conn, "SELECT COUNT(*) as c FROM bhld_view_chungtu_chuanhan_final");
if ($rcnt) { $row = mysqli_fetch_assoc($rcnt); $cnt = $row['c']; }

// Sample mới nhất
$sample = [];
$rs = mysqli_query($conn, "SELECT * FROM bhld_view_chungtu_chuanhan_final ORDER BY ngct DESC LIMIT 5");
if ($rs) while($row = mysqli_fetch_assoc($rs)) $sample[] = $row;

echo json_encode(['view_definition'=>$viewDef, 'columns'=>$cols, 'total_rows'=>$cnt, 'latest_sample'=>$sample], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);


// Nếu có view, thử query thử
$viewSample = [];
if (in_array('bhld_view_chungtu_chuanhan_final', $views)) {
    $rs = mysqli_query($conn, "SELECT * FROM bhld_view_chungtu_chuanhan_final LIMIT 3");
    if ($rs) while($row = mysqli_fetch_assoc($rs)) $viewSample[] = $row;
}

// Lấy cấu trúc bảng bhld_ctctu để xem mavt
$cols = [];
$rc = mysqli_query($conn, "DESCRIBE bhld_ctctu");
if ($rc) while($row = mysqli_fetch_assoc($rc)) $cols[] = $row;

// Sample data bhld_ctctu
$sample = [];
$rs2 = mysqli_query($conn, "SELECT ctct.*, vt.tenvt FROM bhld_ctctu ctct LEFT JOIN bhld_dmvattu vt ON ctct.mavt=vt.mavt LIMIT 10");
if ($rs2) while($row = mysqli_fetch_assoc($rs2)) $sample[] = $row;

echo json_encode(['views'=>$views, 'view_sample'=>$viewSample, 'ctctu_cols'=>$cols, 'ctctu_sample'=>$sample], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
