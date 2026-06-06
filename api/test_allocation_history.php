<?php
/**
 * Test allocation_history.php API
 * http://diavatly.com/BHLD/api/test_allocation_history.php
 */

require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

$tests = [];

// Test 1: Kiểm tra bảng bhld_ctctu
try {
    $sql = "SELECT COUNT(*) as total FROM bhld_ctctu WHERE sl = 1";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    $tests['allocated_count'] = [
        'status' => 'OK',
        'count' => intval($row['total']),
        'message' => 'Số thiết bị đang được cấp phát'
    ];
} catch (Exception $e) {
    $tests['allocated_count'] = [
        'status' => 'ERROR',
        'error' => $e->getMessage()
    ];
}

// Test 2: Query đơn giản - không JOIN
try {
    $sql = "SELECT 
                mact,
                mavt,
                sl,
                ngnhan,
                ngnhantt,
                dmtg
            FROM bhld_ctctu
            WHERE sl = 1
            ORDER BY ngnhan DESC
            LIMIT 5";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }
    
    $simple_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $simple_data[] = $row;
    }
    
    $tests['simple_query'] = [
        'status' => 'OK',
        'count' => count($simple_data),
        'data' => $simple_data
    ];
} catch (Exception $e) {
    $tests['simple_query'] = [
        'status' => 'ERROR',
        'error' => $e->getMessage()
    ];
}

// Test 3: Query với JOIN (như API thực tế)
try {
    $sql = "SELECT 
                ct.mact,
                ct.manv,
                ct.ngct,
                nv.tennhanvien,
                pb.tenphong as tenphongban,
                ctd.mavt,
                vt.tenvt,
                vt.dvt,
                ctd.sl,
                ctd.ngnhan,
                ctd.ngnhantt,
                ctd.dmtg
            FROM bhld_ctctu ctd
            INNER JOIN bhld_ctu ct ON ctd.mact = ct.mact
            LEFT JOIN bhld_nhanvien nv ON ct.manv = nv.manv
            LEFT JOIN bhld_phongban pb ON ct.mapb = pb.mapb
            LEFT JOIN bhld_dmvattu vt ON ctd.mavt = vt.mavt
            WHERE ctd.sl = 1
            ORDER BY ct.ngct DESC, ctd.ngnhan DESC
            LIMIT 5";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }
    
    $joined_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $joined_data[] = $row;
    }
    
    $tests['joined_query'] = [
        'status' => 'OK',
        'count' => count($joined_data),
        'data' => $joined_data
    ];
} catch (Exception $e) {
    $tests['joined_query'] = [
        'status' => 'ERROR',
        'error' => $e->getMessage(),
        'sql' => $sql ?? 'N/A'
    ];
}

// Test 4: Kiểm tra structure bảng bhld_ctctu
try {
    $sql = "DESCRIBE bhld_ctctu";
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

echo json_encode([
    'success' => true,
    'message' => 'Test allocation history API',
    'tests' => $tests,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

mysqli_close($conn);
?>
