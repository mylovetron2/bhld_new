<?php
/**
 * Test API Endpoint
 * Kiểm tra kết nối database và cấu trúc bảng
 * 
 * Truy cập: http://diavatly.com/BHLD/api/test.php
 */

require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

$tests = [];

// Test 1: Kiểm tra kết nối database
try {
    $result = mysqli_query($conn, "SELECT 1 as test");
    if ($result && mysqli_num_rows($result) > 0) {
        $tests['database_connection'] = [
            'status' => 'OK',
            'message' => 'Kết nối MySQL thành công',
            'server_info' => mysqli_get_server_info($conn)
        ];
    } else {
        $tests['database_connection'] = [
            'status' => 'ERROR',
            'message' => 'Không thể query database'
        ];
    }
} catch (Exception $e) {
    $tests['database_connection'] = [
        'status' => 'ERROR',
        'message' => $e->getMessage()
    ];
}

// Test 2: Kiểm tra bảng tồn tại
$tables = ['bhld_nhanvien', 'bhld_phongban', 'bhld_ctu', 'bhld_ctctu', 'bhld_dmvattu'];
$tests['tables'] = [];

foreach ($tables as $table) {
    try {
        $sql = "SELECT COUNT(*) as count FROM $table";
        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $tests['tables'][$table] = [
                'exists' => true,
                'count' => intval($row['count'])
            ];
        } else {
            $tests['tables'][$table] = [
                'exists' => false,
                'error' => mysqli_error($conn)
            ];
        }
    } catch (Exception $e) {
        $tests['tables'][$table] = [
            'exists' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Test 3: Lấy mẫu dữ liệu từ bhld_nhanvien
try {
    $sql = "SELECT 
                nv.manv,
                nv.tennhanvien,
                nv.mapb,
                nv.dinhmuc,
                pb.tenphong as tenphongban
            FROM bhld_nhanvien nv
            LEFT JOIN bhld_phongban pb ON nv.mapb = pb.mapb
            LIMIT 5";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $employees = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $employees[] = $row;
        }
        $tests['sample_employees'] = [
            'status' => 'OK',
            'count' => count($employees),
            'data' => $employees
        ];
    } else {
        $tests['sample_employees'] = [
            'status' => 'ERROR',
            'error' => mysqli_error($conn)
        ];
    }
} catch (Exception $e) {
    $tests['sample_employees'] = [
        'status' => 'ERROR',
        'error' => $e->getMessage()
    ];
}

// Test 4: Kiểm tra cấu trúc cột của bảng bhld_nhanvien
try {
    $sql = "DESCRIBE bhld_nhanvien";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $columns = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = [
                'field' => $row['Field'],
                'type' => $row['Type'],
                'null' => $row['Null'],
                'key' => $row['Key']
            ];
        }
        $tests['table_structure'] = [
            'status' => 'OK',
            'columns' => $columns
        ];
    } else {
        $tests['table_structure'] = [
            'status' => 'ERROR',
            'error' => mysqli_error($conn)
        ];
    }
} catch (Exception $e) {
    $tests['table_structure'] = [
        'status' => 'ERROR',
        'error' => $e->getMessage()
    ];
}

// Kết quả cuối cùng
$response = [
    'success' => true,
    'message' => 'Test API hoàn tất',
    'tests' => $tests,
    'php_version' => phpversion(),
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

mysqli_close($conn);
?>
