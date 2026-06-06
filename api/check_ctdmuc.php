<?php
require_once 'config.php';
// Check bảng bhld_ctdmuc
$tables = ['bhld_ctdmuc', 'bhld_ct_dmuc', 'bhld_dmuc_ct', 'bhld_ctdm', 'bhld_dmvattu_ct'];
foreach ($tables as $t) {
    $r = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM `$t`");
    if ($r) {
        $row = mysqli_fetch_assoc($r);
        echo "$t: {$row['cnt']} rows<br>";
    } else {
        echo "$t: NOT FOUND<br>";
    }
}
// Show all bhld_ tables
$r2 = mysqli_query($conn, "SHOW TABLES LIKE 'bhld_%'");
echo "<br>All bhld_ tables:<br>";
while ($row = mysqli_fetch_row($r2)) echo $row[0] . "<br>";
?>
