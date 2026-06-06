<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
        
        $sql = "SELECT 
                    mavt,
                    tenvt,
                    dvt,
                    ghichu
                FROM bhld_dmvattu
                WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (tenvt LIKE '%$search%' OR mavt LIKE '%$search%')";
        }
        
        $sql .= " ORDER BY tenvt ASC LIMIT 100";
        
        $result = mysqli_query($conn, $sql);
        $equipment = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $equipment[] = $row;
            }
        }
        
        sendSuccess($equipment, 'Lấy danh sách thiết bị thành công');
    } else {
        sendError('Method không được hỗ trợ', 405);
    }
} catch (Exception $e) {
    sendError('Lỗi server: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
