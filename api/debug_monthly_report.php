<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

try {
    // Lấy tháng từ request
    $month = isset($_GET['month']) ? $_GET['month'] : '12/2025';
    $manv = isset($_GET['manv']) ? $_GET['manv'] : '21445';
    
    // Parse month
    $monthParts = preg_split('/[\/\-]/', $month);
    if (count($monthParts) == 2) {
        if (strlen($monthParts[0]) == 4) {
            $year = $monthParts[0];
            $monthNum = str_pad($monthParts[1], 2, '0', STR_PAD_LEFT);
        } else {
            $monthNum = str_pad($monthParts[0], 2, '0', STR_PAD_LEFT);
            $year = $monthParts[1];
        }
    }
    
    $startDate = "$year-$monthNum-01";
    $endDate = date("Y-m-t", strtotime($startDate));
    
    echo "<h3>Debug Monthly Report - Nhân viên $manv - Tháng $month</h3>";
    echo "<p>Start Date: $startDate | End Date: $endDate</p>";
    
    // Query 1: Kiểm tra dữ liệu với ct.ngct (ngày chứng từ)
    echo "<h4>1. Dữ liệu theo ngày chứng từ (ct.ngct):</h4>";
    $sql1 = "SELECT ct.mact, ct.ngct, vt.tenvt, ctct.sl, ctct.ngnhan
             FROM bhld_ctctu ctct
             INNER JOIN bhld_ctu ct ON ctct.mact = ct.mact
             INNER JOIN bhld_nhanvien nv ON ct.manv = nv.manv
             LEFT JOIN bhld_dmvattu vt ON ctct.mavt = vt.mavt
             WHERE ct.manv = '$manv' AND ct.ngct BETWEEN '$startDate' AND '$endDate' AND ctct.sl > 0
             ORDER BY ct.ngct";
    
    $result1 = mysqli_query($conn, $sql1);
    if ($result1 && mysqli_num_rows($result1) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Mã CT</th><th>Ngày CT</th><th>Vật tư</th><th>SL</th><th>Ngày nhận</th></tr>";
        while ($row = mysqli_fetch_assoc($result1)) {
            echo "<tr>";
            echo "<td>{$row['mact']}</td>";
            echo "<td>{$row['ngct']}</td>";
            echo "<td>{$row['tenvt']}</td>";
            echo "<td>{$row['sl']}</td>";
            echo "<td>" . ($row['ngnhan'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>Không có dữ liệu với ct.ngct</p>";
    }
    
    // Query 2: Kiểm tra dữ liệu với ctct.ngnhan (ngày nhận)
    echo "<h4>2. Dữ liệu theo ngày nhận (ctct.ngnhan):</h4>";
    $sql2 = "SELECT ct.mact, ct.ngct, vt.tenvt, ctct.sl, ctct.ngnhan
             FROM bhld_ctctu ctct
             INNER JOIN bhld_ctu ct ON ctct.mact = ct.mact
             INNER JOIN bhld_nhanvien nv ON ct.manv = nv.manv
             LEFT JOIN bhld_dmvattu vt ON ctct.mavt = vt.mavt
             WHERE ct.manv = '$manv' AND ctct.ngnhan BETWEEN '$startDate' AND '$endDate' AND ctct.sl > 0
             ORDER BY ctct.ngnhan";
    
    $result2 = mysqli_query($conn, $sql2);
    if ($result2 && mysqli_num_rows($result2) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Mã CT</th><th>Ngày CT</th><th>Vật tư</th><th>SL</th><th>Ngày nhận</th></tr>";
        while ($row = mysqli_fetch_assoc($result2)) {
            echo "<tr>";
            echo "<td>{$row['mact']}</td>";
            echo "<td>{$row['ngct']}</td>";
            echo "<td>{$row['tenvt']}</td>";
            echo "<td>{$row['sl']}</td>";
            echo "<td>" . ($row['ngnhan'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>Không có dữ liệu với ctct.ngnhan</p>";
    }
    
    // Query 3: Kiểm tra tất cả dữ liệu của nhân viên (không filter ngày)
    echo "<h4>3. Tất cả dữ liệu của nhân viên $manv (20 dòng gần nhất):</h4>";
    $sql3 = "SELECT ct.mact, ct.ngct, vt.tenvt, ctct.sl, ctct.ngnhan
             FROM bhld_ctctu ctct
             INNER JOIN bhld_ctu ct ON ctct.mact = ct.mact
             INNER JOIN bhld_nhanvien nv ON ct.manv = nv.manv
             LEFT JOIN bhld_dmvattu vt ON ctct.mavt = vt.mavt
             WHERE ct.manv = '$manv' AND ctct.sl > 0
             ORDER BY ctct.ngnhan DESC
             LIMIT 20";
    
    $result3 = mysqli_query($conn, $sql3);
    if ($result3 && mysqli_num_rows($result3) > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Mã CT</th><th>Ngày CT</th><th>Vật tư</th><th>SL</th><th>Ngày nhận</th></tr>";
        while ($row = mysqli_fetch_assoc($result3)) {
            echo "<tr>";
            echo "<td>{$row['mact']}</td>";
            echo "<td>{$row['ngct']}</td>";
            echo "<td>{$row['tenvt']}</td>";
            echo "<td>{$row['sl']}</td>";
            echo "<td>" . ($row['ngnhan'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>Không có dữ liệu</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>";
            echo "<td>{$row['sl']}</td>";
            echo "<td>" . ($row['ngnhan'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>Không có dữ liệu</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Lỗi: " . $e->getMessage() . "</p>";
}
?>

// Test 2: Check tables exist
echo "<h3>Test 2: Check Tables</h3>";
$tables = ['bhld_phongban', 'bhld_nhanvien', 'bhld_chungtu', 'bhld_chungtu_chitiet', 'bhld_dmvattu'];
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if ($result && mysqli_num_rows($result) > 0) {
        echo "✓ Table '$table' exists<br>";
    } else {
        echo "✗ Table '$table' NOT found<br>";
    }
}

// Test 3: Count records
echo "<h3>Test 3: Count Records</h3>";
$counts = [
    'Phòng ban' => 'SELECT COUNT(*) as cnt FROM bhld_phongban',
    'Nhân viên' => 'SELECT COUNT(*) as cnt FROM bhld_nhanvien',
    'Chứng từ' => 'SELECT COUNT(*) as cnt FROM bhld_chungtu',
    'Chi tiết CT' => 'SELECT COUNT(*) as cnt FROM bhld_chungtu_chitiet',
    'Vật tư' => 'SELECT COUNT(*) as cnt FROM bhld_dmvattu',
];

foreach ($counts as $label => $sql) {
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "$label: " . $row['cnt'] . "<br>";
    } else {
        echo "$label: Error - " . mysqli_error($conn) . "<br>";
    }
}

// Test 4: Sample query with date filter
echo "<h3>Test 4: Sample Query (January 2026)</h3>";
$startDate = "2026-01-01";
$endDate = "2026-01-31";

$sql = "
    SELECT 
        pb.mapb,
        pb.tenpb as tenphongban,
        nv.manv,
        nv.tennhanvien,
        COUNT(ct.mact) as num_certificates
    FROM bhld_phongban pb
    LEFT JOIN bhld_nhanvien nv ON pb.mapb = nv.mapb
    LEFT JOIN bhld_chungtu ct ON nv.manv = ct.manv 
        AND ct.ngct BETWEEN '$startDate' AND '$endDate'
    WHERE nv.manv IS NOT NULL
    GROUP BY pb.mapb, nv.manv
    LIMIT 10
";

$result = mysqli_query($conn, $sql);
if ($result) {
    echo "Records found: " . mysqli_num_rows($result) . "<br><br>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Phòng ban</th><th>Mã NV</th><th>Tên NV</th><th>Số CT</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>{$row['tenphongban']}</td>";
        echo "<td>{$row['manv']}</td>";
        echo "<td>{$row['tennhanvien']}</td>";
        echo "<td>{$row['num_certificates']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Query failed: " . mysqli_error($conn) . "<br>";
}

// Test 5: Call the actual API
echo "<h3>Test 5: Call Monthly Report API</h3>";
$apiUrl = "http://diavatly.com/BHLD/api/monthly_report.php?month=01/2026";
echo "URL: <a href='$apiUrl' target='_blank'>$apiUrl</a><br>";
echo "<button onclick=\"fetch('$apiUrl').then(r=>r.json()).then(d=>document.getElementById('result').innerHTML=JSON.stringify(d,null,2))\">Test Now</button><br>";
echo "<pre id='result' style='background:#f5f5f5;padding:10px;margin-top:10px;'></pre>";

mysqli_close($conn);
?>
