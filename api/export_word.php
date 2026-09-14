<?php
// Generate Word dynamically (no template) for BHLD report.
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/db_connection.php';

$canUsePhpWord = false;
$autoload = __DIR__ . '/../vendor/autoload.php';
if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 80000 && file_exists($autoload)) {
    require_once $autoload;
    $canUsePhpWord = class_exists('PhpOffice\\PhpWord\\PhpWord');
}

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

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

function column_exists($conn, $tableName, $columnName) {
    $table = mysqli_real_escape_string($conn, $tableName);
    $column = mysqli_real_escape_string($conn, $columnName);
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name = '$column' LIMIT 1";
    $rs = mysqli_query($conn, $sql);
    return $rs && mysqli_num_rows($rs) > 0;
}

function extract_chuc_danh($ghiChu) {
    $s = trim((string)$ghiChu);
    if ($s === '') return '';
    if (function_exists('mb_stripos')) {
        if (mb_stripos($s, 'Chức danh:') === 0) {
            return function_exists('mb_substr')
                ? trim(mb_substr($s, 10, null, 'UTF-8'))
                : trim(substr($s, 10));
        }
    } else {
        if (stripos($s, 'Chức danh:') === 0) {
            return trim(substr($s, 10));
        }
    }
    return $s;
}

function normalize_search_text($s) {
    $s = trim((string)$s);
    if (function_exists('mb_strtolower')) {
        $s = mb_strtolower($s, 'UTF-8');
    } else {
        $s = strtolower($s);
    }
    $map = [
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a',
        'â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a',
        'ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e',
        'ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o',
        'ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o',
        'ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u',
        'ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',
        'đ'=>'d'
    ];
    $s = strtr($s, $map);
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

function esc_html($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$monthParam = isset($_GET['month']) ? trim((string)$_GET['month']) : date('m/Y');
$parts = preg_split('/[\/\-]/', $monthParam);
if (count($parts) < 2) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Tham số month không hợp lệ. Dùng dạng MM/YYYY';
    exit;
}

if (strlen($parts[0]) == 4) {
    $year = $parts[0];
    $monthNum = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
} else {
    $monthNum = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
    $year = $parts[1];
}

$startDate = "$year-$monthNum-01";
$lastDay = date('t', strtotime($startDate));
$endDate = "$year-$monthNum-$lastDay";

$exprGiay = pick_sum_expr($conn, ['GiayBH', 'Giay'], 'GiayBH');
$exprQuanAo = pick_sum_expr($conn, ['QuanAo', 'AoQuan'], 'QuanAo');
$exprMu = pick_sum_expr($conn, ['MuBH', 'Mu'], 'MuBH');
$exprKinh = pick_sum_expr($conn, ['Kinh'], 'Kinh');
$exprGangTay = pick_sum_expr($conn, ['GangTay'], 'GangTay');
$exprKhauTrang = pick_sum_expr($conn, ['KhauTrang'], 'KhauTrang');
$exprAoMua = pick_sum_expr($conn, ['AoMua'], 'AoMua');
$exprPhinLoc = pick_sum_expr($conn, ['PhinLoc'], 'PhinLoc');
$exprAoPhao = pick_sum_expr($conn, ['AoPhao', 'AoPhaoCuuSinh'], 'AoPhao');
$exprNutTai = pick_sum_expr($conn, ['NutTai'], 'NutTai');
$exprGangTayHan = pick_sum_expr($conn, ['GangTayHan', 'GangTayDaThoHan'], 'GangTayHan');

$escEnd = mysqli_real_escape_string($conn, $endDate);
$employeeStatusJoin = '';
$employeeStatusWhere = '';
if (column_exists($conn, 'bhld_nhanvien', 'trangthai')) {
    $employeeStatusJoin = "LEFT JOIN bhld_nhanvien nv ON nv.manv = v.manv";
    $employeeStatusWhere = "AND COALESCE(nv.trangthai, 1) = 1";
}

$sql = "
    SELECT 
        v.mapb,
        COALESCE(pb.tenphong, v.mapb) AS tenphong,
        v.manv,
        v.tennhanvien,
        hs.giay_size,
        hs.giay_loai,
        hs.quanao_size,
        hs.mu_mau,
        hs.ghi_chu as hoso_ghichu,
        $exprGiay,
        $exprQuanAo,
        $exprMu,
        $exprKinh,
        $exprGangTay,
        $exprKhauTrang,
        $exprAoMua,
        $exprPhinLoc,
        $exprAoPhao,
        $exprNutTai,
        $exprGangTayHan
    FROM bhld_view_chungtu_chuanhan_final v
    LEFT JOIN bhld_phongban pb ON pb.mapb = v.mapb
    LEFT JOIN bhld_nhanvien_hoso hs ON hs.manv = v.manv
    $employeeStatusJoin
    WHERE v.ngct <= '$escEnd'
    $employeeStatusWhere
    GROUP BY v.mapb, v.manv
    ORDER BY v.mapb, v.tennhanvien, v.manv
";

$rs = mysqli_query($conn, $sql);
if (!$rs) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Lỗi truy vấn dữ liệu: ' . mysqli_error($conn);
    exit;
}

$departments = [];
while ($row = mysqli_fetch_assoc($rs)) {
    $mapb = (string)$row['mapb'];
    if (!isset($departments[$mapb])) {
        $departments[$mapb] = [
            'tenphong' => (string)$row['tenphong'],
            'employees' => [],
        ];
    }

    $departments[$mapb]['employees'][] = [
        'manv' => (string)$row['manv'],
        'tennhanvien' => (string)$row['tennhanvien'],
        'giay_size' => (string)(isset($row['giay_size']) ? $row['giay_size'] : ''),
        'giay_loai' => (string)(isset($row['giay_loai']) ? $row['giay_loai'] : ''),
        'quanao_size' => (string)(isset($row['quanao_size']) ? $row['quanao_size'] : ''),
        'mu_mau' => (string)(isset($row['mu_mau']) ? $row['mu_mau'] : ''),
        'chucdanh' => extract_chuc_danh(isset($row['hoso_ghichu']) ? $row['hoso_ghichu'] : ''),
        'giaybh' => (int)$row['GiayBH'],
        'quanao' => (int)$row['QuanAo'],
        'mubh' => (int)$row['MuBH'],
        'kinh' => (int)$row['Kinh'],
        'gangtay' => (int)$row['GangTay'],
        'khautrang' => (int)$row['KhauTrang'],
        'aomua' => (int)$row['AoMua'],
        'phinloc' => (int)$row['PhinLoc'],
        'aophao' => (int)$row['AoPhao'],
        'nuttai' => (int)$row['NutTai'],
        'gangtayhan' => (int)$row['GangTayHan'],
    ];
}

$deptGroupRules = [
    'Xưởng SC và CC máy ĐVL' => 'Xưởng SCTBĐVL',
    'Xưởng SC cơ khí chuyên dụng' => 'Xưởng SCTBĐVL',
    'Đội Công nghệ cao' => 'Đội Địa vật lý Tổng hợp',
    'Đội Carota tổng hợp' => 'Đội Địa vật lý Tổng hợp',
];

$groupedDepartments = [];
foreach ($departments as $mapb => $dept) {
    $deptName = trim((string)$dept['tenphong']);
    $normalizedDeptName = normalize_search_text($deptName);
    $targetName = $deptName;

    foreach ($deptGroupRules as $sourceName => $mappedName) {
        $normalizedSource = normalize_search_text($sourceName);
        if ($normalizedSource !== '' && strpos($normalizedDeptName, $normalizedSource) !== false) {
            $targetName = $mappedName;
            break;
        }
    }

    if (!isset($groupedDepartments[$targetName])) {
        $groupedDepartments[$targetName] = [
            'tenphong' => $targetName,
            'employees' => [],
        ];
    }

    foreach ($dept['employees'] as $emp) {
        $manv = $emp['manv'];
        $found = false;
        foreach ($groupedDepartments[$targetName]['employees'] as &$existingEmp) {
            if ($existingEmp['manv'] === $manv) {
                foreach (['giaybh', 'quanao', 'mubh', 'kinh', 'gangtay', 'khautrang', 'aomua', 'phinloc', 'aophao', 'nuttai', 'gangtayhan'] as $equipKey) {
                    $existingEmp[$equipKey] = (int)$existingEmp[$equipKey] + (int)$emp[$equipKey];
                }
                $found = true;
                break;
            }
        }
        unset($existingEmp);

        if (!$found) {
            $groupedDepartments[$targetName]['employees'][] = $emp;
        }
    }
}

$departments = $groupedDepartments;

mysqli_close($conn);

if (!$canUsePhpWord) {
    $outputName = 'ChungTu_Cap_Phat_BHLD_' . $monthNum . '_' . $year . '.doc';
    $html = '<html><head><meta charset="UTF-8"><style>'
        . 'body{font-family:"Times New Roman",serif;font-size:12pt;color:#000}'
        . 'h1,h2,h3,p{margin:0}'
        . '.header{text-align:center;margin-bottom:10px}'
        . '.subtitle{margin-top:2px}'
        . '.dept{margin-top:14px;font-weight:bold;font-size:13pt}'
        . '.note{font-style:italic;margin-top:3px;font-size:11pt}'
        . '.stats{width:100%;border-collapse:collapse;margin-top:6px}'
        . '.stats th,.stats td{border:1px solid #333;padding:4px 6px;text-align:left;font-size:11pt}'
        . '.stats th{background:#f2f2f2;text-align:center}'
        . '.detail{width:100%;border-collapse:collapse;margin-top:8px}'
        . '.detail th,.detail td{border:1px solid #333;padding:4px 5px;text-align:center;font-size:10.5pt}'
        . '.detail th{background:#f2f2f2}'
        . '.left{text-align:left}'
        . '.sign{margin-top:20px;width:100%;border-collapse:collapse}'
        . '.sign td{text-align:center;padding-top:8px;font-size:11pt}'
        . '.sign .gap{height:70px}'
        . '</style></head><body>';

    $html .= '<div class="header">';
    $html .= '<h2>CẤP PHÁT BẢO HỘ LAO ĐỘNG</h2>';
    $html .= '<p class="subtitle">Tháng ' . esc_html($monthNum . '-' . $year) . '</p>';
    $html .= '</div>';

    if (empty($departments)) {
        $html .= '<p>Không có dữ liệu để xuất trong kỳ này.</p>';
    } else {
        $equipmentStatLabels = [
            'giaybh' => 'Giày',
            'quanao' => 'Quần áo',
            'mubh' => 'Mũ',
            'kinh' => 'Kính',
            'gangtay' => 'Găng tay',
            'khautrang' => 'Khẩu trang',
            'aomua' => 'Áo mưa',
            'phinloc' => 'Phin lọc',
            'aophao' => 'Áo phao',
            'nuttai' => 'Nút tai',
            'gangtayhan' => 'GT da hàn',
        ];

        // Tổng hợp chi tiết theo từng loại của tất cả các đội.
        $allEmployees = [];
        $allTotals = array_fill_keys(array_keys($equipmentStatLabels), 0);
        foreach ($departments as $dept) {
            foreach ($dept['employees'] as $emp) {
                $allEmployees[] = $emp;
                foreach ($allTotals as $key => $_) {
                    $allTotals[$key] += (int)(isset($emp[$key]) ? $emp[$key] : 0);
                }
            }
        }

        $allStats = [];
        foreach ($equipmentStatLabels as $key => $label) {
            $qty = (int)(isset($allTotals[$key]) ? $allTotals[$key] : 0);
            if ($qty > 0) {
                $detail = build_stat_detail_text($allEmployees, $key);
                $allStats[] = $label . ': ' . $qty . $detail;
            }
        }

        if (!empty($allStats)) {
            $html .= '<div class="dept">Tổng hợp chi tiết theo từng loại của tất cả các đội</div>';
            $html .= '<table class="stats"><thead><tr><th style="width:50%">Thống kê toàn đơn vị</th><th style="width:50%">Thống kê toàn đơn vị</th></tr></thead><tbody>';
            for ($i = 0; $i < count($allStats); $i += 2) {
                $left = $allStats[$i];
                $right = isset($allStats[$i + 1]) ? $allStats[$i + 1] : '';
                $html .= '<tr><td>' . esc_html($left) . '</td><td>' . esc_html($right) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        foreach ($departments as $mapb => $dept) {
            $deptTitle = build_department_title($mapb, isset($dept['tenphong']) ? $dept['tenphong'] : '');
            $html .= '<div class="dept">' . esc_html($deptTitle) . '</div>';
            $html .= '<div class="note">Số nhân viên: ' . count($dept['employees']) . '</div>';

            $deptTotals = array_fill_keys(array_keys($equipmentStatLabels), 0);
            foreach ($dept['employees'] as $emp) {
                foreach ($deptTotals as $key => $_) {
                    $deptTotals[$key] += (int)(isset($emp[$key]) ? $emp[$key] : 0);
                }
            }

            $stats = [];
            foreach ($equipmentStatLabels as $key => $label) {
                $qty = (int)(isset($deptTotals[$key]) ? $deptTotals[$key] : 0);
                if ($qty > 0) {
                    $detail = build_stat_detail_text($dept['employees'], $key);
                    $stats[] = $label . ': ' . $qty . $detail;
                }
            }

            if (!empty($stats)) {
                $html .= '<table class="stats"><thead><tr><th>Thống kê vật tư nhận theo từng loại</th></tr></thead><tbody>';
                foreach ($stats as $item) {
                    $html .= '<tr><td>' . esc_html($item) . '</td></tr>';
                }
                $html .= '</tbody></table>';
            }

            $html .= '<table class="detail"><thead><tr>'
                . '<th style="width:58px">Mã NV</th>'
                . '<th class="left" style="width:170px">Tên nhân viên</th>'
                . '<th style="width:46px">Giày SL</th>'
                . '<th style="width:58px">Giày size</th>'
                . '<th style="width:38px">Cao cổ</th>'
                . '<th style="width:38px">Thấp cổ</th>'
                . '<th style="width:34px">Ủng</th>'
                . '<th style="width:62px">Mũ BH</th>'
                . '<th style="width:44px">QA SL</th>'
                . '<th style="width:52px">QA size</th>'
                . '<th style="width:36px">Kính</th>'
                . '<th style="width:44px">Áo mưa</th>'
                . '<th style="width:44px">Nút tai</th>'
                . '<th style="width:46px">Phin lọc</th>'
                . '<th style="width:56px">Ký nhận</th>'
                . '</tr></thead><tbody>';

            foreach ($dept['employees'] as $emp) {
                $giayLoai = trim((string)(isset($emp['giay_loai']) ? $emp['giay_loai'] : ''));
                $giayQty = (int)(isset($emp['giaybh']) ? $emp['giaybh'] : 0);
                $quanaoQty = (int)(isset($emp['quanao']) ? $emp['quanao'] : 0);
                $giaySize = $giayQty > 0 ? trim((string)(isset($emp['giay_size']) ? $emp['giay_size'] : '')) : '';
                $quanaoSize = $quanaoQty > 0 ? trim((string)(isset($emp['quanao_size']) ? $emp['quanao_size'] : '')) : '';
                $muText = build_helmet_text(isset($emp['mubh']) ? $emp['mubh'] : 0, (string)(isset($emp['mu_mau']) ? $emp['mu_mau'] : ''));

                $html .= '<tr>'
                    . '<td>' . esc_html(isset($emp['manv']) ? $emp['manv'] : '') . '</td>'
                    . '<td class="left">' . esc_html(isset($emp['tennhanvien']) ? $emp['tennhanvien'] : '') . '</td>'
                    . '<td>' . esc_html(qty_to_text($giayQty)) . '</td>'
                    . '<td>' . esc_html($giaySize) . '</td>'
                    . '<td>' . (($giayQty > 0 && match_shoe_type($giayLoai, 'cao co')) ? 'x' : '') . '</td>'
                    . '<td>' . (($giayQty > 0 && match_shoe_type($giayLoai, 'thap co')) ? 'x' : '') . '</td>'
                    . '<td>' . (($giayQty > 0 && match_shoe_type($giayLoai, 'ung')) ? 'x' : '') . '</td>'
                    . '<td>' . esc_html($muText) . '</td>'
                    . '<td>' . esc_html(qty_to_text($quanaoQty)) . '</td>'
                    . '<td>' . esc_html($quanaoSize) . '</td>'
                    . '<td>' . esc_html(qty_to_text(isset($emp['kinh']) ? $emp['kinh'] : 0)) . '</td>'
                    . '<td>' . esc_html(qty_to_text(isset($emp['aomua']) ? $emp['aomua'] : 0)) . '</td>'
                    . '<td>' . esc_html(qty_to_text(isset($emp['nuttai']) ? $emp['nuttai'] : 0)) . '</td>'
                    . '<td>' . esc_html(qty_to_text(isset($emp['phinloc']) ? $emp['phinloc'] : 0)) . '</td>'
                    . '<td></td>'
                    . '</tr>';
            }

            $html .= '</tbody></table>';
        }
    }

    $html .= '<div style="margin-top:14px;font-style:italic">Ngày in: ' . esc_html($monthNum . '/' . $year) . '</div>';
    $html .= '<table class="sign"><tr>'
        . '<td>NGƯỜI GIAO</td><td>NGƯỜI LẬP PHIẾU</td><td>TRƯỞNG PHÒNG</td><td>CHÁNH KẾ TOÁN</td><td>THỦ TRƯỞNG</td>'
        . '</tr><tr class="gap"><td></td><td></td><td></td><td></td><td></td></tr></table>';
    $html .= '</body></html>';

    header('Content-Type: application/msword; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $outputName . '"');
    echo $html;
    exit;
}

$phpWord = new PhpWord();
$phpWord->setDefaultFontName('Times New Roman');
$phpWord->setDefaultFontSize(12);

$section = $phpWord->addSection([
    'orientation' => 'portrait',
    'marginTop' => 1440,
    'marginRight' => 760,
    'marginBottom' => 1440,
    'marginLeft' => 1440,
    'headerHeight' => 400,
]);

// Header
$header = $section->addHeader();
$header->addText(
    'XN Địa vật lý GK',
    ['bold' => true, 'size' => 13],
    ['alignment' => Jc::LEFT]
);

// Tiêu đề chính
$section->addText(
    'CẤP PHÁT BẢO HỘ LAO ĐỘNG',
    ['size' => 14, 'bold' => true],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
);

// Dòng tháng
$section->addText(
    'Tháng ' . $monthNum . '-' . $year,
    ['size' => 12],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
);

$tableStyle = [
    'borderSize' => 6,
    'borderColor' => '666666',
    'cellMargin' => 40,
    'alignment' => JcTable::CENTER,
    'width' => 10205,
    'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
];
$statsTableStyle = [
    'borderSize' => 4,
    'borderColor' => '999999',
    'cellMargin' => 40,
    'alignment' => JcTable::CENTER,
    'width' => 10205,
    'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
];
$headerCellStyle = ['bgColor' => 'EDEDED'];
$headerFont = ['bold' => true, 'size' => 11];
$cellFont = ['size' => 11];

$reportColumns = [
    'manv' => 760,
    'tennhanvien' => 1550,
    'giay_sl' => 520,
    'giay_size' => 520,
    'giay_cao_co' => 520,
    'giay_thap_co' => 520,
    'giay_ung' => 520,
    'mubh' => 520,
    'quanao_sl' => 520,
    'quanao_size' => 520,
    'quanao_mau' => 520,
    'kinh' => 520,
    'aomua' => 520,
    'nuttai' => 520,
    'phinloc' => 520,
    'kynhan' => 620,
];

$equipmentStatLabels = [
    'giaybh' => 'Giày',
    'quanao' => 'Quần áo',
    'mubh' => 'Mũ',
    'kinh' => 'Kính',
    'gangtay' => 'Găng tay',
    'khautrang' => 'Khẩu trang',
    'aomua' => 'Áo mưa',
    'phinloc' => 'Phin lọc',
    'aophao' => 'Áo phao',
    'nuttai' => 'Nút tai',
    'gangtayhan' => 'GT da hàn',
];

function build_stat_detail_text(array $employees, $equipKey) {
    $buckets = [];
    foreach ($employees as $emp) {
        $qty = (int)(isset($emp[$equipKey]) ? $emp[$equipKey] : 0);
        if ($qty <= 0) {
            continue;
        }

        $label = '';
        if ($equipKey === 'giaybh') {
            $parts = [];
            $size = trim((string)(isset($emp['giay_size']) ? $emp['giay_size'] : ''));
            $type = trim((string)(isset($emp['giay_loai']) ? $emp['giay_loai'] : ''));
            if ($size !== '') {
                $parts[] = 'size ' . $size;
            }
            if ($type !== '') {
                $parts[] = 'loại ' . $type;
            }
            $label = $parts ? implode(', ', $parts) : 'Không rõ size/loại';
        } elseif ($equipKey === 'quanao') {
            $size = trim((string)(isset($emp['quanao_size']) ? $emp['quanao_size'] : ''));
            $label = $size !== '' ? 'size ' . $size : 'Không rõ size';
        } elseif ($equipKey === 'mubh') {
            $color = trim((string)(isset($emp['mu_mau']) ? $emp['mu_mau'] : ''));
            $label = $color !== '' ? 'màu ' . $color : 'Không rõ màu';
        } else {
            return '';
        }

        if (!isset($buckets[$label])) {
            $buckets[$label] = 0;
        }
        $buckets[$label] += $qty;
    }

    if (empty($buckets)) {
        return '';
    }

    $parts = [];
    foreach ($buckets as $label => $count) {
        $parts[] = $label . ': ' . $count;
    }

    return ' (' . implode('; ', $parts) . ')';
}

function qty_to_text($value) {
    $qty = (int)$value;
    return $qty > 0 ? (string)$qty : '';
}

function match_shoe_type($type, $needle) {
    return strpos(normalize_search_text($type), normalize_search_text($needle)) !== false;
}

function build_helmet_text($qtyValue, $color) {
    $qty = (int)$qtyValue;
    if ($qty <= 0) {
        return '';
    }

    $color = trim($color);
    if ($color === '') {
        return (string)$qty;
    }

    return $qty . ' ' . $color;
}

function build_department_title($mapb, $tenphong) {
    $mapb = trim($mapb);
    $tenphong = trim($tenphong);

    if ($tenphong === '') {
        return $mapb;
    }

    if ($mapb === '') {
        return $tenphong;
    }

    if ($mapb === $tenphong) {
        return $tenphong;
    }

    if (strpos($tenphong, $mapb . ' - ') === 0) {
        return trim(substr($tenphong, strlen($mapb) + 3));
    }

    return $tenphong;
}

// Tổng hợp toàn bộ đội theo loại + chi tiết size/màu/loại.
$allTotals = array_fill_keys(array_keys($equipmentStatLabels), 0);
$detailBuckets = [
    'giaybh' => [],
    'quanao' => [],
    'mubh' => [],
];

foreach ($departments as $dept) {
    foreach ($dept['employees'] as $emp) {
        foreach ($allTotals as $key => $_) {
            $allTotals[$key] += (int)(isset($emp[$key]) ? $emp[$key] : 0);
        }

        $giayQty = (int)(isset($emp['giaybh']) ? $emp['giaybh'] : 0);
        if ($giayQty > 0) {
            $giaySize = trim((string)(isset($emp['giay_size']) ? $emp['giay_size'] : ''));
            $giayLoai = trim((string)(isset($emp['giay_loai']) ? $emp['giay_loai'] : ''));
            $giayKey = 'size ' . ($giaySize !== '' ? $giaySize : 'không rõ') . ' | ' . ($giayLoai !== '' ? $giayLoai : 'không rõ');
            if (!isset($detailBuckets['giaybh'][$giayKey])) {
                $detailBuckets['giaybh'][$giayKey] = 0;
            }
            $detailBuckets['giaybh'][$giayKey] += $giayQty;
        }

        $quanaoQty = (int)(isset($emp['quanao']) ? $emp['quanao'] : 0);
        if ($quanaoQty > 0) {
            $quanaoSize = trim((string)(isset($emp['quanao_size']) ? $emp['quanao_size'] : ''));
            $quanaoKey = 'size ' . ($quanaoSize !== '' ? $quanaoSize : 'không rõ');
            if (!isset($detailBuckets['quanao'][$quanaoKey])) {
                $detailBuckets['quanao'][$quanaoKey] = 0;
            }
            $detailBuckets['quanao'][$quanaoKey] += $quanaoQty;
        }

        $mubhQty = (int)(isset($emp['mubh']) ? $emp['mubh'] : 0);
        if ($mubhQty > 0) {
            $muMau = trim((string)(isset($emp['mu_mau']) ? $emp['mu_mau'] : ''));
            $muKey = 'màu ' . ($muMau !== '' ? $muMau : 'không rõ');
            if (!isset($detailBuckets['mubh'][$muKey])) {
                $detailBuckets['mubh'][$muKey] = 0;
            }
            $detailBuckets['mubh'][$muKey] += $mubhQty;
        }
    }
}

$summaryRows = [];
foreach ($equipmentStatLabels as $key => $label) {
    $qty = (int)(isset($allTotals[$key]) ? $allTotals[$key] : 0);
    if ($qty <= 0) {
        continue;
    }

    $detailText = '';
    if ($key === 'giaybh' && !empty($detailBuckets['giaybh'])) {
        $parts = [];
        foreach ($detailBuckets['giaybh'] as $detailLabel => $count) {
            $parts[] = $detailLabel . ': ' . $count;
        }
        $detailText = ' (' . implode('; ', $parts) . ')';
    } elseif ($key === 'quanao' && !empty($detailBuckets['quanao'])) {
        $parts = [];
        foreach ($detailBuckets['quanao'] as $detailLabel => $count) {
            $parts[] = $detailLabel . ': ' . $count;
        }
        $detailText = ' (' . implode('; ', $parts) . ')';
    } elseif ($key === 'mubh' && !empty($detailBuckets['mubh'])) {
        $parts = [];
        foreach ($detailBuckets['mubh'] as $detailLabel => $count) {
            $parts[] = $detailLabel . ': ' . $count;
        }
        $detailText = ' (' . implode('; ', $parts) . ')';
    }

    $summaryRows[] = [
        'label' => $label,
        'qty' => $qty,
        'detail' => $detailText,
    ];
}

if (!empty($summaryRows)) {
    $section->addText(
        'Tổng hợp chi tiết theo từng loại của tất cả các đội',
        ['bold' => true, 'size' => 12],
        ['spaceAfter' => 30]
    );

    $summaryTable = $section->addTable([
        'borderSize' => 6,
        'borderColor' => '666666',
        'cellMargin' => 40,
        'alignment' => JcTable::CENTER,
        'width' => 10205,
        'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
    ]);

    $summaryTable->addRow();
    $summaryTable->addCell(7600, ['bgColor' => 'EDEDED'])->addText('Loại vật tư', $headerFont, ['alignment' => Jc::CENTER]);
    $summaryTable->addCell(2605, ['bgColor' => 'EDEDED'])->addText('Số lượng', $headerFont, ['alignment' => Jc::CENTER]);

    foreach ($summaryRows as $row) {
        $summaryTable->addRow();
        $summaryTable->addCell(7600)->addText($row['label'] . $row['detail'], $cellFont, ['alignment' => Jc::LEFT]);
        $summaryTable->addCell(2605)->addText((string)$row['qty'], $cellFont, ['alignment' => Jc::CENTER]);
    }

    $section->addText('', ['size' => 1], ['spaceAfter' => 40]);
}

if (empty($departments)) {
    $section->addText('Không có dữ liệu để xuất trong kỳ này.', ['italic' => true, 'size' => 12]);
} else {
    foreach ($departments as $mapb => $dept) {
        $deptTotals = array_fill_keys(array_keys($equipmentStatLabels), 0);
        foreach ($dept['employees'] as $emp) {
            foreach ($deptTotals as $key => $_) {
                $deptTotals[$key] += (int)(isset($emp[$key]) ? $emp[$key] : 0);
            }
        }

        $employeeCount = count($dept['employees']);

        $deptTitle = build_department_title($mapb, (string)$dept['tenphong']);
        $section->addText(
            $deptTitle,
            ['bold' => true, 'size' => 12],
            ['spaceBefore' => 120, 'spaceAfter' => 80]
        );

        $section->addText(
            'Số nhân viên: ' . $employeeCount,
            ['size' => 11, 'italic' => true],
            ['spaceAfter' => 20]
        );

        $section->addText(
            'Thống kê vật tư nhận theo từng loại:',
            ['size' => 11, 'italic' => true],
            ['spaceAfter' => 40]
        );

        $nonZeroStats = [];
        foreach ($equipmentStatLabels as $key => $label) {
            $qty = (int)(isset($deptTotals[$key]) ? $deptTotals[$key] : 0);
            if ($qty > 0) {
                $nonZeroStats[] = [
                    'label' => $label,
                    'qty' => $qty,
                ];
            }
        }

        if (!empty($nonZeroStats)) {
            $statsTable = $section->addTable($statsTableStyle);
            $statsTable->addRow();
            $statsTable->addCell(1000, $headerCellStyle)->addText('Loại vật tư', $headerFont, ['alignment' => Jc::CENTER]);
            $statsTable->addCell(3205, $headerCellStyle)->addText('Số lượng', $headerFont, ['alignment' => Jc::LEFT]);
            $statsTable->addCell(1000, $headerCellStyle)->addText('Loại vật tư', $headerFont, ['alignment' => Jc::CENTER]);
            $statsTable->addCell(3205, $headerCellStyle)->addText('Số lượng', $headerFont, ['alignment' => Jc::LEFT]);

            for ($idx = 0; $idx < count($nonZeroStats); $idx += 2) {
                $left = $nonZeroStats[$idx];
                $right = isset($nonZeroStats[$idx + 1]) ? $nonZeroStats[$idx + 1] : null;

                $statsTable->addRow();
                $statsTable->addCell(1000)->addText($left['label'], $cellFont, ['alignment' => Jc::CENTER]);
                $leftDetail = build_stat_detail_text($dept['employees'], array_search($left['label'], $equipmentStatLabels, true) ?: '');
                $statsTable->addCell(3205)->addText((string)$left['qty'] . $leftDetail, $cellFont, ['alignment' => Jc::LEFT]);

                if ($right !== null) {
                    $statsTable->addCell(1000)->addText($right['label'], $cellFont, ['alignment' => Jc::CENTER]);
                    $rightDetail = build_stat_detail_text($dept['employees'], array_search($right['label'], $equipmentStatLabels, true) ?: '');
                    $statsTable->addCell(3205)->addText((string)$right['qty'] . $rightDetail, $cellFont, ['alignment' => Jc::LEFT]);
                } else {
                    $statsTable->addCell(1000)->addText('', $cellFont, ['alignment' => Jc::CENTER]);
                    $statsTable->addCell(3205)->addText('', $cellFont, ['alignment' => Jc::LEFT]);
                }
            }

            $section->addText('', ['size' => 1], ['spaceAfter' => 40]);
        }

        $table = $section->addTable($tableStyle);
        $mergeHeaderCellStyle = $headerCellStyle + ['vMerge' => 'restart', 'valign' => 'center'];
        $continueHeaderCellStyle = $headerCellStyle + ['vMerge' => 'continue'];

        $table->addRow();
        $table->addCell($reportColumns['manv'], $mergeHeaderCellStyle)->addText('Danh Số', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['tennhanvien'], $mergeHeaderCellStyle)->addText('Tên', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell(
            $reportColumns['giay_sl'] + $reportColumns['giay_size'] + $reportColumns['giay_cao_co'] + $reportColumns['giay_thap_co'] + $reportColumns['giay_ung'],
            $headerCellStyle + ['gridSpan' => 5]
        )->addText('Giày', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['mubh'], $mergeHeaderCellStyle)->addText('Mũ', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell(
            $reportColumns['quanao_sl'] + $reportColumns['quanao_size'] + $reportColumns['quanao_mau'],
            $headerCellStyle + ['gridSpan' => 3]
        )->addText('Quần áo', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['kinh'], $mergeHeaderCellStyle)->addText('Kính', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['aomua'], $mergeHeaderCellStyle)->addText('Áo mưa', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['nuttai'], $mergeHeaderCellStyle)->addText('Nút tai', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['phinloc'], $mergeHeaderCellStyle)->addText('Phin Lọc', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['kynhan'], $mergeHeaderCellStyle)->addText('Ký nhận', $headerFont, ['alignment' => Jc::CENTER]);

        $table->addRow();
        $table->addCell($reportColumns['manv'], $continueHeaderCellStyle);
        $table->addCell($reportColumns['tennhanvien'], $continueHeaderCellStyle);
        $table->addCell($reportColumns['giay_sl'], $headerCellStyle)->addText('số lượng', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['giay_size'], $headerCellStyle)->addText('size', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['giay_cao_co'], $headerCellStyle)->addText('Cao cổ', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['giay_thap_co'], $headerCellStyle)->addText('thấp cổ', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['giay_ung'], $headerCellStyle)->addText('Ủng', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['mubh'], $continueHeaderCellStyle);
        $table->addCell($reportColumns['quanao_sl'], $headerCellStyle)->addText('số lượng', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['quanao_size'], $headerCellStyle)->addText('Size', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['quanao_mau'], $headerCellStyle)->addText('màu', $headerFont, ['alignment' => Jc::CENTER]);
        $table->addCell($reportColumns['kinh'], $continueHeaderCellStyle);
        $table->addCell($reportColumns['aomua'], $continueHeaderCellStyle);
        $table->addCell($reportColumns['nuttai'], $continueHeaderCellStyle);
        $table->addCell($reportColumns['phinloc'], $continueHeaderCellStyle);
        $table->addCell($reportColumns['kynhan'], $continueHeaderCellStyle);

        foreach ($dept['employees'] as $emp) {
            $table->addRow();

            $giayLoai = trim((string)(isset($emp['giay_loai']) ? $emp['giay_loai'] : ''));
            $giayQty = (int)(isset($emp['giaybh']) ? $emp['giaybh'] : 0);
            $quanaoQty = (int)(isset($emp['quanao']) ? $emp['quanao'] : 0);
            $giaySize = $giayQty > 0 ? trim((string)(isset($emp['giay_size']) ? $emp['giay_size'] : '')) : '';
            $quanaoSize = $quanaoQty > 0 ? trim((string)(isset($emp['quanao_size']) ? $emp['quanao_size'] : '')) : '';
            $muText = build_helmet_text(isset($emp['mubh']) ? $emp['mubh'] : 0, (string)(isset($emp['mu_mau']) ? $emp['mu_mau'] : ''));

            $table->addCell($reportColumns['manv'])->addText(trim((string)(isset($emp['manv']) ? $emp['manv'] : '')), $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['tennhanvien'])->addText(trim((string)(isset($emp['tennhanvien']) ? $emp['tennhanvien'] : '')), $cellFont, ['alignment' => Jc::LEFT]);
            $table->addCell($reportColumns['giay_sl'])->addText(qty_to_text($giayQty), $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['giay_size'])->addText($giaySize, $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['giay_cao_co'])->addText($giayQty > 0 && match_shoe_type($giayLoai, 'cao co') ? 'x' : '', $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['giay_thap_co'])->addText($giayQty > 0 && match_shoe_type($giayLoai, 'thap co') ? 'x' : '', $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['giay_ung'])->addText($giayQty > 0 && match_shoe_type($giayLoai, 'ung') ? 'x' : '', $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['mubh'])->addText($muText, $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['quanao_sl'])->addText(qty_to_text($quanaoQty), $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['quanao_size'])->addText($quanaoSize, $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['quanao_mau'])->addText('', $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['kinh'])->addText(qty_to_text(isset($emp['kinh']) ? $emp['kinh'] : 0), $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['aomua'])->addText(qty_to_text(isset($emp['aomua']) ? $emp['aomua'] : 0), $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['nuttai'])->addText(qty_to_text(isset($emp['nuttai']) ? $emp['nuttai'] : 0), $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['phinloc'])->addText(qty_to_text(isset($emp['phinloc']) ? $emp['phinloc'] : 0), $cellFont, ['alignment' => Jc::CENTER]);
            $table->addCell($reportColumns['kynhan'])->addText('', $cellFont, ['alignment' => Jc::CENTER]);
        }
    }
}

$tmpFile = tempnam(sys_get_temp_dir(), 'bhld_word_');


// ===== NGÀY IN VÀ KÝ TÊN =====
$section->addText('', ['size' => 6], ['spaceAfter' => 60]);

$section->addText(
    'Ngày in: ' . $monthNum . '/' . $year,
    ['size' => 11, 'italic' => true],
    ['alignment' => Jc::LEFT, 'spaceAfter' => 80]
);

$signStyle = [
    'borderSize' => NULL,
    'cellMargin'  => 40,
    'alignment'   => JcTable::CENTER,
    'width' => 10205,
    'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
];
$signTable = $section->addTable($signStyle);
$signTable->addRow();
$cellW = 1700;
$signHeaders = ['NGƯỜI GIAO', 'NGƯỜI LẬP PHIẾU', 'TRƯỞNG PHÒNG', 'CHÁNH KẾ TOÁN', 'THỦ TRƯỞNG'];
foreach ($signHeaders as $sh) {
    $signTable->addCell($cellW)->addText($sh, ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER]);
}
$signTable->addRow();
foreach ($signHeaders as $_) {
    $signTable->addCell($cellW)->addText('', ['size' => 40], ['spaceAfter' => 0]);
}

$docxFile = $tmpFile . '.docx';
@rename($tmpFile, $docxFile);

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($docxFile);

$outputName = 'ChungTu_Cap_Phat_BHLD_' . $monthNum . '_' . $year . '.docx';

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $outputName . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($docxFile));
readfile($docxFile);
@unlink($docxFile);
exit;
