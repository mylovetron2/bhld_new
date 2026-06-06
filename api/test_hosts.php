<?php
header('Content-Type: application/json; charset=UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$user = 'diavatly_ltd';
$pass = '12345678';
$db   = 'diavatly_ltd';

$hosts = ['localhost', '127.0.0.1', 'diavatly.com', 'mysql.diavatly.com'];
$results = [];

foreach ($hosts as $h) {
    $c = mysqli_connect($h, $user, $pass, $db, 3306);
    if ($c) {
        $results[$h] = '✅ THÀNH CÔNG - ' . mysqli_get_server_info($c);
        mysqli_close($c);
    } else {
        $results[$h] = '❌ ' . mysqli_connect_error();
    }
}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
