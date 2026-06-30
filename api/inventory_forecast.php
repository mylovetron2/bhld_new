<?php
/**
 * API Báo cáo vật tư cần cấp phát trong N tháng tới
 * GET /inventory_forecast.php?months=3
 *
 * Trả về danh sách vật tư sắp hết hạn (ngnhantt trong khoảng từ hôm nay đến N tháng tới)
 * nhóm theo vật tư, kèm số lượng cần cấp và tồn kho hiện tại.
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') { http_response_code(200); exit; }
if ($method !== 'GET') { sendError('Method không được hỗ trợ', 405); }

$months = max(1, min(12, intval(isset($_GET['months']) ? $_GET['months'] : 3)));
$today    = date('Y-m-d');
$deadline = date('Y-m-d', strtotime("+$months months"));

// Lấy danh sách vật tư sắp đến hạn cấp lại (sl=1 = đang dùng, ngnhantt trong khoảng)
// JOIN bhld_ctu và bhld_nhanvien để đồng bộ với report_schedule.php (chỉ tính NV còn trong hệ thống)
$sql = "SELECT
            ct.mavt,
            d.tenvt,
            d.dvt,
            COUNT(*)                                      AS so_luong_can_cap,
            MIN(ct.ngnhantt)                              AS ngay_can_cap_som_nhat,
            MAX(ct.ngnhantt)                              AS ngay_can_cap_muon_nhat,
            COALESCE(t.so_luong_nhap, 0) - COALESCE(t.so_luong_cap_phat, 0) AS ton_hien_tai
        FROM bhld_ctctu ct
        JOIN bhld_ctu   ctu ON ctu.mact = ct.mact
        JOIN bhld_nhanvien nv ON nv.manv = ctu.manv
        JOIN bhld_dmvattu d  ON d.mavt  = ct.mavt
        LEFT JOIN bhld_tonkho t ON t.mavt = ct.mavt
        WHERE ct.sl = 1
          AND ct.ngnhantt != '1911-11-11'
          AND ct.ngnhantt >= '$today'
          AND ct.ngnhantt <= '$deadline'
        GROUP BY ct.mavt, d.tenvt, d.dvt, t.so_luong_nhap, t.so_luong_cap_phat
        ORDER BY ngay_can_cap_som_nhat ASC, so_luong_can_cap DESC
        LIMIT 200";

$res = mysqli_query($conn, $sql);
if (!$res) sendError('Lỗi truy vấn: ' . mysqli_error($conn), 500);

$items = [];
while ($r = mysqli_fetch_assoc($res)) {
    $ton = intval($r['ton_hien_tai']);
    $can = intval($r['so_luong_can_cap']);
    $r['ton_hien_tai']   = $ton;
    $r['so_luong_can_cap'] = $can;
    $r['thieu']           = max(0, $can - $ton);  // số lượng thiếu so với tồn kho
    $items[] = $r;
}

// Thống kê tổng hợp
$tongCanCap   = array_sum(array_column($items, 'so_luong_can_cap'));
$tongThieu    = array_sum(array_column($items, 'thieu'));
$soLoaiVT     = count($items);
$loaiThieu    = count(array_filter($items, fn($r) => $r['thieu'] > 0));

sendSuccess([
    'months'       => $months,
    'from_date'    => $today,
    'to_date'      => $deadline,
    'tong_loai_vt' => $soLoaiVT,
    'tong_can_cap' => $tongCanCap,
    'tong_thieu'   => $tongThieu,
    'loai_thieu'   => $loaiThieu,
    'items'        => $items,
], "Vật tư cần cấp trong $months tháng tới");

mysqli_close($conn);
?>
