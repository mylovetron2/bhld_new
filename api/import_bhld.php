<?php
/**
 * Import nhân viên + hồ sơ + policy vật tư từ Excel
 * Yêu cầu:
 * - Có bảng: bhld_nhanvien, bhld_nhanvien_hoso, bhld_nhanvien_vattu_dm, bhld_dmvattu
 * - Có composer package phpoffice/phpspreadsheet (cho .xlsx)
 * 
 * Cách dùng:
 * 1) Truy cập script bằng trình duyệt
 * 2) Chọn file Excel theo mẫu cột
 * 3) Bấm Import
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=UTF-8'); 
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function vn_norm($s) {
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

function to_nullable_string($v) {
    $v = trim((string)$v);
    return $v === '' ? null : $v;
}

function find_col_idx($headerRow, $aliases) {
    $normalized = array_map(fn($x) => vn_norm((string)$x), $headerRow);
    foreach ($aliases as $alias) {
        $needle = vn_norm((string)$alias);
        foreach ($normalized as $idx => $h) {
            if ($h === $needle || strpos($h, $needle) !== false) {
                return (int)$idx;
            }
        }
    }
    return -1;
}

function load_rows_from_file($tmpPath, $originalName) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $rows = [];

    if ($ext === 'csv') {
        if (($fp = fopen($tmpPath, 'r')) === false) {
            throw new Exception('Không mở được file CSV');
        }
        while (($data = fgetcsv($fp, 0, ',')) !== false) {
            $rows[] = $data;
        }
        fclose($fp);
        return $rows;
    }

    if ($ext === 'xlsx' || $ext === 'xls') {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload)) {
            throw new Exception('Thiếu vendor/autoload.php. Cài thư viện: composer require phpoffice/phpspreadsheet');
        }
        require_once $autoload;
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpPath);
        $spreadsheet = $reader->load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        return $rows;
    }

    throw new Exception('Định dạng file không hỗ trợ. Chỉ nhận .xlsx, .xls, .csv');
}

function normalize_manv_from_danhso($v) {
    $s = trim((string)$v);
    if ($s === '') return null;

    if (is_numeric($s)) {
        if (strpos($s, '.') !== false) {
            $f = (float)$s;
            if (abs($f - round($f)) < 0.0000001) {
                return (string)(int)round($f);
            }
        }
        return (string)$s;
    }

    return $s;
}

function to_dmuc_thang_or_null($v) {
    $s = trim((string)$v);
    if ($s === '') return null;

    $s = str_replace(',', '.', $s);
    if (!is_numeric($s)) return null;

    $n = (float)$s;
    if ($n <= 0) return null;

    if (abs($n - round($n)) < 0.0000001) {
        return (int)round($n);
    }

    return max(1, (int)round($n * 12));
}

function ensure_equipment_fixed_map($conn) {
    $fixed = [
        'nut_tai'      => ['mavt' => 10000,  'tenvt' => 'Nút tai chống ồn',       'dvt' => 'đôi'],
        'phin_loc'     => ['mavt' => 20000,  'tenvt' => 'Phin lọc khí độc',       'dvt' => 'cái'],
        'gang_tay'     => ['mavt' => 30000,  'tenvt' => 'Găng tay',               'dvt' => 'đôi'],
        'khau_trang'   => ['mavt' => 40000,  'tenvt' => 'Khẩu trang',             'dvt' => 'cái'],
        'ao_phao'      => ['mavt' => 60000,  'tenvt' => 'Áo phao cứu sinh',       'dvt' => 'cái'],
        'gang_tay_han' => ['mavt' => 70000,  'tenvt' => 'Găng tay da thợ hàn',    'dvt' => 'đôi'],
        'ao_mua'       => ['mavt' => 501660, 'tenvt' => 'Áo bạt đi mưa',          'dvt' => 'cái'],
        'giay'         => ['mavt' => 500120, 'tenvt' => 'Giày bảo hộ',            'dvt' => 'Đôi'],
        'mu'           => ['mavt' => 500500, 'tenvt' => 'Mũ bảo hộ',              'dvt' => 'Chiếc'],
        'quan_ao'      => ['mavt' => 500860, 'tenvt' => 'Áo quần bảo hộ',         'dvt' => 'Bộ'],
        'kinh'         => ['mavt' => 501545, 'tenvt' => 'Kính bảo hộ',            'dvt' => 'Cặp'],
    ];

    foreach ($fixed as $info) {
        $mavt = (int)$info['mavt'];
        $tenvt = mysqli_real_escape_string($conn, $info['tenvt']);
        $dvt = mysqli_real_escape_string($conn, $info['dvt']);

        $sql = "INSERT INTO bhld_dmvattu (mavt, tenvt, dvt, ghichu)
                VALUES ($mavt, '$tenvt', '$dvt', 'Đồng bộ từ import Excel')
                ON DUPLICATE KEY UPDATE
                  tenvt = VALUES(tenvt),
                  dvt = VALUES(dvt)";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Không upsert được vật tư mã ' . $mavt . ': ' . mysqli_error($conn));
        }
    }

    $map = [];
    foreach ($fixed as $k => $info) $map[$k] = (int)$info['mavt'];
    return $map;
}

function fetch_departments($conn) {
    $list = [];
    $sql = "
        SELECT d.mapb, COALESCE(pb.tenphong, d.mapb) AS tenphong
        FROM (
            SELECT DISTINCT mapb
            FROM bhld_nhanvien
            WHERE mapb IS NOT NULL AND TRIM(mapb) <> ''
        ) d
        LEFT JOIN bhld_phongban pb ON pb.mapb = d.mapb
        ORDER BY d.mapb
    ";
    $rs = mysqli_query($conn, $sql);
    if (!$rs) {
        throw new Exception('Không tải được danh sách phòng ban: ' . mysqli_error($conn));
    }
    while ($row = mysqli_fetch_assoc($rs)) {
        $list[] = [
            'mapb' => (string)$row['mapb'],
            'tenphong' => (string)($row['tenphong'] ?? $row['mapb']),
        ];
    }
    return $list;
}

function fetch_employees_by_department($conn, $mapb) {
    $list = [];
    if ($mapb === null || $mapb === '') {
        return $list;
    }

    $st = mysqli_prepare($conn, "
        SELECT manv, tennhanvien, dinhmuc
        FROM bhld_nhanvien
        WHERE mapb = ?
        ORDER BY tennhanvien, manv
    ");
    if (!$st) {
        throw new Exception('Không prepare được danh sách nhân viên: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($st, 's', $mapb);
    if (!mysqli_stmt_execute($st)) {
        throw new Exception('Không tải được danh sách nhân viên: ' . mysqli_stmt_error($st));
    }
    $rs = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($rs)) {
        $list[] = [
            'manv' => (string)$row['manv'],
            'tennhanvien' => (string)($row['tennhanvien'] ?? ''),
            'dinhmuc' => $row['dinhmuc'] ?? null,
        ];
    }
    return $list;
}

function fetch_manual_employee_details($conn, $manv) {
    if ($manv === null || $manv === '') {
        return null;
    }

    $mavtToField = [
        500120 => 'manual_giay_dm',
        500860 => 'manual_quanao_dm',
        500500 => 'manual_mu_dm',
        501545 => 'manual_kinh_dm',
        501660 => 'manual_aomua_dm',
        30000 => 'manual_gangtay_dm',
        40000 => 'manual_khautrang_dm',
        20000 => 'manual_phinloc_dm',
        60000 => 'manual_aophao_dm',
        10000 => 'manual_nuttai_dm',
        70000 => 'manual_gangtayhan_dm',
    ];

    $result = [
        'manual_dinhmuc' => '',
        'manual_chucdanh' => '',
        'manual_giay_size' => '',
        'manual_giay_loai' => '',
        'manual_quanao_size' => '',
        'manual_mu_mau' => '',
        'manual_giay_dm' => '',
        'manual_quanao_dm' => '',
        'manual_mu_dm' => '',
        'manual_kinh_dm' => '',
        'manual_gangtay_dm' => '',
        'manual_khautrang_dm' => '',
        'manual_aomua_dm' => '',
        'manual_phinloc_dm' => '',
        'manual_aophao_dm' => '',
        'manual_nuttai_dm' => '',
        'manual_gangtayhan_dm' => '',
    ];

    $stBase = mysqli_prepare($conn, "
        SELECT nv.dinhmuc, hs.giay_size, hs.giay_loai, hs.quanao_size, hs.mu_mau, hs.ghi_chu
        FROM bhld_nhanvien nv
        LEFT JOIN bhld_nhanvien_hoso hs ON hs.manv = nv.manv
        WHERE nv.manv = ?
        LIMIT 1
    ");
    if (!$stBase) {
        throw new Exception('Không prepare được dữ liệu nhân viên: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stBase, 's', $manv);
    if (!mysqli_stmt_execute($stBase)) {
        throw new Exception('Không tải được dữ liệu nhân viên: ' . mysqli_stmt_error($stBase));
    }
    $base = mysqli_fetch_assoc(mysqli_stmt_get_result($stBase));
    if (!$base) {
        return null;
    }

    $result['manual_dinhmuc'] = (string)($base['dinhmuc'] ?? '');
    $result['manual_giay_size'] = (string)($base['giay_size'] ?? '');
    $result['manual_giay_loai'] = (string)($base['giay_loai'] ?? '');
    $result['manual_quanao_size'] = (string)($base['quanao_size'] ?? '');
    $result['manual_mu_mau'] = (string)($base['mu_mau'] ?? '');
    $ghiChu = trim((string)($base['ghi_chu'] ?? ''));
    if (stripos($ghiChu, 'Chức danh:') === 0) {
        $result['manual_chucdanh'] = trim(substr($ghiChu, 10));
    }

    $stPolicy = mysqli_prepare($conn, "
        SELECT mavt, dmuc_thang
        FROM bhld_nhanvien_vattu_dm
        WHERE manv = ? AND active = 1
    ");
    if (!$stPolicy) {
        throw new Exception('Không prepare được policy nhân viên: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stPolicy, 's', $manv);
    if (!mysqli_stmt_execute($stPolicy)) {
        throw new Exception('Không tải được policy nhân viên: ' . mysqli_stmt_error($stPolicy));
    }
    $rsPolicy = mysqli_stmt_get_result($stPolicy);
    while ($row = mysqli_fetch_assoc($rsPolicy)) {
        $mavt = (int)$row['mavt'];
        $dmuc = (int)$row['dmuc_thang'];
        if (isset($mavtToField[$mavt])) {
            $result[$mavtToField[$mavt]] = $dmuc > 0 ? (string)$dmuc : '';
        }
    }

    return $result;
}

$report = [
    'total_rows' => 0,
    'imported' => 0,
    'skipped' => 0,
    'failed' => 0,
    'errors' => [],
    'row_logs' => [],
];

$manualResult = [
    'ok' => null,
    'message' => '',
];

$selectedMapb = to_nullable_string($_POST['manual_mapb'] ?? $_GET['manual_mapb'] ?? '');
$selectedManv = to_nullable_string($_POST['manual_manv'] ?? $_GET['manual_manv'] ?? '');
$postAction = (string)($_POST['action'] ?? 'excel_import');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'manual_save') {
    try {
        $manualMapb = to_nullable_string($_POST['manual_mapb'] ?? '');
        $manualManv = to_nullable_string($_POST['manual_manv'] ?? '');

        if ($manualMapb === null) {
            throw new Exception('Vui lòng chọn phòng ban');
        }
        if ($manualManv === null) {
            throw new Exception('Vui lòng chọn nhân viên');
        }

        $selectedMapb = $manualMapb;
        $selectedManv = $manualManv;

        $stFind = mysqli_prepare($conn, "
            SELECT manv, tennhanvien
            FROM bhld_nhanvien
            WHERE manv = ? AND mapb = ?
            LIMIT 1
        ");
        if (!$stFind) {
            throw new Exception('Lỗi prepare kiểm tra nhân viên: ' . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stFind, 'ss', $manualManv, $manualMapb);
        if (!mysqli_stmt_execute($stFind)) {
            throw new Exception('Lỗi kiểm tra nhân viên: ' . mysqli_stmt_error($stFind));
        }
        $rsFind = mysqli_stmt_get_result($stFind);
        $employee = mysqli_fetch_assoc($rsFind);
        if (!$employee) {
            throw new Exception('Nhân viên không thuộc phòng ban đã chọn');
        }

        $manualDinhmuc = to_nullable_string($_POST['manual_dinhmuc'] ?? '');
        $manualChucDanh = to_nullable_string($_POST['manual_chucdanh'] ?? '');
        $manualGiaySize = to_nullable_string($_POST['manual_giay_size'] ?? '');
        $manualGiayLoai = to_nullable_string($_POST['manual_giay_loai'] ?? '');
        $manualQuanaoSize = to_nullable_string($_POST['manual_quanao_size'] ?? '');
        $manualMuMau = to_nullable_string($_POST['manual_mu_mau'] ?? '');

        $manualPolicy = [
            ['key' => 'giay', 'dm' => to_dmuc_thang_or_null($_POST['manual_giay_dm'] ?? '')],
            ['key' => 'quan_ao', 'dm' => to_dmuc_thang_or_null($_POST['manual_quanao_dm'] ?? '')],
            ['key' => 'mu', 'dm' => to_dmuc_thang_or_null($_POST['manual_mu_dm'] ?? '')],
            ['key' => 'kinh', 'dm' => to_dmuc_thang_or_null($_POST['manual_kinh_dm'] ?? '')],
            ['key' => 'gang_tay', 'dm' => to_dmuc_thang_or_null($_POST['manual_gangtay_dm'] ?? '')],
            ['key' => 'khau_trang', 'dm' => to_dmuc_thang_or_null($_POST['manual_khautrang_dm'] ?? '')],
            ['key' => 'ao_mua', 'dm' => to_dmuc_thang_or_null($_POST['manual_aomua_dm'] ?? '')],
            ['key' => 'phin_loc', 'dm' => to_dmuc_thang_or_null($_POST['manual_phinloc_dm'] ?? '')],
            ['key' => 'ao_phao', 'dm' => to_dmuc_thang_or_null($_POST['manual_aophao_dm'] ?? '')],
            ['key' => 'nut_tai', 'dm' => to_dmuc_thang_or_null($_POST['manual_nuttai_dm'] ?? '')],
            ['key' => 'gang_tay_han', 'dm' => to_dmuc_thang_or_null($_POST['manual_gangtayhan_dm'] ?? '')],
        ];

        mysqli_begin_transaction($conn);

        $mavtMap = ensure_equipment_fixed_map($conn);

        $stEmpManual = mysqli_prepare($conn, "
            UPDATE bhld_nhanvien
            SET mapb = ?, dinhmuc = ?
            WHERE manv = ?
        ");
        if (!$stEmpManual) throw new Exception('Lỗi prepare cập nhật nhân viên: ' . mysqli_error($conn));
        mysqli_stmt_bind_param($stEmpManual, 'sss', $manualMapb, $manualDinhmuc, $manualManv);
        if (!mysqli_stmt_execute($stEmpManual)) {
            throw new Exception('Lỗi cập nhật nhân viên: ' . mysqli_stmt_error($stEmpManual));
        }

        $stProfileManual = mysqli_prepare($conn, "
            INSERT INTO bhld_nhanvien_hoso (manv, giay_size, giay_loai, quanao_size, mu_mau, ghi_chu)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                giay_size = VALUES(giay_size),
                giay_loai = VALUES(giay_loai),
                quanao_size = VALUES(quanao_size),
                mu_mau = VALUES(mu_mau),
                ghi_chu = VALUES(ghi_chu)
        ");
        if (!$stProfileManual) throw new Exception('Lỗi prepare cập nhật hồ sơ: ' . mysqli_error($conn));

        $manualGhiChu = $manualChucDanh ? ('Chức danh: ' . $manualChucDanh) : null;
        mysqli_stmt_bind_param($stProfileManual, 'ssssss', $manualManv, $manualGiaySize, $manualGiayLoai, $manualQuanaoSize, $manualMuMau, $manualGhiChu);
        if (!mysqli_stmt_execute($stProfileManual)) {
            throw new Exception('Lỗi cập nhật hồ sơ: ' . mysqli_stmt_error($stProfileManual));
        }

        $stPolicyManual = mysqli_prepare($conn, "
            INSERT INTO bhld_nhanvien_vattu_dm (manv, mavt, dmuc_thang, so_luong, active, source_madm, ghi_chu)
            VALUES (?, ?, ?, 1, 1, ?, NULL)
            ON DUPLICATE KEY UPDATE
                dmuc_thang = VALUES(dmuc_thang),
                so_luong = VALUES(so_luong),
                active = VALUES(active),
                source_madm = VALUES(source_madm),
                ghi_chu = VALUES(ghi_chu)
        ");
        if (!$stPolicyManual) throw new Exception('Lỗi prepare cập nhật policy: ' . mysqli_error($conn));

        $manualSourceMadm = $manualDinhmuc ?? 'MANUAL_ENTRY';
        foreach ($manualPolicy as $pc) {
            if ($pc['dm'] === null || $pc['dm'] <= 0) continue;
            $mavt = (int)$mavtMap[$pc['key']];
            $dmuc = (int)$pc['dm'];
            mysqli_stmt_bind_param($stPolicyManual, 'siis', $manualManv, $mavt, $dmuc, $manualSourceMadm);
            if (!mysqli_stmt_execute($stPolicyManual)) {
                throw new Exception('Lỗi cập nhật policy mã vật tư ' . $mavt . ': ' . mysqli_stmt_error($stPolicyManual));
            }
        }

        mysqli_commit($conn);
        $manualResult['ok'] = true;
        $manualResult['message'] = 'Đã lưu nhập tay cho nhân viên ' . $manualManv . ' - ' . (string)$employee['tennhanvien'];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $manualResult['ok'] = false;
        $manualResult['message'] = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'excel_import') {
    try {
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Vui lòng chọn file Excel hợp lệ');
        }

        $rows = load_rows_from_file($_FILES['excel_file']['tmp_name'], $_FILES['excel_file']['name']);
        if (count($rows) < 2) {
            throw new Exception('File không có dữ liệu');
        }

        // Bỏ dòng header: tìm dòng có chữ "Danh số"
        $startIdx = 1;
        foreach ($rows as $i => $r) {
            $line = vn_norm(implode(' ', array_map(fn($x) => (string)$x, $r)));
            if (strpos($line, 'danh so') !== false && strpos($line, 'ho va ten') !== false) {
                $startIdx = $i + 1;
                break;
            }
        }

        mysqli_begin_transaction($conn);

        // mapping cột (0-based)
        // 0 STT | 1 Danh số | 2 Họ và tên | 3 Chức danh
        // 4 Giày size | 5 Giày loại | 6 Giày dmtg
        // 7 Quần áo size | 8 Quần áo dmtg
        // 9 Mũ màu | 10 Mũ dmtg
        // 11 Kính | 12 Găng tay | 13 Khẩu trang | 14 Áo mưa | 15 Phin lọc khí độc
        // 16 Áo phao cứu sinh | 17 Nút bịt tai chống ồn | 18 Găng tay da thợ hàn

        $mavtMap = ensure_equipment_fixed_map($conn);

        // Prepared statements
        $stEmp = mysqli_prepare($conn, "
            INSERT INTO bhld_nhanvien (manv, tennhanvien, mapb, dinhmuc)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                tennhanvien = VALUES(tennhanvien),
                mapb = VALUES(mapb),
                dinhmuc = VALUES(dinhmuc)
        ");
        if (!$stEmp) throw new Exception('Lỗi prepare nhân viên: ' . mysqli_error($conn));

        $stProfile = mysqli_prepare($conn, "
            INSERT INTO bhld_nhanvien_hoso (manv, giay_size, giay_loai, quanao_size, mu_mau, ghi_chu)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                giay_size = VALUES(giay_size),
                giay_loai = VALUES(giay_loai),
                quanao_size = VALUES(quanao_size),
                mu_mau = VALUES(mu_mau),
                ghi_chu = VALUES(ghi_chu)
        ");
        if (!$stProfile) throw new Exception('Lỗi prepare hồ sơ: ' . mysqli_error($conn));

        $stPolicy = mysqli_prepare($conn, "
            INSERT INTO bhld_nhanvien_vattu_dm (manv, mavt, dmuc_thang, so_luong, active, source_madm, ghi_chu)
            VALUES (?, ?, ?, 1, 1, ?, NULL)
            ON DUPLICATE KEY UPDATE
                dmuc_thang = VALUES(dmuc_thang),
                so_luong = VALUES(so_luong),
                active = VALUES(active),
                source_madm = VALUES(source_madm),
                ghi_chu = VALUES(ghi_chu)
        ");
        if (!$stPolicy) throw new Exception('Lỗi prepare policy: ' . mysqli_error($conn));

        $stEmpLookup = mysqli_prepare($conn, "
            SELECT mapb, dinhmuc
            FROM bhld_nhanvien
            WHERE manv = ?
            LIMIT 1
        ");
        if (!$stEmpLookup) throw new Exception('Lỗi prepare kiểm tra nhân viên: ' . mysqli_error($conn));

        for ($i = $startIdx; $i < count($rows); $i++) {
            $r = $rows[$i];
            $lineNo = $i + 1;

            $manv = normalize_manv_from_danhso($r[1] ?? '');
            $ten = to_nullable_string($r[2] ?? '');

            if ($manv === null || $ten === null) {
                $report['skipped']++;
                $report['row_logs'][] = [
                    'line' => $lineNo,
                    'manv' => $manv ?? '',
                    'ten' => $ten ?? '',
                    'status' => 'skipped',
                    'message' => 'Thiếu Danh số hoặc Họ và tên',
                ];
                continue;
            }

            $report['total_rows']++;

            mysqli_query($conn, "SAVEPOINT sp_row_{$lineNo}");

            $chucDanh = to_nullable_string($r[3] ?? '');

            $giaySize = to_nullable_string($r[4] ?? '');
            $giayLoai = to_nullable_string($r[5] ?? '');
            $giayDm = to_dmuc_thang_or_null($r[6] ?? '');

            $quanaoSize = to_nullable_string($r[7] ?? '');
            $quanaoDm = to_dmuc_thang_or_null($r[8] ?? '');

            $muMau = to_nullable_string($r[9] ?? '');
            $muDm = to_dmuc_thang_or_null($r[10] ?? '');

            $kinhDm = to_dmuc_thang_or_null($r[11] ?? '');
            $gangTayDm = to_dmuc_thang_or_null($r[12] ?? '');
            $khauTrangDm = to_dmuc_thang_or_null($r[13] ?? '');
            $aoMuaDm = to_dmuc_thang_or_null($r[14] ?? '');
            $phinLocDm = to_dmuc_thang_or_null($r[15] ?? '');
            $aoPhaoDm = to_dmuc_thang_or_null($r[16] ?? '');
            $nutTaiDm = to_dmuc_thang_or_null($r[17] ?? '');
            $gangTayHanDm = to_dmuc_thang_or_null($r[18] ?? '');

            mysqli_stmt_bind_param($stEmpLookup, 's', $manv);
            if (!mysqli_stmt_execute($stEmpLookup)) {
                throw new Exception('Lỗi tra cứu nhân viên: ' . mysqli_stmt_error($stEmpLookup));
            }
            $empFound = mysqli_fetch_assoc(mysqli_stmt_get_result($stEmpLookup));

            if (!$empFound) {
                $report['skipped']++;
                $report['row_logs'][] = [
                    'line' => $lineNo,
                    'manv' => $manv,
                    'ten' => $ten,
                    'status' => 'skipped',
                    'message' => 'Danh số chưa tồn tại trong hệ thống, vui lòng thêm nhân viên trước',
                ];
                continue;
            }

            $mapb = to_nullable_string($empFound['mapb'] ?? '');
            $dm = to_nullable_string($empFound['dinhmuc'] ?? '');
            $sourceMadm = $dm ?? 'IMPORT_EXCEL';

            if ($mapb === null) {
                $report['skipped']++;
                $report['row_logs'][] = [
                    'line' => $lineNo,
                    'manv' => $manv,
                    'ten' => $ten,
                    'status' => 'skipped',
                    'message' => 'Nhân viên chưa có phòng ban trong hệ thống',
                ];
                continue;
            }

            try {
                mysqli_stmt_bind_param($stEmp, 'ssss', $manv, $ten, $mapb, $dm);
                if (!mysqli_stmt_execute($stEmp)) {
                    throw new Exception('Lỗi upsert nhân viên: ' . mysqli_stmt_error($stEmp));
                }

                $ghiChu = $chucDanh ? ('Chức danh: ' . $chucDanh) : null;
                mysqli_stmt_bind_param($stProfile, 'ssssss', $manv, $giaySize, $giayLoai, $quanaoSize, $muMau, $ghiChu);
                if (!mysqli_stmt_execute($stProfile)) {
                    throw new Exception('Lỗi upsert hồ sơ: ' . mysqli_stmt_error($stProfile));
                }

                $policyCols = [
                    ['key' => 'giay', 'dm' => $giayDm],
                    ['key' => 'quan_ao', 'dm' => $quanaoDm],
                    ['key' => 'mu', 'dm' => $muDm],
                    ['key' => 'kinh', 'dm' => $kinhDm],
                    ['key' => 'gang_tay', 'dm' => $gangTayDm],
                    ['key' => 'khau_trang', 'dm' => $khauTrangDm],
                    ['key' => 'ao_mua', 'dm' => $aoMuaDm],
                    ['key' => 'phin_loc', 'dm' => $phinLocDm],
                    ['key' => 'ao_phao', 'dm' => $aoPhaoDm],
                    ['key' => 'nut_tai', 'dm' => $nutTaiDm],
                    ['key' => 'gang_tay_han', 'dm' => $gangTayHanDm],
                ];

                foreach ($policyCols as $pc) {
                    if ($pc['dm'] === null || $pc['dm'] <= 0) continue;
                    $mavt = (int)$mavtMap[$pc['key']];
                    $dmuc = (int)$pc['dm'];
                    mysqli_stmt_bind_param($stPolicy, 'siis', $manv, $mavt, $dmuc, $sourceMadm);
                    if (!mysqli_stmt_execute($stPolicy)) {
                        throw new Exception('Lỗi upsert policy mã vật tư ' . $mavt . ': ' . mysqli_stmt_error($stPolicy));
                    }
                }

                $report['imported']++;
                $report['row_logs'][] = [
                    'line' => $lineNo,
                    'manv' => $manv,
                    'ten' => $ten,
                    'status' => 'success',
                    'message' => 'Import thành công',
                ];
            } catch (Throwable $rowEx) {
                mysqli_query($conn, "ROLLBACK TO SAVEPOINT sp_row_{$lineNo}");
                $report['failed']++;
                $report['row_logs'][] = [
                    'line' => $lineNo,
                    'manv' => $manv,
                    'ten' => $ten,
                    'status' => 'failed',
                    'message' => $rowEx->getMessage(),
                ];
            }
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $report['errors'][] = $e->getMessage();
    }
}

$departments = [];
$employeesInDepartment = [];
$selectedEmployee = null;
$manualFormValues = [
    'manual_dinhmuc' => '',
    'manual_chucdanh' => '',
    'manual_giay_size' => '',
    'manual_giay_loai' => '',
    'manual_quanao_size' => '',
    'manual_mu_mau' => '',
    'manual_giay_dm' => '',
    'manual_quanao_dm' => '',
    'manual_mu_dm' => '',
    'manual_kinh_dm' => '',
    'manual_gangtay_dm' => '',
    'manual_khautrang_dm' => '',
    'manual_aomua_dm' => '',
    'manual_phinloc_dm' => '',
    'manual_aophao_dm' => '',
    'manual_nuttai_dm' => '',
    'manual_gangtayhan_dm' => '',
];
try {
    $departments = fetch_departments($conn);
    $employeesInDepartment = fetch_employees_by_department($conn, $selectedMapb);
    foreach ($employeesInDepartment as $emp) {
        if ((string)$emp['manv'] === (string)$selectedManv) {
            $selectedEmployee = $emp;
            break;
        }
    }
    if ($selectedEmployee) {
        $loaded = fetch_manual_employee_details($conn, $selectedEmployee['manv']);
        if (is_array($loaded)) {
            $manualFormValues = array_merge($manualFormValues, $loaded);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'manual_save') {
        foreach (array_keys($manualFormValues) as $k) {
            if (array_key_exists($k, $_POST)) {
                $manualFormValues[$k] = (string)$_POST[$k];
            }
        }
    }
} catch (Throwable $e) {
    $report['errors'][] = $e->getMessage();
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Import Excel BHLD</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; }
    .card { border: 1px solid #ddd; border-radius: 8px; padding: 16px; max-width: 760px; }
    .row { margin-bottom: 10px; }
    label { display: inline-block; min-width: 120px; font-weight: 600; }
    input[type=text], input[type=file] { width: 460px; max-width: 100%; padding: 6px; }
    button { padding: 8px 14px; cursor: pointer; }
    .ok { color: #0a7a0a; }
    .err { color: #b00020; }
        .log-wrap { margin-top: 12px; max-height: 360px; overflow: auto; border: 1px solid #eee; border-radius: 6px; }
        .log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .log-table th, .log-table td { border-bottom: 1px solid #f0f0f0; padding: 6px 8px; text-align: left; vertical-align: top; }
        .log-ok { color: #0a7a0a; font-weight: 600; }
        .log-skip { color: #9a6a00; font-weight: 600; }
        .log-fail { color: #b00020; font-weight: 600; }
        .card + .card { margin-top: 16px; }
        .manual-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
        .manual-grid .row { margin-bottom: 0; }
        select, input[type=number] { width: 100%; max-width: 100%; padding: 6px; }
  </style>
</head>
<body>
  <div class="card">
    <h3>Import nhân viên từ Excel</h3>
                <p>Mẫu cột: STT, Danh số, Họ và tên, Chức danh, Giầy, Quần áo, Mũ, Kính, Găng tay, Khẩu trang, Áo mưa, Phin lọc khí độc, Áo phao cứu sinh, Nút bịt tai chống ồn, Găng tay da thợ hàn. Không cần cột Mã phòng ban/Mã định mức.</p>

    <form method="post" enctype="multipart/form-data">
      <div class="row">
        <label>File Excel</label>
        <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required>
      </div>
      <div class="row">
        <button type="submit">Import</button>
      </div>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
      <hr>
      <div class="ok">Tổng dòng dữ liệu: <?php echo (int)$report['total_rows']; ?></div>
      <div class="ok">Import thành công: <?php echo (int)$report['imported']; ?></div>
      <div>Bỏ qua: <?php echo (int)$report['skipped']; ?></div>
            <div class="err">Thất bại: <?php echo (int)$report['failed']; ?></div>
      <?php if (!empty($report['errors'])): ?>
        <div class="err">
          <?php foreach ($report['errors'] as $er): ?>
            <div>- <?php echo h($er); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

            <?php if (!empty($report['row_logs'])): ?>
                <div class="log-wrap">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Dòng</th>
                                <th>Danh số</th>
                                <th>Họ và tên</th>
                                <th>Kết quả</th>
                                <th>Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['row_logs'] as $log): ?>
                                <?php
                                    $st = (string)($log['status'] ?? '');
                                    $stText = $st === 'success' ? 'Thành công' : ($st === 'skipped' ? 'Bỏ qua' : 'Thất bại');
                                    $stClass = $st === 'success' ? 'log-ok' : ($st === 'skipped' ? 'log-skip' : 'log-fail');
                                ?>
                                <tr>
                                    <td><?php echo (int)($log['line'] ?? 0); ?></td>
                                    <td><?php echo h($log['manv'] ?? ''); ?></td>
                                    <td><?php echo h($log['ten'] ?? ''); ?></td>
                                    <td class="<?php echo $stClass; ?>"><?php echo h($stText); ?></td>
                                    <td><?php echo h($log['message'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
    <?php endif; ?>
  </div>

    <div class="card">
        <h3>Nhập tay theo nhân viên</h3>
        <p>Chọn phòng ban, chọn nhân viên, sau đó nhập thông số và định mức cho đúng người.</p>

        <form method="get" class="manual-grid" style="margin-bottom: 10px;">
            <div class="row">
                <label>Phòng ban</label>
                <select name="manual_mapb" onchange="this.form.submit()">
                    <option value="">-- Chọn phòng ban --</option>
                    <?php foreach ($departments as $dep): ?>
                        <option value="<?php echo h($dep['mapb']); ?>" <?php echo ((string)$selectedMapb === (string)$dep['mapb']) ? 'selected' : ''; ?>>
                            <?php echo h($dep['mapb'] . ' - ' . $dep['tenphong']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row">
                <label>Nhân viên</label>
                <select name="manual_manv" onchange="this.form.submit()" <?php echo empty($employeesInDepartment) ? 'disabled' : ''; ?>>
                    <option value="">-- Chọn nhân viên --</option>
                    <?php foreach ($employeesInDepartment as $emp): ?>
                        <option value="<?php echo h($emp['manv']); ?>" <?php echo ((string)$selectedManv === (string)$emp['manv']) ? 'selected' : ''; ?>>
                            <?php echo h($emp['manv'] . ' - ' . $emp['tennhanvien']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <form method="post">
            <input type="hidden" name="action" value="manual_save">
            <input type="hidden" name="manual_mapb" value="<?php echo h($selectedMapb ?? ''); ?>">
            <input type="hidden" name="manual_manv" value="<?php echo h($selectedManv ?? ''); ?>">

            <div class="row">
                <label>Nhân viên đã chọn</label>
                <input type="text" value="<?php echo h($selectedEmployee ? ($selectedEmployee['manv'] . ' - ' . $selectedEmployee['tennhanvien']) : 'Chưa chọn nhân viên'); ?>" readonly>
            </div>

            <div class="manual-grid">
                <div class="row">
                    <label>Mã định mức</label>
                    <input type="text" name="manual_dinhmuc" value="<?php echo h($manualFormValues['manual_dinhmuc']); ?>" placeholder="VD: DM001">
                </div>
                <div class="row">
                    <label>Chức danh</label>
                    <input type="text" name="manual_chucdanh" value="<?php echo h($manualFormValues['manual_chucdanh']); ?>">
                </div>
                <div class="row">
                    <label>Size giầy</label>
                    <input type="text" name="manual_giay_size" value="<?php echo h($manualFormValues['manual_giay_size']); ?>">
                </div>
                <div class="row">
                    <label>Loại giầy</label>
                    <input type="text" name="manual_giay_loai" value="<?php echo h($manualFormValues['manual_giay_loai']); ?>">
                </div>
                <div class="row">
                    <label>Size quần áo</label>
                    <input type="text" name="manual_quanao_size" value="<?php echo h($manualFormValues['manual_quanao_size']); ?>">
                </div>
                <div class="row">
                    <label>Màu mũ</label>
                    <input type="text" name="manual_mu_mau" value="<?php echo h($manualFormValues['manual_mu_mau']); ?>">
                </div>
                <div class="row">
                    <label>ĐM giầy</label>
                    <input type="text" name="manual_giay_dm" value="<?php echo h($manualFormValues['manual_giay_dm']); ?>" placeholder="VD: 1 hoặc 0.5">
                </div>
                <div class="row">
                    <label>ĐM quần áo</label>
                    <input type="text" name="manual_quanao_dm" value="<?php echo h($manualFormValues['manual_quanao_dm']); ?>" placeholder="VD: 1 hoặc 0.5">
                </div>
                <div class="row">
                    <label>ĐM mũ</label>
                    <input type="text" name="manual_mu_dm" value="<?php echo h($manualFormValues['manual_mu_dm']); ?>" placeholder="VD: 1 hoặc 0.5">
                </div>
                <div class="row">
                    <label>ĐM kính</label>
                    <input type="text" name="manual_kinh_dm" value="<?php echo h($manualFormValues['manual_kinh_dm']); ?>" placeholder="VD: 1 hoặc 0.5">
                </div>
                <div class="row">
                    <label>ĐM găng tay</label>
                    <input type="text" name="manual_gangtay_dm" value="<?php echo h($manualFormValues['manual_gangtay_dm']); ?>" placeholder="VD: 1 hoặc 0.5">
                </div>
                <div class="row">
                    <label>ĐM khẩu trang</label>
                    <input type="text" name="manual_khautrang_dm" value="<?php echo h($manualFormValues['manual_khautrang_dm']); ?>" placeholder="VD: 1 hoặc 0.5">
                </div>
                <div class="row">
                    <label>ĐM áo mưa</label>
                    <input type="text" name="manual_aomua_dm" value="<?php echo h($manualFormValues['manual_aomua_dm']); ?>" placeholder="VD: 1 hoặc 0.5">
                </div>
                <div class="row">
                    <label>ĐM phin lọc</label>
                    <input type="text" name="manual_phinloc_dm" value="<?php echo h($manualFormValues['manual_phinloc_dm']); ?>" placeholder="VD: 1 hoặc 0.5">
                </div>
                <div class="row">
                    <label>ĐM áo phao</label>
                    <input type="text" name="manual_aophao_dm" value="<?php echo h($manualFormValues['manual_aophao_dm']); ?>" placeholder="VD: 1 hoặc 0.5">
                </div>
                <div class="row">
                    <label>ĐM nút tai</label>
                    <input type="text" name="manual_nuttai_dm" value="<?php echo h($manualFormValues['manual_nuttai_dm']); ?>" placeholder="VD: 1 hoặc 0.5">
                </div>
                <div class="row">
                    <label>ĐM găng tay hàn</label>
                    <input type="text" name="manual_gangtayhan_dm" value="<?php echo h($manualFormValues['manual_gangtayhan_dm']); ?>" placeholder="VD: 1 hoặc 0.5">
                </div>
            </div>

            <div class="row" style="margin-top:12px;">
                <button type="submit" <?php echo $selectedEmployee ? '' : 'disabled'; ?>>Lưu nhập tay</button>
            </div>
        </form>

        <?php if ($manualResult['ok'] !== null): ?>
            <div class="<?php echo $manualResult['ok'] ? 'ok' : 'err'; ?>" style="margin-top:10px;">
                <?php echo h($manualResult['message']); ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>