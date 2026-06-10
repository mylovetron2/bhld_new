<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method không được hỗ trợ', 405);
}

@set_time_limit(0);

function sqlValue($conn, $value) {
    if ($value === null) {
        return 'NULL';
    }

    if (is_numeric($value) && !preg_match('/^0[0-9]+$/', (string)$value)) {
        return (string)$value;
    }

    return "'" . mysqli_real_escape_string($conn, (string)$value) . "'";
}

function streamLine($line = '') {
    echo $line . "\n";
}

try {
    $filename = 'bhld_backup_' . date('Ymd_His') . '.sql';

    header_remove('Content-Type');
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    streamLine('-- ================================================================');
    streamLine('-- BHLD Database Backup');
    streamLine('-- Generated at: ' . date('Y-m-d H:i:s'));
    streamLine('-- ================================================================');
    streamLine('SET NAMES utf8mb4;');
    streamLine('SET FOREIGN_KEY_CHECKS = 0;');
    streamLine('');

    $tables = [];
    $sqlTables = "SHOW TABLES LIKE 'bhld\\_%'";
    $rsTables = mysqli_query($conn, $sqlTables);
    if (!$rsTables) {
        throw new Exception('Không đọc được danh sách bảng: ' . mysqli_error($conn));
    }

    while ($row = mysqli_fetch_row($rsTables)) {
        if (!empty($row[0])) {
            $tables[] = $row[0];
        }
    }

    sort($tables);

    foreach ($tables as $table) {
        $tableEsc = '`' . str_replace('`', '``', $table) . '`';

        streamLine('-- ---------------------------------------------------------------');
        streamLine('-- Table: ' . $table);
        streamLine('-- ---------------------------------------------------------------');

        $rsCreate = mysqli_query($conn, 'SHOW CREATE TABLE ' . $tableEsc);
        if (!$rsCreate) {
            throw new Exception('Lỗi SHOW CREATE TABLE ' . $table . ': ' . mysqli_error($conn));
        }
        $createRow = mysqli_fetch_assoc($rsCreate);
        $createSql = $createRow['Create Table'] ?? '';

        streamLine('DROP TABLE IF EXISTS ' . $tableEsc . ';');
        streamLine($createSql . ';');
        streamLine('');

        $rsData = mysqli_query($conn, 'SELECT * FROM ' . $tableEsc);
        if (!$rsData) {
            throw new Exception('Lỗi SELECT dữ liệu bảng ' . $table . ': ' . mysqli_error($conn));
        }

        $numRows = mysqli_num_rows($rsData);
        if ($numRows > 0) {
            $fields = [];
            while ($f = mysqli_fetch_field($rsData)) {
                $fields[] = '`' . str_replace('`', '``', $f->name) . '`';
            }

            $columnsSql = implode(', ', $fields);
            streamLine('-- Data rows: ' . $numRows);

            mysqli_data_seek($rsData, 0);
            while ($r = mysqli_fetch_assoc($rsData)) {
                $vals = [];
                foreach ($r as $v) {
                    $vals[] = sqlValue($conn, $v);
                }
                streamLine('INSERT INTO ' . $tableEsc . ' (' . $columnsSql . ') VALUES (' . implode(', ', $vals) . ');');
            }
        } else {
            streamLine('-- No data');
        }

        streamLine('');
    }

    $rsTriggers = mysqli_query($conn, 'SHOW TRIGGERS');
    if ($rsTriggers) {
        $triggerNames = [];
        while ($tr = mysqli_fetch_assoc($rsTriggers)) {
            $tbl = $tr['Table'] ?? '';
            if (strpos($tbl, 'bhld_') === 0) {
                $triggerNames[] = $tr['Trigger'];
            }
        }

        if (!empty($triggerNames)) {
            streamLine('-- ---------------------------------------------------------------');
            streamLine('-- Triggers');
            streamLine('-- ---------------------------------------------------------------');
            streamLine('DELIMITER $$');

            foreach ($triggerNames as $name) {
                $triggerEsc = '`' . str_replace('`', '``', $name) . '`';
                $rsTr = mysqli_query($conn, 'SHOW CREATE TRIGGER ' . $triggerEsc);
                if ($rsTr && ($trRow = mysqli_fetch_assoc($rsTr))) {
                    $createTrigger = $trRow['SQL Original Statement'] ?? $trRow['Create Trigger'] ?? '';
                    if ($createTrigger !== '') {
                        streamLine('DROP TRIGGER IF EXISTS ' . $triggerEsc . '$$');
                        streamLine($createTrigger . '$$');
                        streamLine('');
                    }
                }
            }

            streamLine('DELIMITER ;');
            streamLine('');
        }
    }

    streamLine('SET FOREIGN_KEY_CHECKS = 1;');
    streamLine('-- End of backup');
} catch (Exception $e) {
    header_remove('Content-Disposition');
    header_remove('Content-Type');
    header('Content-Type: application/json; charset=UTF-8');
    sendError('Lỗi tạo backup: ' . $e->getMessage(), 500);
}
