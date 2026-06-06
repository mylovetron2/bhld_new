<?php
require_once 'config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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
?>
