<?php
/**
 * Quy tac thuoc tinh vat tu.
 * Migration api/migration_vattu_thuoctinh.sql phai duoc chay truoc.
 */
function getVattuAttributeRules($conn, $mavt) {
    $mavt = intval($mavt);
    $defaults = [
        'cho_phep_size' => 0,
        'cho_phep_mau' => 0,
        'cho_phep_loai' => 0,
        'cho_phep_quycach' => 0,
    ];

    $table = mysqli_query($conn, "SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'bhld_vattu_thuoctinh' LIMIT 1");
    if (!$table || mysqli_num_rows($table) === 0) {
        return $defaults;
    }

    $result = mysqli_query($conn, "SELECT cho_phep_size, cho_phep_mau,
        cho_phep_loai, cho_phep_quycach
        FROM bhld_vattu_thuoctinh WHERE mavt = $mavt LIMIT 1");
    if (!$result || mysqli_num_rows($result) === 0) {
        return $defaults;
    }

    $row = mysqli_fetch_assoc($result);
    return [
        'cho_phep_size' => intval($row['cho_phep_size']) ? 1 : 0,
        'cho_phep_mau' => intval($row['cho_phep_mau']) ? 1 : 0,
        'cho_phep_loai' => intval($row['cho_phep_loai']) ? 1 : 0,
        'cho_phep_quycach' => intval($row['cho_phep_quycach']) ? 1 : 0,
    ];
}

function normalizedAttribute($conn, $value, $allowed) {
    $value = trim((string)(isset($value) ? $value : ''));
    if (!$allowed || $value === '') {
        return '';
    }
    return mysqli_real_escape_string($conn, $value);
}

function sanitizeVattuAttributes($conn, $mavt, $input) {
    $rules = getVattuAttributeRules($conn, $mavt);
    $sizeIn = isset($input['size']) ? $input['size'] : (isset($input['size_label']) ? $input['size_label'] : '');
    $mauIn = isset($input['mau']) ? $input['mau'] : (isset($input['mau_label']) ? $input['mau_label'] : '');
    $loaiIn = isset($input['loai']) ? $input['loai'] : (isset($input['loai_label']) ? $input['loai_label'] : '');
    $quycachIn = isset($input['quycach']) ? $input['quycach'] : (isset($input['quycach_label']) ? $input['quycach_label'] : '');

    $size = normalizedAttribute($conn, $sizeIn, $rules['cho_phep_size']);
    $mau = normalizedAttribute($conn, $mauIn, $rules['cho_phep_mau']);
    $loai = normalizedAttribute($conn, $loaiIn, $rules['cho_phep_loai']);
    $quycach = normalizedAttribute($conn, $quycachIn, $rules['cho_phep_quycach']);

    return [
        'size' => $size,
        'mau' => $mau,
        'loai' => $loai,
        'quycach' => $quycach,
        'rules' => $rules,
    ];
}

function attributeSqlValue($conn, $value) {
    return $value === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $value) . "'";
}
?>
