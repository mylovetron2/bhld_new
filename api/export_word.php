<?php
// Generate Word dynamically (no template) for BHLD report.
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/db_connection.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Thiếu vendor/autoload.php. Cài thư viện: composer require phpoffice/phpword";
    exit;
}
require_once $autoload;

if (!class_exists('PhpOffice\\PhpWord\\PhpWord')) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Thiếu thư viện PhpWord. Cài: composer require phpoffice/phpword";
    exit;
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

function extract_chuc_danh($ghiChu) {
    $s = trim((string)$ghiChu);
    if ($s === '') return '';
    if (mb_stripos($s, 'Chức danh:') === 0) {
        return trim(mb_substr($s, 10, null, 'UTF-8'));
    }
    return $s;
}

function normalize_search_text($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
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
    WHERE v.ngct <= '$escEnd'
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
        'giay_size' => (string)($row['giay_size'] ?? ''),
        'giay_loai' => (string)($row['giay_loai'] ?? ''),
        'quanao_size' => (string)($row['quanao_size'] ?? ''),
        'mu_mau' => (string)($row['mu_mau'] ?? ''),
        'chucdanh' => extract_chuc_danh($row['hoso_ghichu'] ?? ''),
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

$columns = [
    ['key' => 'stt', 'label' => 'STT', 'w' => 380],
    ['key' => 'manv', 'label' => 'Danh số', 'w' => 760],
    ['key' => 'tennhanvien', 'label' => 'Họ và tên', 'w' => 1550],
    ['key' => 'giaybh', 'label' => 'Giày', 'w' => 520],
    ['key' => 'quanao', 'label' => 'Quần áo', 'w' => 520],
    ['key' => 'mubh', 'label' => 'Mũ', 'w' => 520],
    ['key' => 'kinh', 'label' => 'Kính', 'w' => 520],
    ['key' => 'gangtay', 'label' => 'Găng tay', 'w' => 520],
    ['key' => 'khautrang', 'label' => 'Khẩu trang', 'w' => 520],
    ['key' => 'aomua', 'label' => 'Áo mưa', 'w' => 520],
    ['key' => 'phinloc', 'label' => 'Phin lọc', 'w' => 520],
    ['key' => 'aophao', 'label' => 'Áo phao', 'w' => 520],
    ['key' => 'nuttai', 'label' => 'Nút tai', 'w' => 520],
    ['key' => 'gangtayhan', 'label' => 'GT da hàn', 'w' => 520],
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

function build_stat_detail_text(array $employees, string $equipKey): string {
    $buckets = [];
    foreach ($employees as $emp) {
        $qty = (int)($emp[$equipKey] ?? 0);
        if ($qty <= 0) {
            continue;
        }

        $label = '';
        if ($equipKey === 'giaybh') {
            $parts = [];
            $size = trim((string)($emp['giay_size'] ?? ''));
            $type = trim((string)($emp['giay_loai'] ?? ''));
            if ($size !== '') {
                $parts[] = 'size ' . $size;
            }
            if ($type !== '') {
                $parts[] = 'loại ' . $type;
            }
            $label = $parts ? implode(', ', $parts) : 'Không rõ size/loại';
        } elseif ($equipKey === 'quanao') {
            $size = trim((string)($emp['quanao_size'] ?? ''));
            $label = $size !== '' ? 'size ' . $size : 'Không rõ size';
        } elseif ($equipKey === 'mubh') {
            $color = trim((string)($emp['mu_mau'] ?? ''));
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

function build_department_title(string $mapb, string $tenphong): string {
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

if (empty($departments)) {
    $section->addText('Không có dữ liệu để xuất trong kỳ này.', ['italic' => true, 'size' => 12]);
} else {
    foreach ($departments as $mapb => $dept) {
        $deptTotals = array_fill_keys(array_keys($equipmentStatLabels), 0);
        foreach ($dept['employees'] as $emp) {
            foreach ($deptTotals as $key => $_) {
                $deptTotals[$key] += (int)($emp[$key] ?? 0);
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
            $qty = (int)($deptTotals[$key] ?? 0);
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
                $right = $nonZeroStats[$idx + 1] ?? null;

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
        $table->addRow();
        foreach ($columns as $c) {
            $table->addCell($c['w'], $headerCellStyle)->addText($c['label'], $headerFont, ['alignment' => Jc::CENTER]);
        }

        $stt = 1;
        foreach ($dept['employees'] as $emp) {
            $table->addRow();
            foreach ($columns as $c) {
                if ($c['key'] === 'stt') {
                    $value = (string)$stt;
                } elseif ($c['key'] === 'tennhanvien') {
                    $cell = $table->addCell($c['w']);
                    $cell->addText(trim((string)($emp['tennhanvien'] ?? '')), ['size' => 11], ['alignment' => Jc::LEFT]);
                    continue;
                } else {
                    $value = $emp[$c['key']] ?? '';
                    if (is_int($value) || is_float($value)) {
                        $value = $value > 0 ? (string)$value : '';
                    } else {
                        $value = trim((string)$value);
                    }

                    if ($value !== '') {
                        if ($c['key'] === 'giaybh') {
                            $giaySize = trim((string)($emp['giay_size'] ?? ''));
                            $giayLoai = trim((string)($emp['giay_loai'] ?? ''));
                            if ($giaySize !== '' && $giayLoai !== '') {
                                $value .= ' (' . $giaySize . '-' . $giayLoai . ')';
                            } elseif ($giaySize !== '') {
                                $value .= ' (' . $giaySize . ')';
                            } elseif ($giayLoai !== '') {
                                $value .= ' (' . $giayLoai . ')';
                            }
                        } elseif ($c['key'] === 'quanao') {
                            $qaSize = trim((string)($emp['quanao_size'] ?? ''));
                            if ($qaSize !== '') {
                                $value .= ' (' . $qaSize . ')';
                            }
                        } elseif ($c['key'] === 'mubh') {
                            $muMau = trim((string)($emp['mu_mau'] ?? ''));
                            if ($muMau !== '') {
                                $value .= ' (' . $muMau . ')';
                            }
                        }
                    }
                }
                $align = in_array($c['key'], ['stt', 'giaybh', 'quanao', 'mubh', 'kinh', 'gangtay', 'khautrang', 'aomua', 'phinloc', 'aophao', 'nuttai', 'gangtayhan'], true)
                    ? Jc::CENTER
                    : Jc::LEFT;
                $table->addCell($c['w'])->addText($value, $cellFont, ['alignment' => $align]);
            }
            $stt++;
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
