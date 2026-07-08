<?php
require 'db_connection.php';
$r = mysqli_query($conn, 'SELECT mavt, tenvt, dvt FROM bhld_dmvattu ORDER BY mavt');
while($row = mysqli_fetch_assoc($r)) {
    echo $row['mavt'] . ' | ' . $row['tenvt'] . ' | ' . $row['dvt'] . "\n";
}
