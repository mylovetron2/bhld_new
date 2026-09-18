<?php
require_once __DIR__ . '/config.php';
// config.php phục vụ API JSON; file này là giao diện HTML nên phải đổi content type.
header('Content-Type: text/html; charset=UTF-8');

$messages = [];
$errors = [];
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$batchSize = 50;
$maxBatches = 200;

function table_exists_safe($conn, $table) {
    $table = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = '$table' LIMIT 1");
    return $result && mysqli_num_rows($result) > 0;
}

function column_exists_safe($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = '$table'
          AND column_name = '$column' LIMIT 1");
    return $result && mysqli_num_rows($result) > 0;
}

function require_schema_safe($conn) {
    foreach (['bhld_ctctu', 'bhld_ctu', 'bhld_dmvattu'] as $table) {
        if (!table_exists_safe($conn, $table)) {
            throw new Exception("Thiếu bảng $table");
        }
    }
    foreach (['size_label', 'mau_label', 'loai_label', 'quycach_label'] as $column) {
        if (!column_exists_safe($conn, 'bhld_ctctu', $column)) {
            throw new Exception("Thiếu cột bhld_ctctu.$column");
        }
    }
}

function query_or_throw($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }
    return $result;
}

function run_batch_update($conn, $sql, $label, $batchSize, $maxBatches) {
    $total = 0;
    for ($i = 0; $i < $maxBatches; $i++) {
        $batchSql = $sql . " LIMIT " . intval($batchSize);
        query_or_throw($conn, $batchSql);
        $affected = mysqli_affected_rows($conn);
        $total += max(0, $affected);
        if ($affected === 0) {
            return "$label: đã cập nhật $total dòng";
        }
    }
    throw new Exception("$label vượt quá giới hạn $maxBatches batch; dừng để kiểm tra an toàn");
}

function run_join_batch_update($conn, $selectSql, $updateSql, $label, $batchSize, $maxBatches) {
    query_or_throw($conn, "CREATE TEMPORARY TABLE IF NOT EXISTS tmp_vattu_batch AS
        SELECT ct.mact, ct.mavt FROM bhld_ctctu ct LIMIT 0");
    query_or_throw($conn, "TRUNCATE TABLE tmp_vattu_batch");
    query_or_throw($conn, $selectSql . " LIMIT " . intval($batchSize));
    $result = query_or_throw($conn, "SELECT COUNT(*) AS total FROM tmp_vattu_batch");
    $count = intval(mysqli_fetch_assoc($result)['total']);
    if ($count === 0) {
        return "$label: không còn dòng cần cập nhật";
    }
    query_or_throw($conn, $updateSql);
    $affected = max(0, mysqli_affected_rows($conn));
    return "$label: đã cập nhật $affected dòng; bấm lại để chạy batch tiếp theo";
}

function verify_data($conn) {
    $result = query_or_throw($conn, "SELECT
        SUM(mavt = 500120 AND size_label IS NOT NULL AND TRIM(size_label) <> '') AS giay_size,
        SUM(mavt = 500120 AND loai_label IS NOT NULL AND TRIM(loai_label) <> '') AS giay_loai,
        SUM(mavt = 500500 AND mau_label IS NOT NULL AND TRIM(mau_label) <> '') AS mu_mau,
        SUM(mavt IN (501545, 501660) AND (size_label IS NOT NULL OR mau_label IS NOT NULL
            OR loai_label IS NOT NULL OR quycach_label IS NOT NULL)) AS vat_tu_cam_co_thuoc_tinh
        FROM bhld_ctctu");
    return mysqli_fetch_assoc($result);
}

if ($action !== '') {
    try {
        require_schema_safe($conn);
        mysqli_begin_transaction($conn);

        if ($action === 'prepare') {
            query_or_throw($conn, "CREATE TABLE IF NOT EXISTS bhld_vattu_thuoctinh (
                mavt INT NOT NULL PRIMARY KEY,
                cho_phep_size TINYINT(1) NOT NULL DEFAULT 0,
                cho_phep_mau TINYINT(1) NOT NULL DEFAULT 0,
                cho_phep_loai TINYINT(1) NOT NULL DEFAULT 0,
                cho_phep_quycach TINYINT(1) NOT NULL DEFAULT 0,
                ghi_chu VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            query_or_throw($conn, "INSERT INTO bhld_vattu_thuoctinh
                (mavt, cho_phep_size, cho_phep_mau, cho_phep_loai, cho_phep_quycach, ghi_chu)
                VALUES
                (500120, 1, 0, 1, 1, 'Giay bao ho'),
                (500500, 0, 1, 1, 1, 'Mu bao ho'),
                (500860, 1, 0, 0, 1, 'Ao quan bao ho'),
                (501545, 0, 0, 0, 0, 'Kinh bao ho'),
                (501660, 0, 0, 0, 0, 'Ao bat di mua')
                ON DUPLICATE KEY UPDATE
                cho_phep_size=VALUES(cho_phep_size), cho_phep_mau=VALUES(cho_phep_mau),
                cho_phep_loai=VALUES(cho_phep_loai), cho_phep_quycach=VALUES(cho_phep_quycach),
                ghi_chu=VALUES(ghi_chu)");
            $messages[] = 'Đã tạo/cập nhật bảng quy tắc và 5 mã vật tư chuẩn.';
        } elseif ($action === 'backup') {
            query_or_throw($conn, "CREATE TABLE IF NOT EXISTS bhld_ctctu_backup_thuoctinh_20260918 AS
                SELECT * FROM bhld_ctctu");
            $messages[] = 'Đã tạo backup bhld_ctctu (nếu chưa tồn tại).';
        } elseif ($action === 'normalize') {
            $messages[] = run_batch_update($conn, "UPDATE bhld_ctctu SET
                size_label=NULLIF(TRIM(size_label), ''),
                mau_label=NULLIF(TRIM(mau_label), ''),
                loai_label=NULLIF(TRIM(loai_label), ''),
                quycach_label=NULLIF(TRIM(quycach_label), '')
                WHERE (size_label IS NOT NULL AND TRIM(size_label) = '')
                   OR (mau_label IS NOT NULL AND TRIM(mau_label) = '')
                   OR (loai_label IS NOT NULL AND TRIM(loai_label) = '')
                   OR (quycach_label IS NOT NULL AND TRIM(quycach_label) = '')", 'Chuẩn hóa NULL', $batchSize, $maxBatches);
        } elseif ($action === 'enforce') {
            if (!table_exists_safe($conn, 'bhld_vattu_thuoctinh')) {
                throw new Exception('Chưa có bảng quy tắc; hãy chạy bước Chuẩn bị trước');
            }
                        $messages[] = run_join_batch_update($conn,
                                "INSERT INTO tmp_vattu_batch (mact, mavt)
                                        SELECT ct.mact, ct.mavt FROM bhld_ctctu ct
                                        INNER JOIN bhld_vattu_thuoctinh q ON q.mavt=ct.mavt
                                        WHERE (q.cho_phep_size=0 AND ct.size_label IS NOT NULL)
                                             OR (q.cho_phep_mau=0 AND ct.mau_label IS NOT NULL)
                                             OR (q.cho_phep_loai=0 AND ct.loai_label IS NOT NULL)
                                             OR (q.cho_phep_quycach=0 AND ct.quycach_label IS NOT NULL)",
                                "UPDATE bhld_ctctu ct
                                        INNER JOIN tmp_vattu_batch b ON b.mact=ct.mact AND b.mavt=ct.mavt
                                        INNER JOIN bhld_vattu_thuoctinh q ON q.mavt=ct.mavt
                                        SET ct.size_label=CASE WHEN q.cho_phep_size=1 THEN ct.size_label ELSE NULL END,
                                                ct.mau_label=CASE WHEN q.cho_phep_mau=1 THEN ct.mau_label ELSE NULL END,
                                                ct.loai_label=CASE WHEN q.cho_phep_loai=1 THEN ct.loai_label ELSE NULL END,
                                                ct.quycach_label=CASE WHEN q.cho_phep_quycach=1 THEN ct.quycach_label ELSE NULL END",
                                'Xóa thuộc tính không được phép', $batchSize, $maxBatches);
        } elseif ($action === 'backfill_shoes') {
            if (!table_exists_safe($conn, 'bhld_nhanvien_hoso')) throw new Exception('Thiếu bảng bhld_nhanvien_hoso');
                        $messages[] = run_join_batch_update($conn, "INSERT INTO tmp_vattu_batch
                                SELECT ct.mact, ct.mavt FROM bhld_ctctu ct
                                INNER JOIN bhld_ctu ctu ON ctu.mact=ct.mact
                                INNER JOIN bhld_nhanvien_hoso hs ON hs.manv=ctu.manv
                                WHERE ct.mavt=500120 AND (ct.size_label IS NULL OR TRIM(ct.size_label)='')
                                    AND hs.giay_size IS NOT NULL AND TRIM(hs.giay_size)<>''",
                                "UPDATE bhld_ctctu ct INNER JOIN tmp_vattu_batch b ON b.mact=ct.mact AND b.mavt=ct.mavt
                                 INNER JOIN bhld_ctu ctu ON ctu.mact=ct.mact INNER JOIN bhld_nhanvien_hoso hs ON hs.manv=ctu.manv
                                 SET ct.size_label=NULLIF(TRIM(hs.giay_size), '')", 'Backfill size giày', $batchSize, $maxBatches);
                        $messages[] = run_join_batch_update($conn, "INSERT INTO tmp_vattu_batch
                                SELECT ct.mact, ct.mavt FROM bhld_ctctu ct
                                INNER JOIN bhld_ctu ctu ON ctu.mact=ct.mact
                                INNER JOIN bhld_nhanvien_hoso hs ON hs.manv=ctu.manv
                                WHERE ct.mavt=500120 AND (ct.loai_label IS NULL OR TRIM(ct.loai_label)='')
                                    AND hs.giay_loai IS NOT NULL AND TRIM(hs.giay_loai)<>''",
                                "UPDATE bhld_ctctu ct INNER JOIN tmp_vattu_batch b ON b.mact=ct.mact AND b.mavt=ct.mavt
                                 INNER JOIN bhld_ctu ctu ON ctu.mact=ct.mact INNER JOIN bhld_nhanvien_hoso hs ON hs.manv=ctu.manv
                                 SET ct.loai_label=NULLIF(TRIM(hs.giay_loai), '')", 'Backfill loại giày', $batchSize, $maxBatches);
        } elseif ($action === 'backfill_clothes') {
            if (!table_exists_safe($conn, 'bhld_nhanvien_hoso')) throw new Exception('Thiếu bảng bhld_nhanvien_hoso');
                        $messages[] = run_join_batch_update($conn, "INSERT INTO tmp_vattu_batch
                                SELECT ct.mact, ct.mavt FROM bhld_ctctu ct
                                INNER JOIN bhld_ctu ctu ON ctu.mact=ct.mact
                                INNER JOIN bhld_nhanvien_hoso hs ON hs.manv=ctu.manv
                                WHERE ct.mavt=500860 AND (ct.size_label IS NULL OR TRIM(ct.size_label)='')
                                    AND hs.quanao_size IS NOT NULL AND TRIM(hs.quanao_size)<>''",
                                "UPDATE bhld_ctctu ct INNER JOIN tmp_vattu_batch b ON b.mact=ct.mact AND b.mavt=ct.mavt
                                 INNER JOIN bhld_ctu ctu ON ctu.mact=ct.mact INNER JOIN bhld_nhanvien_hoso hs ON hs.manv=ctu.manv
                                 SET ct.size_label=NULLIF(TRIM(hs.quanao_size), '')", 'Backfill size áo quần', $batchSize, $maxBatches);
        } elseif ($action === 'backfill_helmet') {
            if (!table_exists_safe($conn, 'bhld_nhanvien_hoso')) throw new Exception('Thiếu bảng bhld_nhanvien_hoso');
                        $messages[] = run_join_batch_update($conn, "INSERT INTO tmp_vattu_batch
                                SELECT ct.mact, ct.mavt FROM bhld_ctctu ct
                                INNER JOIN bhld_ctu ctu ON ctu.mact=ct.mact
                                INNER JOIN bhld_nhanvien_hoso hs ON hs.manv=ctu.manv
                                WHERE ct.mavt=500500 AND (ct.mau_label IS NULL OR TRIM(ct.mau_label)='')
                                    AND hs.mu_mau IS NOT NULL AND TRIM(hs.mu_mau)<>''",
                                "UPDATE bhld_ctctu ct INNER JOIN tmp_vattu_batch b ON b.mact=ct.mact AND b.mavt=ct.mavt
                                 INNER JOIN bhld_ctu ctu ON ctu.mact=ct.mact INNER JOIN bhld_nhanvien_hoso hs ON hs.manv=ctu.manv
                                 SET ct.mau_label=NULLIF(TRIM(hs.mu_mau), '')", 'Backfill màu mũ', $batchSize, $maxBatches);
        } elseif ($action === 'verify') {
            $data = verify_data($conn);
            $messages[] = 'Giày có size: ' . intval($data['giay_size']);
            $messages[] = 'Giày có loại: ' . intval($data['giay_loai']);
            $messages[] = 'Mũ có màu: ' . intval($data['mu_mau']);
            $messages[] = 'Vật tư bị cấm còn thuộc tính: ' . intval($data['vat_tu_cam_co_thuoc_tinh']);
        } else {
            throw new Exception('Bước không hợp lệ');
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $errors[] = $e->getMessage();
    }
}

$ready = table_exists_safe($conn, 'bhld_vattu_thuoctinh');
$lastVerify = null;
if ($ready) {
    try { $lastVerify = verify_data($conn); } catch (Throwable $e) { $errors[] = $e->getMessage(); }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Migration thuộc tính vật tư</title>
<style>
body{font:14px Arial,sans-serif;background:#f3f6f8;color:#1e2933;margin:0;padding:24px}
main{max-width:980px;margin:auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 2px 12px #0001}
h1{margin-top:0;color:#175a86}.note{padding:12px;background:#fff7e6;border-left:4px solid #e39b19;margin:12px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}form{margin:0}button{width:100%;padding:10px;border:0;border-radius:5px;background:#1769aa;color:#fff;cursor:pointer}button.warn{background:#a85b16}button.safe{background:#29734a}.box{padding:12px;margin-top:16px;border:1px solid #d5dee5;border-radius:6px}.ok{color:#176b40}.err{color:#b42318}.muted{color:#66737e}table{width:100%;border-collapse:collapse}th,td{padding:7px;border:1px solid #d5dee5;text-align:left}th{background:#edf5fa}
</style>
</head>
<body><main>
<h1>Migration thuộc tính vật tư</h1>
<div class="note"><strong>Chạy an toàn từng bước.</strong> Mỗi lần bấm chỉ xử lý một transaction và tối đa 50 dòng. Với dữ liệu nhiều, hãy bấm lại cùng nút cho đến khi báo “không còn dòng cần cập nhật”; cách này tránh timeout và lock kéo dài.</div>
<div class="grid">
<form method="post"><button name="action" value="prepare">1. Chuẩn bị bảng quy tắc</button></form>
<form method="post"><button name="action" value="backup" class="warn">2. Tạo backup</button></form>
<form method="post"><button name="action" value="normalize">3. Chuẩn hóa chuỗi rỗng</button></form>
<form method="post"><button name="action" value="enforce" class="warn">4. Xóa thuộc tính sai</button></form>
<form method="post"><button name="action" value="backfill_shoes">5. Backfill giày</button></form>
<form method="post"><button name="action" value="backfill_clothes">6. Backfill áo quần</button></form>
<form method="post"><button name="action" value="backfill_helmet">7. Backfill màu mũ</button></form>
<form method="post"><button name="action" value="verify" class="safe">8. Kiểm tra kết quả</button></form>
</div>
<div class="box"><strong>Trạng thái:</strong> <?php echo $ready ? '<span class="ok">Đã có bảng quy tắc</span>' : '<span class="muted">Chưa có bảng quy tắc</span>'; ?>
<?php if ($lastVerify): ?><table><tr><th>Chỉ số</th><th>Giá trị</th></tr><tr><td>Giày có size</td><td><?php echo intval($lastVerify['giay_size']); ?></td></tr><tr><td>Giày có loại</td><td><?php echo intval($lastVerify['giay_loai']); ?></td></tr><tr><td>Mũ có màu</td><td><?php echo intval($lastVerify['mu_mau']); ?></td></tr><tr><td>Vật tư cấm còn thuộc tính</td><td><?php echo intval($lastVerify['vat_tu_cam_co_thuoc_tinh']); ?></td></tr></table><?php endif; ?></div>
<?php if ($messages): ?><div class="box"><strong class="ok">Kết quả</strong><?php foreach($messages as $message): ?><div><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($errors): ?><div class="box err"><strong>Lỗi</strong><?php foreach($errors as $error): ?><div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?>
<p class="muted">Sau khi chạy xong, xóa hoặc hạn chế quyền truy cập file này trên server.</p>
</main></body></html>
