<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$month = '12/2025';
$manv = '21445';

$monthParts = preg_split('/[\/\-]/', $month);
$monthNum = str_pad($monthParts[0], 2, '0', STR_PAD_LEFT);
$year = $monthParts[1];

$startDate = "$year-$monthNum-01";
$endDate = date("Y-m-t", strtotime($startDate));

$standardEquipment = ['Giày', 'Mũ', 'Quần áo', 'Kính', 'Áo mưa', 'Nút tai', 'Phim'];

echo "<h3>Test Equipment Matching</h3>";
echo "<p>Standard Equipment: " . implode(', ', $standardEquipment) . "</p>";

// Lấy dữ liệu
$sql = "SELECT vt.tenvt, ctct.sl
        FROM bhld_ctctu ctct
        INNER JOIN bhld_ctu ct ON ctct.mact = ct.mact
        LEFT JOIN bhld_dmvattu vt ON ctct.mavt = vt.mavt
        WHERE ct.manv = '$manv' 
        AND ctct.ngnhan BETWEEN '$startDate' AND '$endDate' 
        AND ctct.sl > 0";

$result = mysqli_query($conn, $sql);
echo "<h4>Testing stripos matching:</h4>";
echo "<table border='1' style='border-collapse:collapse'>";
echo "<tr><th>Vật tư DB</th><th>SL</th><th>Matched?</th><th>Matched With</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    $tenvt = $row['tenvt'];
    $sl = $row['sl'];
    $matched = false;
    $matchedWith = '';
    
    foreach ($standardEquipment as $equipName) {
        if (stripos($tenvt, $equipName) !== false) {
            $matched = true;
            $matchedWith = $equipName;
            break;
        }
    }
    
    echo "<tr>";
    echo "<td>$tenvt</td>";
    echo "<td>$sl</td>";
    echo "<td style='color:" . ($matched ? "green" : "red") . "'>" . ($matched ? "YES" : "NO") . "</td>";
    echo "<td>$matchedWith</td>";
    echo "</tr>";
}
echo "</table>";
?>
