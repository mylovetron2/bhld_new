<?php
require_once 'config.php';

function tableExists($conn, $tableName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$tableName' LIMIT 1";
    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $manv = isset($_GET['manv']) ? mysqli_real_escape_string($conn, trim($_GET['manv'])) : '';

        if ($manv !== '' && tableExists($conn, 'bhld_nhanvien_vattu_dm')) {
            $sqlPolicy = "SELECT
                            p.manv,
                            p.mavt,
                            p.dmuc_thang as dmtg,
                            p.so_luong,
                            p.active,
                            p.source_madm,
                            p.ghi_chu,
                            IFNULL(vt.tenvt, '') as tenvt,
                            IFNULL(vt.dvt, '') as dvt
                         FROM bhld_nhanvien_vattu_dm p
                         LEFT JOIN bhld_dmvattu vt ON p.mavt = vt.mavt
                         WHERE p.manv = '$manv' AND p.active = 1
                         ORDER BY p.mavt";

            $rp = mysqli_query($conn, $sqlPolicy);
            if (!$rp) {
                sendError('Lỗi truy vấn định mức theo nhân viên: ' . mysqli_error($conn), 500);
            }

            $items = [];
            while ($row = mysqli_fetch_assoc($rp)) {
                $items[] = $row;
            }

            if (!empty($items)) {
                sendSuccess([
                    'source' => 'employee_policy',
                    'manv' => $manv,
                    'items' => $items,
                ], 'Lấy định mức theo nhân viên thành công');
            }
        }

        // Lấy danh sách định mức
        $result = mysqli_query($conn, "SELECT * FROM bhld_dmuc ORDER BY madm");
        if (!$result) sendError('Lỗi truy vấn bhld_dmuc: ' . mysqli_error($conn), 500);

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['chitiet'] = [];
            $rows[$row['madm']] = $row;
        }

        // Lấy chi tiết định mức JOIN vật tư
        $result2 = mysqli_query($conn,
            "SELECT ct.madm, ct.mavt, ct.dmuc as dmtg,
                    IFNULL(vt.tenvt, '') as tenvt,
                    IFNULL(vt.dvt, '') as dvt
             FROM bhld_ctdmuc ct
             LEFT JOIN bhld_dmvattu vt ON ct.mavt = vt.mavt
             ORDER BY ct.madm, ct.mavt"
        );
        if ($result2) {
            while ($r = mysqli_fetch_assoc($result2)) {
                $madm = $r['madm'];
                if (isset($rows[$madm])) {
                    $rows[$madm]['chitiet'][] = $r;
                }
            }
        }

        sendSuccess(array_values($rows), 'Lấy danh sách định mức thành công');
    } else {
        sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
?>
