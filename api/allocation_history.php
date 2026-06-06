<?php
/**
 * API Lấy lịch sử cấp phát
 * GET /allocation_history.php
 * 
 * Parameters:
 * - manv: Mã nhân viên (optional)
 * - mavt: Mã vật tư (optional)
 * - from_date: Từ ngày (yyyy-MM-dd) (optional)
 * - to_date: Đến ngày (yyyy-MM-dd) (optional)
 * - status: Trạng thái (allocated/returned/all) (optional, default: all)
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $manv = isset($_GET['manv']) ? mysqli_real_escape_string($conn, $_GET['manv']) : '';
        $mavt = isset($_GET['mavt']) ? intval($_GET['mavt']) : 0;
        $from_date = isset($_GET['from_date']) ? mysqli_real_escape_string($conn, $_GET['from_date']) : '';
        $to_date = isset($_GET['to_date']) ? mysqli_real_escape_string($conn, $_GET['to_date']) : '';
        $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
        
        // Build WHERE conditions first to optimize query
        $where = [];
        
        // Filter by status (most selective)
        if ($status === 'allocated') {
            $where[] = "ctd.sl = 1";
        } elseif ($status === 'returned') {
            $where[] = "ctd.sl = 0";
        }
        
        // Filter by employee
        if (!empty($manv)) {
            $where[] = "ct.manv = '$manv'";
        }
        
        // Filter by equipment
        if ($mavt > 0) {
            $where[] = "ctd.mavt = $mavt";
        }
        
        // Filter by date range
        if (!empty($from_date)) {
            $where[] = "ct.ngct >= '$from_date'";
        }
        if (!empty($to_date)) {
            $where[] = "ct.ngct <= '$to_date'";
        }
        
        $where_clause = empty($where) ? "1=1" : implode(" AND ", $where);
        
        // Simplified query - only essential JOINs
        $sql = "SELECT 
                    ct.mact,
                    ct.manv,
                    ct.ngct,
                    IFNULL(nv.tennhanvien, '') as tennhanvien,
                    IFNULL(pb.tenphong, '') as tenphongban,
                    ctd.mavt,
                    IFNULL(vt.tenvt, '') as tenvt,
                    IFNULL(vt.dvt, '') as dvt,
                    ctd.sl,
                    ctd.ngnhan,
                    ctd.ngnhantt,
                    ctd.dmtg
                FROM bhld_ctctu ctd
                INNER JOIN bhld_ctu ct ON ctd.mact = ct.mact
                LEFT JOIN bhld_nhanvien nv ON ct.manv = nv.manv
                LEFT JOIN bhld_phongban pb ON ct.mapb = pb.mapb
                LEFT JOIN bhld_dmvattu vt ON ctd.mavt = vt.mavt
                WHERE $where_clause
                ORDER BY ct.ngct DESC
                LIMIT 20";
        
        $result = mysqli_query($conn, $sql);
        
        if (!$result) {
            sendError('Lỗi query: ' . mysqli_error($conn));
        }
        
        $history = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }
        
        sendSuccess($history, 'Lấy lịch sử cấp phát thành công');
    } else {
        sendError('Method không được hỗ trợ', 405);
    }
} catch (Exception $e) {
    sendError('Lỗi server: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
