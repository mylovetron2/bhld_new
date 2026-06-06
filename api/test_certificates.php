<?php
/**
 * Test API certificates.php trực tiếp
 * Truy cập: http://diavatly.com/BHLD/api/test_certificates.php
 */

require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

$tests = [];

// Test 1: Đếm số chứng từ
try {
    $sql = "SELECT COUNT(*) as total FROM bhld_ctu";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $tests['total_certificates'] = [
        'status' => 'OK',
        'count' => intval($row['total'])
    ];
} catch (Exception $e) {
    $tests['total_certificates'] = [
        'status' => 'ERROR',
        'error' => $e->getMessage()
    ];
}

// Test 2: Kiểm tra cấu trúc bảng bhld_ctu
try {
    $sql = "DESCRIBE bhld_ctu";
    $result = mysqli_query($conn, $sql);
    
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = [
            'field' => $row['Field'],
            'type' => $row['Type']
        ];
    }
    
    $tests['table_structure'] = [
        'status' => 'OK',
        'columns' => $columns
    ];
} catch (Exception $e) {
    $tests['table_structure'] = [
        'status' => 'ERROR',
        'error' => $e->getMessage()
    ];
}

// Test 3: Lấy 5 chứng từ mẫu (query giống API)
try {
    $sql = "SELECT 
                ct.mact,
                ct.ngct,
                ct.mapb,
                ct.manv,
                ct.ghichu,
                ct.madm,
                nv.tennhanvien,
                pb.tenphong as tenphongban
            FROM bhld_ctu ct
            LEFT JOIN bhld_nhanvien nv ON ct.manv = nv.manv
            LEFT JOIN bhld_phongban pb ON ct.mapb = pb.mapb
            ORDER BY ct.ngct DESC
            LIMIT 5";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        $certificates = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $certificates[] = $row;
        }
        
        $tests['sample_certificates'] = [
            'status' => 'OK',
            'count' => count($certificates),
            'data' => $certificates
        ];
    } else {
        $tests['sample_certificates'] = [
            'status' => 'ERROR',
            'error' => mysqli_error($conn)
        ];
    }
} catch (Exception $e) {
    $tests['sample_certificates'] = [
        'status' => 'ERROR',
        'error' => $e->getMessage()
    ];
}

// Test 4: Test với filter (manv cụ thể)
try {
    // Lấy 1 mã NV bất kỳ có chứng từ
    $sql_manv = "SELECT DISTINCT manv FROM bhld_ctu LIMIT 1";
    $result_manv = mysqli_query($conn, $sql_manv);
    
    if ($result_manv && mysqli_num_rows($result_manv) > 0) {
        $row_manv = mysqli_fetch_assoc($result_manv);
        $test_manv = $row_manv['manv'];
        
        $sql = "SELECT 
                    ct.mact,
                    ct.ngct,
                    ct.manv
                FROM bhld_ctu ct
                WHERE ct.manv = '$test_manv'
                LIMIT 3";
        
        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            $certificates = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $certificates[] = $row;
            }
            
            $tests['filter_by_manv'] = [
                'status' => 'OK',
                'test_manv' => $test_manv,
                'count' => count($certificates),
                'data' => $certificates
            ];
        } else {
            $tests['filter_by_manv'] = [
                'status' => 'ERROR',
                'error' => mysqli_error($conn)
            ];
        }
    } else {
        $tests['filter_by_manv'] = [
            'status' => 'SKIP',
            'message' => 'Không có chứng từ nào trong DB'
        ];
    }
} catch (Exception $e) {
    $tests['filter_by_manv'] = [
        'status' => 'ERROR',
        'error' => $e->getMessage()
    ];
}

// Kết quả
echo json_encode([
    'success' => true,
    'message' => 'Test certificates API hoàn tất',
    'tests' => $tests,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

mysqli_close($conn);
?>
