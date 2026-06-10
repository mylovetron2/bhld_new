<?php
// Tắt warnings/deprecated để không làm hỏng binary output
error_reporting(0);
ini_set('display_errors', 0);

/**
 * DEBUG - Xuất chứng từ cấp phát BHLĐ ra file Word (.docx)
 * Hiển thị tên phòng ban trước và sau khi gom
 */

require_once __DIR__ . '/db_connection.php';
include_once __DIR__ . '/tbs_class.php';
include_once __DIR__ . '/tbs_plugin_opentbs.php';

function view_column_exists($conn, $columnName) {
    $col = mysqli_real_escape_string($conn, $columnName);
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bhld_view_chungtu_chuanhan_final' AND column_name = '$col' LIMIT 1";
    $rs = mysqli_query($conn, $sql);
    return $rs && mysqli_num_rows($rs) > 0;
}

function pick_sum_expr($conn, $candidates, $alias) {
    foreach ($candidates as $c) {
        if (view_column_exists($conn, $c)) {
            return "SUM($c) as $alias";
        }
    }
    return "0 as $alias";
}

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

// ===== BUILD DATA =====
$escEnd = mysqli_real_escape_string($conn, $endDate);
$data   = [];

$sqlPB = "SELECT * FROM bhld_phongban ORDER BY mapb ASC";
$resPB = mysqli_query($conn, $sqlPB);
if ($resPB) {
    $exprGiay = pick_sum_expr($conn, ['GiayBH', 'Giay'], 'GiayBH');
    $exprMu = pick_sum_expr($conn, ['MuBH', 'Mu'], 'MuBH');
    $exprQuanAo = pick_sum_expr($conn, ['QuanAo', 'AoQuan'], 'QuanAo');
    $exprKinh = pick_sum_expr($conn, ['Kinh'], 'Kinh');
    $exprAoMua = pick_sum_expr($conn, ['AoMua'], 'AoMua');
    $exprNutTai = pick_sum_expr($conn, ['NutTai'], 'NutTai');
    $exprPhinLoc = pick_sum_expr($conn, ['PhinLoc'], 'PhinLoc');
    $exprGangTay = pick_sum_expr($conn, ['GangTay'], 'GangTay');
    $exprKhauTrang = pick_sum_expr($conn, ['KhauTrang'], 'KhauTrang');
    $exprAoPhao = pick_sum_expr($conn, ['AoPhao', 'AoPhaoCuuSinh'], 'AoPhao');
    $exprGangTayHan = pick_sum_expr($conn, ['GangTayHan', 'GangTayDaThoHan'], 'GangTayHan');

    while ($rowPB = mysqli_fetch_assoc($resPB)) {
        $pb    = mysqli_real_escape_string($conn, $rowPB['mapb']);
        $tenpb = $rowPB['tenphong'];

        $sql = "SELECT manv, tennhanvien, mact, ngct,
                       $exprGiay, $exprMu,
                       $exprQuanAo, $exprKinh,
                       $exprAoMua, $exprNutTai,
                       $exprPhinLoc, $exprGangTay,
                       $exprKhauTrang, $exprAoPhao,
                       $exprGangTayHan
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
                    'gangtay'     => $row['GangTay'] ?: '',
                    'khautrang'   => $row['KhauTrang'] ?: '',
                    'aophao'      => $row['AoPhao'] ?: '',
                    'gangtayhan'  => $row['GangTayHan'] ?: '',
                ];
            }
            if (count($rows) > 0)
                $data[] = ['name' => $tenpb, 'spokenlg' => $rows];
        }
    }
}

// DEBUG: Hiển thị danh sách TRƯỚC khi gom
header('Content-Type: application/json; charset=utf-8');
mysqli_close($conn);

$beforeMerge = array_map(function($d) {
    return [
        'name' => $d['name'],
        'employeeCount' => count($d['spokenlg'])
    ];
}, $data);

// ===== GOM CÁC PHÒNG BAN THEO YÊU CẦU =====
$deptGroupMap = [
    'Xưởng SC và CC ĐVL' => 'Xưởng sửa chữa thiết bị ĐVL',
    'Xưởng SC cơ khí chuyên dụng' => 'Xưởng sửa chữa thiết bị ĐVL',
    'Đội carota tổng hợp' => 'Đội Địa vật lý tổng hợp',
    'Đội công nghệ cao' => 'Đội Địa vật lý tổng hợp',
];

$mergedData = [];
$deptEmployees = [];
$mergeLog = [];

foreach ($data as $dept) {
    $deptName = trim($dept['name']);
    
    // Tìm target name
    $targetName = $deptName;
    foreach ($deptGroupMap as $sourceName => $mappedName) {
        if (trim($sourceName) === $deptName) {
            $targetName = $mappedName;
            $mergeLog[] = "✓ GOM: '$deptName' → '$targetName'";
            break;
        }
    }
    
    if ($targetName === $deptName) {
        $mergeLog[] = "- GIỮ NGUYÊN: '$deptName'";
    }
    
    if (!isset($deptEmployees[$targetName])) {
        $deptEmployees[$targetName] = [];
    }
    
    foreach ($dept['spokenlg'] as $emp) {
        $manv = $emp['manv'];
        
        $found = false;
        foreach ($deptEmployees[$targetName] as &$existingEmp) {
            if ($existingEmp['manv'] === $manv) {
                foreach (['giaybh', 'mubh', 'quanao', 'kinh', 'aomua', 'nuttai', 'phinloc', 'gangtay', 'khautrang', 'aophao', 'gangtayhan'] as $equipKey) {
                    if ($emp[$equipKey] !== '') {
                        $existingEmp[$equipKey] = ($existingEmp[$equipKey] === '') ? $emp[$equipKey] : ($existingEmp[$equipKey] + $emp[$equipKey]);
                    }
                }
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $deptEmployees[$targetName][] = $emp;
        }
    }
}

$data = [];
foreach ($deptEmployees as $targetName => $employees) {
    if (count($employees) > 0) {
        $data[] = ['name' => $targetName, 'spokenlg' => $employees];
    }
}

$afterMerge = array_map(function($d) {
    return [
        'name' => $d['name'],
        'employeeCount' => count($d['spokenlg'])
    ];
}, $data);

echo json_encode([
    'month' => "$monthNum/$year",
    'beforeMerge' => $beforeMerge,
    'mergeLog' => $mergeLog,
    'afterMerge' => $afterMerge,
    'totalBefore' => count($beforeMerge),
    'totalAfter' => count($afterMerge)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit();
?>
