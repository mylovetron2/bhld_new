<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/db_connection.php';
header('Content-Type: application/json; charset=utf-8');

$month_param = isset($_GET['month']) ? trim($_GET['month']) : date('m/Y');
$parts = preg_split('/[\/\-]/', $month_param);
if (strlen($parts[0]) == 4) {
    $year = $parts[0]; $monthNum = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
} else {
    $monthNum = str_pad($parts[0], 2, '0', STR_PAD_LEFT); $year = $parts[1];
}
$startDate = "$year-$monthNum-01";
$lastDay   = date('t', strtotime($startDate));
$endDate   = "$year-$monthNum-$lastDay";

// Query raw rows
$sql = "SELECT ct.mact, ct.ngct, nv.mapb, pb.tenphong, ct.manv, nv.tennhanvien, ctct.mavt, vt.tenvt, ctct.sl
        FROM bhld_ctctu ctct
        INNER JOIN bhld_ctu ct    ON ctct.mact = ct.mact
        INNER JOIN bhld_nhanvien nv ON ct.manv  = nv.manv
        INNER JOIN bhld_phongban pb ON nv.mapb   = pb.mapb
        LEFT  JOIN bhld_dmvattu  vt ON ctct.mavt = vt.mavt
        WHERE ct.ngct BETWEEN '$startDate' AND '$endDate'
          AND ctct.sl > 0
        ORDER BY nv.mapb, ct.manv
        LIMIT 50";

$res = mysqli_query($conn, $sql);
$rows = [];
while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;

// Also get distinct tenvt values
$sqlVt = "SELECT DISTINCT vt.mavt, vt.tenvt FROM bhld_dmvattu vt ORDER BY vt.mavt";
$resVt = mysqli_query($conn, $sqlVt);
$vtList = [];
while ($r = mysqli_fetch_assoc($resVt)) $vtList[] = $r;

echo json_encode(['month'=>"$monthNum/$year", 'startDate'=>$startDate, 'endDate'=>$endDate, 'row_count'=>count($rows), 'sample_rows'=>$rows, 'all_vattu'=>$vtList], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
