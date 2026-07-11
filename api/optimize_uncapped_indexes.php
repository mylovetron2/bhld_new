<?php
/**
 * Tạo index tối ưu cho màn hình Chưa Cấp Phát.
 * Chạy 1 lần: /api/optimize_uncapped_indexes.php
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method không được hỗ trợ', 405);
}

function indexExists($conn, $table, $indexName) {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $indexEsc = mysqli_real_escape_string($conn, $indexName);
    $sql = "SHOW INDEX FROM `$tableEsc` WHERE Key_name = '$indexEsc'";
    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}

$indexPlans = [
    ['table' => 'bhld_ctu', 'name' => 'idx_ctu_ngct_manv', 'ddl' => 'CREATE INDEX idx_ctu_ngct_manv ON bhld_ctu (ngct, manv)'],
    ['table' => 'bhld_ctu', 'name' => 'idx_ctu_manv_ngct', 'ddl' => 'CREATE INDEX idx_ctu_manv_ngct ON bhld_ctu (manv, ngct)'],
    ['table' => 'bhld_ctctu', 'name' => 'idx_ctctu_mact_sl', 'ddl' => 'CREATE INDEX idx_ctctu_mact_sl ON bhld_ctctu (mact, sl)'],
    ['table' => 'bhld_ctctu', 'name' => 'idx_ctctu_sl_mact', 'ddl' => 'CREATE INDEX idx_ctctu_sl_mact ON bhld_ctctu (sl, mact)'],
    ['table' => 'bhld_nhanvien', 'name' => 'idx_nhanvien_mapb_trangthai', 'ddl' => 'CREATE INDEX idx_nhanvien_mapb_trangthai ON bhld_nhanvien (mapb, trangthai)'],
];

$created = [];
$skipped = [];
$failed = [];

foreach ($indexPlans as $p) {
    if (indexExists($conn, $p['table'], $p['name'])) {
        $skipped[] = $p['name'];
        continue;
    }

    if (mysqli_query($conn, $p['ddl'])) {
        $created[] = $p['name'];
    } else {
        $failed[] = [
            'index' => $p['name'],
            'error' => mysqli_error($conn),
        ];
    }
}

sendSuccess([
    'created' => $created,
    'skipped' => $skipped,
    'failed' => $failed,
], 'Tối ưu index hoàn tất');
