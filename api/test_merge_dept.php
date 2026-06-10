<?php
/**
 * Test logic gom phòng ban
 */

// Giả lập dữ liệu
$data = [
    ['name' => 'Xưởng SC và CC ĐVL', 'spokenlg' => [
        ['manv' => 'NV001', 'tennhanvien' => 'Nguyễn A', 'giaybh' => 1, 'mubh' => '', 'quanao' => '', 'kinh' => '', 'aomua' => '', 'nuttai' => '', 'phinloc' => ''],
        ['manv' => 'NV002', 'tennhanvien' => 'Trần B', 'giaybh' => '', 'mubh' => 1, 'quanao' => '', 'kinh' => '', 'aomua' => '', 'nuttai' => '', 'phinloc' => ''],
    ]],
    ['name' => 'Xưởng SC cơ khí chuyên dụng', 'spokenlg' => [
        ['manv' => 'NV003', 'tennhanvien' => 'Lê C', 'giaybh' => 1, 'mubh' => '', 'quanao' => 1, 'kinh' => '', 'aomua' => '', 'nuttai' => '', 'phinloc' => ''],
    ]],
    ['name' => 'Phòng khác', 'spokenlg' => [
        ['manv' => 'NV004', 'tennhanvien' => 'Phạm D', 'giaybh' => '', 'mubh' => '', 'quanao' => 1, 'kinh' => '', 'aomua' => '', 'nuttai' => '', 'phinloc' => ''],
    ]],
];

echo "=== DỮ LIỆU TRƯỚC KHI GOM ===\n";
foreach ($data as $dept) {
    echo "- {$dept['name']}: " . count($dept['spokenlg']) . " nhân viên\n";
}
echo "\n";

// ===== GOM CÁC PHÒNG BAN THEO YÊU CẦU =====
$deptGroupMap = [
    'Xưởng SC và CC ĐVL' => 'Xưởng sửa chữa thiết bị ĐVL',
    'Xưởng SC cơ khí chuyên dụng' => 'Xưởng sửa chữa thiết bị ĐVL',
    'Đội carota tổng hợp' => 'Đội Địa vật lý tổng hợp',
    'Đội công nghệ cao' => 'Đội Địa vật lý tổng hợp',
];

$deptEmployees = [];

foreach ($data as $dept) {
    $deptName = trim($dept['name']);
    
    // Tìm target name
    $targetName = $deptName;
    foreach ($deptGroupMap as $key => $value) {
        if (trim($key) === $deptName) {
            $targetName = $value;
            echo ">>> Gom '{$deptName}' vào '{$targetName}'\n";
            break;
        }
    }
    
    if (!isset($deptEmployees[$targetName])) {
        $deptEmployees[$targetName] = [];
    }
    
    // Merge nhân viên
    foreach ($dept['spokenlg'] as $emp) {
        $manv = $emp['manv'];
        
        $found = false;
        foreach ($deptEmployees[$targetName] as &$existingEmp) {
            if ($existingEmp['manv'] === $manv) {
                foreach (['giaybh', 'mubh', 'quanao', 'kinh', 'aomua', 'nuttai', 'phinloc'] as $key) {
                    if ($emp[$key] !== '') {
                        $existingEmp[$key] = ($existingEmp[$key] === '') ? $emp[$key] : ($existingEmp[$key] + $emp[$key]);
                    }
                }
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $deptEmployees[$targetName][] = $emp;
        }
    }
}

// Tạo lại mảng $data
$data = [];
foreach ($deptEmployees as $targetName => $employees) {
    if (count($employees) > 0) {
        $data[] = ['name' => $targetName, 'spokenlg' => $employees];
    }
}

echo "\n=== DỮ LIỆU SAU KHI GOM ===\n";
foreach ($data as $dept) {
    echo "- {$dept['name']}: " . count($dept['spokenlg']) . " nhân viên\n";
    foreach ($dept['spokenlg'] as $emp) {
        echo "  + {$emp['manv']}: {$emp['tennhanvien']}\n";
    }
}
?>
