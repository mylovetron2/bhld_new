<?php
// Simple test without using config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Test 1: Basic PHP
echo json_encode(['step' => 1, 'message' => 'PHP works']);

// Test 2: Include config
try {
    require_once 'config.php';
    echo json_encode(['step' => 2, 'message' => 'Config loaded']);
} catch (Exception $e) {
    echo json_encode(['step' => 2, 'error' => $e->getMessage()]);
    exit;
}

// Test 3: Check connection
if (!isset($conn) || !$conn) {
    echo json_encode(['step' => 3, 'error' => 'No database connection']);
    exit;
}
echo json_encode(['step' => 3, 'message' => 'Database connected']);

// Test 4: Simple query
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM bhld_phongban");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo json_encode(['step' => 4, 'message' => 'Query works', 'departments' => $row['cnt']]);
} else {
    echo json_encode(['step' => 4, 'error' => mysqli_error($conn)]);
}

// Test 5: Test sendSuccess function
if (function_exists('sendSuccess')) {
    sendSuccess(['test' => 'data'], 'sendSuccess works!');
} else {
    echo json_encode(['step' => 5, 'error' => 'sendSuccess function not found']);
}
?>
