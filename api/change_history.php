<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Chỉ hỗ trợ GET');
}

$table      = isset($_GET['table'])     ? $_GET['table'] : 'bhld_ctu';
$record_id  = isset($_GET['id'])        ? trim($_GET['id']) : '';
$action     = isset($_GET['action'])    ? trim($_GET['action']) : '';
$from_date  = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date    = isset($_GET['to_date'])   ? trim($_GET['to_date']) : '';
$limit      = isset($_GET['limit'])     ? min(intval($_GET['limit']), 500) : 100;
$page       = isset($_GET['page'])      ? max(1, intval($_GET['page'])) : 1;
$offset     = ($page - 1) * $limit;

if (!in_array($table, ['bhld_ctu', 'bhld_ctctu'])) {
    sendError('Bảng không hợp lệ');
}

$history_table = $table . '_history';

// Build WHERE
$wheres = [];
if (!empty($record_id)) {
    $rid = mysqli_real_escape_string($conn, $record_id);
    if ($table === 'bhld_ctu') {
        $wheres[] = "record_id LIKE '%$rid%'";
    } else {
        $wheres[] = "record_id_mact LIKE '%$rid%'";
    }
}
if (!empty($action) && in_array($action, ['INSERT', 'UPDATE', 'DELETE'])) {
    $wheres[] = "action_type = '" . mysqli_real_escape_string($conn, $action) . "'";
}
if (!empty($from_date)) {
    $wheres[] = "action_time >= '" . mysqli_real_escape_string($conn, $from_date) . " 00:00:00'";
}
if (!empty($to_date)) {
    $wheres[] = "action_time <= '" . mysqli_real_escape_string($conn, $to_date) . " 23:59:59'";
}
$where = count($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

// Count
$count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM `$history_table` $where");
if (!$count_res) {
    sendSuccess(['data' => [], 'total' => 0, 'pages' => 0, 'table_exists' => false], 'Bảng history chưa tồn tại');
}
$total = (int)mysqli_fetch_assoc($count_res)['total'];

// Data
$sql = "SELECT * FROM `$history_table` $where ORDER BY action_time DESC LIMIT $limit OFFSET $offset";
$res = mysqli_query($conn, $sql);
$rows = [];
while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;

sendSuccess([
    'data'        => $rows,
    'total'       => $total,
    'pages'       => (int)ceil($total / $limit),
    'page'        => $page,
    'limit'       => $limit,
    'table'       => $table,
    'table_exists'=> true,
]);
