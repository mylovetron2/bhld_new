<?php
require_once 'config.php';

// Cấu trúc bảng bhld_ctdmuc
echo "<b>DESCRIBE bhld_ctdmuc:</b><br>";
$r = mysqli_query($conn, "DESCRIBE bhld_ctdmuc");
while ($row = mysqli_fetch_assoc($r)) echo $row['Field'] . " (" . $row['Type'] . ")<br>";

// 5 dòng đầu
echo "<br><b>Sample data:</b><br>";
$r2 = mysqli_query($conn, "SELECT * FROM bhld_ctdmuc LIMIT 5");
while ($row = mysqli_fetch_assoc($r2)) echo json_encode($row, JSON_UNESCAPED_UNICODE) . "<br>";
?>
