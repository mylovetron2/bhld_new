<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');
$data = json_decode($input, true);

echo json_encode([
    'success' => true,
    'method' => $method,
    'raw_input' => $input,
    'parsed_data' => $data,
    'post' => $_POST,
    'get' => $_GET,
    'server' => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'],
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
        'PHP_SELF' => $_SERVER['PHP_SELF']
    ]
], JSON_PRETTY_PRINT);
?>
