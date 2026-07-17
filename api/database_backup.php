<?php
ob_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    sendError('Method không được hỗ trợ', 405);
}

@set_time_limit(0);
@ini_set('memory_limit', '256M');

$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';
$metaOnly = isset($_GET['meta_only']) && $_GET['meta_only'] === '1';
$singleTable = isset($_GET['table']) ? trim((string)$_GET['table']) : '';
$maxTables = isset($_GET['max_tables']) ? max(0, (int)$_GET['max_tables']) : 0;
$maxRows = isset($_GET['max_rows']) ? max(0, (int)$_GET['max_rows']) : 0;
$startedAt = microtime(true);
$debugSteps = [];

function sqlValue($conn, $value) {
    if ($value === null) {
        return 'NULL';
    }

    if (is_numeric($value) && !preg_match('/^0[0-9]+$/', (string)$value)) {
        return (string)$value;
    }

    return "'" . mysqli_real_escape_string($conn, (string)$value) . "'";
}

function sqlIdentifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

function streamLine($line = '') {
    echo $line . "\n";
}

function flushBackupOutput() {
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

function addDebugStep(&$steps, $message, $extra = []) {
    $steps[] = [
        'time' => date('H:i:s'),
        'message' => $message,
        'extra' => $extra,
    ];
}

try {
    $filename = 'bhld_backup_' . date('Ymd_His') . '.sql';

    if ($debugMode) {
        header_remove('Content-Disposition');
        header('Content-Type: application/json; charset=UTF-8');
        addDebugStep($debugSteps, 'Bắt đầu debug backup', [
            'table' => $singleTable,
            'max_tables' => $maxTables,
            'max_rows' => $maxRows,
            'meta_only' => $metaOnly,
        ]);
    }

    if (!$debugMode) {
        header_remove('Content-Type');
        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    if (!$debugMode) {
        streamLine('-- ================================================================');
        streamLine('-- BHLD Database Backup');
        streamLine('-- Generated at: ' . date('Y-m-d H:i:s'));
        streamLine('-- ================================================================');
        streamLine('SET NAMES utf8mb4;');
        streamLine('SET FOREIGN_KEY_CHECKS = 0;');
        streamLine('');
    }

    $tables = [];
    $sqlObjects = "SHOW FULL TABLES LIKE 'bhld\\_%'";
    $rsObjects = mysqli_query($conn, $sqlObjects);
    if (!$rsObjects) {
        throw new Exception('Không đọc được danh sách object: ' . mysqli_error($conn));
    }
    addDebugStep($debugSteps, 'Đã lấy danh sách object');

    while ($row = mysqli_fetch_assoc($rsObjects)) {
        $values = array_values($row);
        $name = isset($values[0]) ? (string)$values[0] : '';
        $type = isset($values[1]) ? strtoupper((string)$values[1]) : 'BASE TABLE';
        if ($name === '') {
            continue;
        }

        if ($type === 'BASE TABLE') {
            $tables[] = $name;
        }
    }

    sort($tables);

    if ($singleTable !== '') {
        $tables = array_values(array_filter($tables, function ($table) use ($singleTable) {
            return $table === $singleTable;
        }));
    }

    if ($maxTables > 0) {
        $tables = array_slice($tables, 0, $maxTables);
    }

    addDebugStep($debugSteps, 'Danh sách bảng sẽ xử lý', [
        'count' => count($tables),
        'tables' => $debugMode ? $tables : [],
    ]);

    if ($debugMode && empty($tables)) {
        sendSuccess([
            'debug' => true,
            'steps' => $debugSteps,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ], 'Không có bảng nào phù hợp để backup');
    }

    foreach ($tables as $table) {
        $tableEsc = sqlIdentifier($table);
        addDebugStep($debugSteps, 'Bắt đầu xử lý bảng', ['table' => $table]);

        if (!$debugMode) {
            streamLine('-- ---------------------------------------------------------------');
            streamLine('-- Table: ' . $table);
            streamLine('-- ---------------------------------------------------------------');
        }

        $rsCreate = mysqli_query($conn, 'SHOW CREATE TABLE ' . $tableEsc);
        if (!$rsCreate) {
            throw new Exception('Lỗi SHOW CREATE TABLE ' . $table . ': ' . mysqli_error($conn));
        }
        $createRow = mysqli_fetch_assoc($rsCreate);
        $createSql = isset($createRow['Create Table']) ? $createRow['Create Table'] : '';
        addDebugStep($debugSteps, 'Đã lấy CREATE TABLE', ['table' => $table]);

        if (!$debugMode) {
            streamLine('DROP TABLE IF EXISTS ' . $tableEsc . ';');
            streamLine($createSql . ';');
            streamLine('');
        }

        if ($metaOnly) {
            $countRs = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM ' . $tableEsc);
            if (!$countRs) {
                throw new Exception('Lỗi COUNT dữ liệu bảng ' . $table . ': ' . mysqli_error($conn));
            }
            $countRow = mysqli_fetch_assoc($countRs);
            addDebugStep($debugSteps, 'Đã đếm số dòng', [
                'table' => $table,
                'rows' => isset($countRow['total']) ? (int) $countRow['total'] : null,
            ]);
            continue;
        }

        $rsData = mysqli_query($conn, 'SELECT * FROM ' . $tableEsc, MYSQLI_USE_RESULT);
        if (!$rsData) {
            throw new Exception('Lỗi SELECT dữ liệu bảng ' . $table . ': ' . mysqli_error($conn));
        }
        addDebugStep($debugSteps, 'Đã mở luồng đọc dữ liệu', ['table' => $table]);

        $fields = [];
        while ($f = mysqli_fetch_field($rsData)) {
            $fields[] = sqlIdentifier($f->name);
        }

        $columnsSql = implode(', ', $fields);
        $rowCount = 0;
        while ($r = mysqli_fetch_assoc($rsData)) {
            $vals = [];
            foreach ($r as $v) {
                $vals[] = sqlValue($conn, $v);
            }
            if (!$debugMode) {
                streamLine('INSERT INTO ' . $tableEsc . ' (' . $columnsSql . ') VALUES (' . implode(', ', $vals) . ');');
            }
            $rowCount++;

            if (($rowCount % 200) === 0) {
                addDebugStep($debugSteps, 'Đã xử lý lô dữ liệu', ['table' => $table, 'rows' => $rowCount]);
                if (!$debugMode) {
                    flushBackupOutput();
                }
            }

            if ($debugMode && $maxRows > 0 && $rowCount >= $maxRows) {
                addDebugStep($debugSteps, 'Dừng sớm theo max_rows', ['table' => $table, 'rows' => $rowCount]);
                break;
            }
        }

        if (!$debugMode) {
            if ($rowCount > 0) {
                streamLine('-- Data rows: ' . $rowCount);
            } else {
                streamLine('-- No data');
            }
        }
        addDebugStep($debugSteps, 'Hoàn tất bảng', ['table' => $table, 'rows' => $rowCount]);

        mysqli_free_result($rsData);

        if (!$debugMode) {
            streamLine('');
            flushBackupOutput();
        }
    }

    if ($debugMode) {
        sendSuccess([
            'debug' => true,
            'tables' => $tables,
            'steps' => $debugSteps,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ], 'Debug backup hoàn tất');
    }

    streamLine('-- Note: backup omits VIEW and TRIGGER definitions for compatibility on shared hosting.');
    streamLine('');

    streamLine('SET FOREIGN_KEY_CHECKS = 1;');
    streamLine('-- End of backup');
    ob_end_flush();
} catch (Exception $e) {
    ob_end_clean();
    header_remove('Content-Disposition');
    header_remove('Content-Type');
    header('Content-Type: application/json; charset=UTF-8');
    if ($debugMode) {
        addDebugStep($debugSteps, 'Phát sinh lỗi', ['error' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi tạo backup: ' . $e->getMessage(),
            'data' => [
                'debug' => true,
                'steps' => $debugSteps,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    sendError('Lỗi tạo backup: ' . $e->getMessage(), 500);
}
