<?php
// Tắt warnings/deprecated để không làm hỏng binary output
error_reporting(0);
ini_set('display_errors', 0);

/**
 * Xuất chứng từ cấp phát BHLĐ ra file Word (.docx)
 * Dùng VIEW bhld_view_chungtu_chuanhan_final (giống in_chung_tu_theo_thang.php)
 * GET ?month=MM/YYYY  (ví dụ: 06/2026)
 */

require_once __DIR__ . '/db_connection.php';
include_once __DIR__ . '/tbs_class.php';
include_once __DIR__ . '/tbs_plugin_opentbs.php';

// ===== PARSE THAM SỐ THÁNG =====
$month_param = isset($_GET['month']) ? trim($_GET['month']) : date('m/Y');
$parts = preg_split('/[\/\-]/', $month_param);
if (count($parts) < 2) die('Tham số month không hợp lệ. Dùng dạng MM/YYYY');

if (strlen($parts[0]) == 4) {
    $year = $parts[0]; $monthNum = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
} else {
    $monthNum = str_pad($parts[0], 2, '0', STR_PAD_LEFT); $year = $parts[1];
}

$startDate = "$year-$monthNum-01";
$lastDay   = date('t', strtotime($startDate));
$endDate   = "$year-$monthNum-$lastDay";
$showngay   = "Tháng $monthNum-$year";
$showngayin = "$monthNum/$year";

// ===== KHỞI TẠO OPENTBS =====
$TBS = new \clsTinyButStrong();
$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
$templateFile = __DIR__ . '/chung_tu_chua_nhan_3.docx';
if (!file_exists($templateFile)) die('Không tìm thấy file template');
$TBS->LoadTemplate($templateFile, OPENTBS_ALREADY_UTF8);

// ===== BUILD DATA - GIỐNG in_chung_tu_theo_thang.php =====
$escEnd = mysqli_real_escape_string($conn, $endDate);
$data   = [];

$sqlPB = "SELECT * FROM bhld_phongban ORDER BY mapb ASC";
$resPB = mysqli_query($conn, $sqlPB);
if ($resPB) {
    while ($rowPB = mysqli_fetch_assoc($resPB)) {
        $pb    = mysqli_real_escape_string($conn, $rowPB['mapb']);
        $tenpb = $rowPB['tenphong'];

        $sql = "SELECT manv, tennhanvien, mact, ngct,
                       SUM(GiayBH) as GiayBH, SUM(MuBH) as MuBH,
                       SUM(QuanAo) as QuanAo, SUM(Kinh) as Kinh,
                       SUM(AoMua) as AoMua, SUM(NutTai) as NutTai,
                       SUM(PhinLoc) as PhinLoc
                FROM bhld_view_chungtu_chuanhan_final
                WHERE mapb='$pb' AND ngct <= '$escEnd'
                GROUP BY manv";

        $res = mysqli_query($conn, $sql);
        if ($res) {
            $rows = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = [
                    'tennhanvien' => $row['tennhanvien'],
                    'manv'        => $row['manv'],
                    'giaybh'      => $row['GiayBH'] ?: '',
                    'mubh'        => $row['MuBH']   ?: '',
                    'quanao'      => $row['QuanAo']  ?: '',
                    'kinh'        => $row['Kinh']    ?: '',
                    'aomua'       => $row['AoMua']   ?: '',
                    'nuttai'      => $row['NutTai']  ?: '',
                    'phinloc'     => $row['PhinLoc'] ?: '',
                ];
            }
            if (count($rows) > 0)
                $data[] = ['name' => $tenpb, 'spokenlg' => $rows];
        }
    }
}

// ===== DEBUG MODE =====
if (isset($_GET['debug'])) {
    header('Content-Type: application/json; charset=utf-8');
    mysqli_close($conn);
    echo json_encode(['month'=>"$monthNum/$year",'end'=>$endDate,'data'=>$data], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit();
}

mysqli_close($conn);

// ===== MERGE & DOWNLOAD =====
$TBS->MergeBlock('onshow', [['showngay' => $showngay, 'showngayin' => $showngayin]]);
$TBS->MergeBlock('main', $data);

$outputName = 'ChungTu_Cap_Phat_BHLD_' . $monthNum . '_' . $year . '.docx';
$TBS->Show(OPENTBS_DOWNLOAD, $outputName);
exit();


/**
 * Xuất chứng từ cấp phát BHLĐ ra file Word (.docx)
 * Sử dụng OpenTBS + template chung_tu_chua_nhan_3.docx
 * 
 * GET ?month=MM/YYYY  (ví dụ: 05/2025)
 */

// Không include config.php vì nó set Content-Type: application/json
require_once __DIR__ . '/db_connection.php';
include_once __DIR__ . '/tbs_class.php';
include_once __DIR__ . '/tbs_plugin_opentbs.php';

// ===== PARSE THAM SỐ THÁNG =====
$month_param = isset($_GET['month']) ? trim($_GET['month']) : date('m/Y');

$parts = preg_split('/[\/\-]/', $month_param);
if (count($parts) < 2) {
    die('Tham số month không hợp lệ. Dùng dạng MM/YYYY');
}

if (strlen($parts[0]) == 4) {
    // Dạng YYYY-MM
    $year     = $parts[0];
    $monthNum = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
} else {
    // Dạng MM/YYYY
    $monthNum = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
    $year     = $parts[1];
}

$startDate = "$year-$monthNum-01";
$lastDay   = date('t', strtotime($startDate));
$endDate   = "$year-$monthNum-$lastDay";
$showngay   = "Tháng $monthNum/$year";
$showngayin = date('d/m/Y'); // Ngày in

// ===== KHỞI TẠO OPENTBS =====
$TBS = new \clsTinyButStrong();
$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);

$templateFile = __DIR__ . '/chung_tu_chua_nhan_3.docx';
if (!file_exists($templateFile)) {
    die('Không tìm thấy file template: chung_tu_chua_nhan_3.docx');
}
$TBS->LoadTemplate($templateFile, OPENTBS_ALREADY_UTF8);

// Mapping mavt code → key cột (lấy từ bảng bhld_dmvattu thực tế)
$mavtKeyMap = [
    '500120' => 'giaybh',   // Giày bảo hộ
    '500500' => 'mubh',     // Mũ bảo hộ
    '500860' => 'quanao',   // Áo quần bảo hộ
    '501545' => 'kinh',     // Kính bảo hộ
    '501660' => 'aomua',    // Áo bạt đi mưa
    '10000'  => 'nuttai',   // Nút tai chống ồn
    '20000'  => 'phinloc',  // Phin lọc khí độc
];
// Fallback: tên từ khóa nếu mavt chưa có trong map
$tenvtKeyMap = [
    'giày'    => 'giaybh',
    'mũ'      => 'mubh',
    'áo quần' => 'quanao',
    'kính'    => 'kinh',
    'mưa'     => 'aomua',
    'nút tai' => 'nuttai',
    'phin'    => 'phinloc',
];

// ===== QUERY TẤT CẢ CẤP PHÁT (logic: ngct <= cuối tháng, GROUP BY manv - giống in_chung_tu_theo_thang.php) =====
$escEnd   = mysqli_real_escape_string($conn, $endDate);

$sqlAll = "SELECT nv.mapb, pb.tenphong, ct.manv, nv.tennhanvien, ctct.mavt, vt.tenvt, SUM(ctct.sl) as sl
           FROM bhld_ctctu ctct
           INNER JOIN bhld_ctu ct    ON ctct.mact = ct.mact
           INNER JOIN bhld_nhanvien nv ON ct.manv  = nv.manv
           INNER JOIN bhld_phongban pb ON nv.mapb   = pb.mapb
           LEFT  JOIN bhld_dmvattu  vt ON ctct.mavt = vt.mavt
           WHERE ct.ngct <= '$escEnd'
             AND ctct.sl > 0
           GROUP BY nv.mapb, pb.tenphong, ct.manv, nv.tennhanvien, ctct.mavt, vt.tenvt
           ORDER BY nv.mapb, ct.manv";

$resAll = mysqli_query($conn, $sqlAll);

// ===== BUILD DỮ LIỆU THEO CẤU TRÚC OPENTBS =====
// $deptMap[mapb] = ['name'=>..., 'empMap'[manv]=>['tennhanvien'=>..., giaybh=>..., ...]]
$deptMap = [];

if ($resAll) {
    while ($row = mysqli_fetch_assoc($resAll)) {
        $pb    = $row['mapb'];
        $tenpb = $row['tenphong'];
        $manv  = $row['manv'];
        $mavt  = $row['mavt'];
        $tenvt = $row['tenvt'];
        $sl    = (int)$row['sl'];

        if (!isset($deptMap[$pb])) {
            $deptMap[$pb] = ['name' => $tenpb, 'empMap' => []];
        }
        if (!isset($deptMap[$pb]['empMap'][$manv])) {
            $deptMap[$pb]['empMap'][$manv] = [
                'manv'        => $manv,
                'tennhanvien' => $row['tennhanvien'],
                'giaybh'      => '',
                'mubh'        => '',
                'quanao'      => '',
                'kinh'        => '',
                'aomua'       => '',
                'nuttai'      => '',
                'phinloc'     => '',
            ];
        }

        // Map thiết bị: ưu tiên mavt code, fallback theo tenvt từ khóa
        $key = null;
        if (isset($mavtKeyMap[$mavt])) {
            $key = $mavtKeyMap[$mavt];
        } elseif ($tenvt) {
            $tenvtLower = mb_strtolower($tenvt, 'UTF-8');
            foreach ($tenvtKeyMap as $keyword => $colKey) {
                if (mb_strpos($tenvtLower, $keyword) !== false) {
                    $key = $colKey;
                    break;
                }
            }
        }
        if ($key) {
            $current = $deptMap[$pb]['empMap'][$manv][$key];
            $deptMap[$pb]['empMap'][$manv][$key] = $current === '' ? $sl : ($current + $sl);
        }
    }
}

// ===== CHUYỂN THÀNH MẢNG CHO OPENTBS =====
$data = [];
foreach ($deptMap as $pb => $dept) {
    $employees = array_values($dept['empMap']);
    if (count($employees) > 0) {
        $data[] = [
            'name'     => $dept['name'],
            'spokenlg' => $employees,
        ];
    }
}

// ===== DEBUG MODE =====
if (isset($_GET['debug'])) {
    header('Content-Type: application/json; charset=utf-8');
    $vtRows = [];
    $resVt = mysqli_query($conn, "SELECT DISTINCT mavt, tenvt FROM bhld_dmvattu ORDER BY mavt");
    while ($r = mysqli_fetch_assoc($resVt)) $vtRows[] = $r;
    $rawRows = [];
    $resRaw = mysqli_query($conn, "SELECT ct.mact, ct.ngct, nv.mapb, pb.tenphong, ct.manv, nv.tennhanvien, ctct.mavt, vt.tenvt, ctct.sl FROM bhld_ctctu ctct INNER JOIN bhld_ctu ct ON ctct.mact=ct.mact INNER JOIN bhld_nhanvien nv ON ct.manv=nv.manv INNER JOIN bhld_phongban pb ON nv.mapb=pb.mapb LEFT JOIN bhld_dmvattu vt ON ctct.mavt=vt.mavt WHERE ct.ngct <= '$escEnd' AND ctct.sl>0 ORDER BY nv.mapb, ct.manv LIMIT 100");
    while ($r = mysqli_fetch_assoc($resRaw)) $rawRows[] = $r;
    mysqli_close($conn);
    echo json_encode(['month'=>"$monthNum/$year",'start'=>$startDate,'end'=>$endDate,'data'=>$data,'raw_sample'=>$rawRows,'vattu'=>$vtRows], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit();
}

mysqli_close($conn);
$TBS->MergeBlock('onshow', [['showngay' => $showngay, 'showngayin' => $showngayin]]);
$TBS->MergeBlock('main', $data);

$outputName = 'ChungTu_Cap_Phat_BHLD_' . $monthNum . '_' . $year . '.docx';
$TBS->Show(OPENTBS_DOWNLOAD, $outputName);
exit();
?>
