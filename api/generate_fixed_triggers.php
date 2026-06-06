<?php
header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

echo "-- ===============================================\n";
echo "-- FIX TRIGGERS: MySQL 5.6 Compatible\n";
echo "-- Thay thế JSON_OBJECT() bằng CONCAT()\n";
echo "-- Cấu trúc bảng: mact, mavt, ngnhan, sl, ngnhantt, dmtg\n";
echo "-- ===============================================\n\n";

try {
    // Get current trigger definitions
    $query = "SHOW TRIGGERS WHERE `Table` = 'bhld_ctctu'";
    $result = $conn->query($query);
    
    $triggers = [];
    while ($row = $result->fetch_assoc()) {
        $triggers[$row['Trigger']] = [
            'timing' => $row['Timing'],
            'event' => $row['Event'],
            'statement' => $row['Statement']
        ];
    }
    
    echo "-- Step 1: Drop existing triggers\n";
    foreach ($triggers as $name => $info) {
        echo "DROP TRIGGER IF EXISTS `$name`;\n";
    }
    
    echo "\n-- Step 2: Create MySQL 5.6 compatible triggers\n\n";
    
    // Generate fixed version for AFTER INSERT
    if (isset($triggers['bhld_ctctu_after_insert'])) {
        echo "-- ===== bhld_ctctu_after_insert (AFTER INSERT) =====\n";
        echo "DELIMITER $$\n\n";
        echo "CREATE TRIGGER `bhld_ctctu_after_insert`\n";
        echo "AFTER INSERT ON `bhld_ctctu`\n";
        echo "FOR EACH ROW\n";
        echo "BEGIN\n";
        echo "    -- MySQL 5.6 compatible: Use CONCAT instead of JSON_OBJECT\n";
        echo "    -- Record ID: mact-mavt (composite key)\n";
        echo "    INSERT INTO bhld_audit_log (table_name, record_id, action, old_data, new_data, user_id, created_at)\n";
        echo "    VALUES (\n";
        echo "        'bhld_ctctu',\n";
        echo "        CONCAT(NEW.mact, '-', NEW.mavt),\n";
        echo "        'INSERT',\n";
        echo "        NULL,\n";
        echo "        CONCAT(\n";
        echo "            '{',\n";
        echo "            '\"mact\":\"', COALESCE(NEW.mact, ''), '\",',\n";
        echo "            '\"mavt\":', COALESCE(NEW.mavt, 'null'), ',',\n";
        echo "            '\"ngnhan\":\"', COALESCE(DATE_FORMAT(NEW.ngnhan, '%Y-%m-%d'), ''), '\",',\n";
        echo "            '\"sl\":', COALESCE(NEW.sl, 0), ',',\n";
        echo "            '\"ngnhantt\":\"', COALESCE(DATE_FORMAT(NEW.ngnhantt, '%Y-%m-%d'), ''), '\",',\n";
        echo "            '\"dmtg\":', COALESCE(NEW.dmtg, 0),\n";
        echo "            '}'\n";
        echo "        ),\n";
        echo "        @current_user_id,\n";
        echo "        NOW()\n";
        echo "    );\n";
        echo "END$$\n\n";
        echo "DELIMITER ;\n\n";
    }
    
    // Generate fixed version for AFTER UPDATE
    if (isset($triggers['bhld_ctctu_after_update'])) {
        echo "-- ===== bhld_ctctu_after_update (AFTER UPDATE) =====\n";
        echo "DELIMITER $$\n\n";
        echo "CREATE TRIGGER `bhld_ctctu_after_update`\n";
        echo "AFTER UPDATE ON `bhld_ctctu`\n";
        echo "FOR EACH ROW\n";
        echo "BEGIN\n";
        echo "    -- MySQL 5.6 compatible: Use CONCAT instead of JSON_OBJECT\n";
        echo "    -- Record ID: mact-mavt (composite key)\n";
        echo "    INSERT INTO bhld_audit_log (table_name, record_id, action, old_data, new_data, user_id, created_at)\n";
        echo "    VALUES (\n";
        echo "        'bhld_ctctu',\n";
        echo "        CONCAT(NEW.mact, '-', NEW.mavt),\n";
        echo "        'UPDATE',\n";
        echo "        CONCAT(\n";
        echo "            '{',\n";
        echo "            '\"mact\":\"', COALESCE(OLD.mact, ''), '\",',\n";
        echo "            '\"mavt\":', COALESCE(OLD.mavt, 'null'), ',',\n";
        echo "            '\"ngnhan\":\"', COALESCE(DATE_FORMAT(OLD.ngnhan, '%Y-%m-%d'), ''), '\",',\n";
        echo "            '\"sl\":', COALESCE(OLD.sl, 0), ',',\n";
        echo "            '\"ngnhantt\":\"', COALESCE(DATE_FORMAT(OLD.ngnhantt, '%Y-%m-%d'), ''), '\",',\n";
        echo "            '\"dmtg\":', COALESCE(OLD.dmtg, 0),\n";
        echo "            '}'\n";
        echo "        ),\n";
        echo "        CONCAT(\n";
        echo "            '{',\n";
        echo "            '\"mact\":\"', COALESCE(NEW.mact, ''), '\",',\n";
        echo "            '\"mavt\":', COALESCE(NEW.mavt, 'null'), ',',\n";
        echo "            '\"ngnhan\":\"', COALESCE(DATE_FORMAT(NEW.ngnhan, '%Y-%m-%d'), ''), '\",',\n";
        echo "            '\"sl\":', COALESCE(NEW.sl, 0), ',',\n";
        echo "            '\"ngnhantt\":\"', COALESCE(DATE_FORMAT(NEW.ngnhantt, '%Y-%m-%d'), ''), '\",',\n";
        echo "            '\"dmtg\":', COALESCE(NEW.dmtg, 0),\n";
        echo "            '}'\n";
        echo "        ),\n";
        echo "        @current_user_id,\n";
        echo "        NOW()\n";
        echo "    );\n";
        echo "END$$\n\n";
        echo "DELIMITER ;\n\n";
    }
    
    // Generate fixed version for BEFORE DELETE
    if (isset($triggers['bhld_ctctu_before_delete'])) {
        echo "-- ===== bhld_ctctu_before_delete (BEFORE DELETE) =====\n";
        echo "DELIMITER $$\n\n";
        echo "CREATE TRIGGER `bhld_ctctu_before_delete`\n";
        echo "BEFORE DELETE ON `bhld_ctctu`\n";
        echo "FOR EACH ROW\n";
        echo "BEGIN\n";
        echo "    -- MySQL 5.6 compatible: Use CONCAT instead of JSON_OBJECT\n";
        echo "    -- Record ID: mact-mavt (composite key)\n";
        echo "    INSERT INTO bhld_audit_log (table_name, record_id, action, old_data, new_data, user_id, created_at)\n";
        echo "    VALUES (\n";
        echo "        'bhld_ctctu',\n";
        echo "        CONCAT(OLD.mact, '-', OLD.mavt),\n";
        echo "        'DELETE',\n";
        echo "        CONCAT(\n";
        echo "            '{',\n";
        echo "            '\"mact\":\"', COALESCE(OLD.mact, ''), '\",',\n";
        echo "            '\"mavt\":', COALESCE(OLD.mavt, 'null'), ',',\n";
        echo "            '\"ngnhan\":\"', COALESCE(DATE_FORMAT(OLD.ngnhan, '%Y-%m-%d'), ''), '\",',\n";
        echo "            '\"sl\":', COALESCE(OLD.sl, 0), ',',\n";
        echo "            '\"ngnhantt\":\"', COALESCE(DATE_FORMAT(OLD.ngnhantt, '%Y-%m-%d'), ''), '\",',\n";
        echo "            '\"dmtg\":', COALESCE(OLD.dmtg, 0),\n";
        echo "            '}'\n";
        echo "        ),\n";
        echo "        NULL,\n";
        echo "        @current_user_id,\n";
        echo "        NOW()\n";
        echo "    );\n";
        echo "END$$\n\n";
        echo "DELIMITER ;\n\n";
    }
    
    echo "-- ===============================================\n";
    echo "-- Hoàn thành! Copy toàn bộ SQL trên và:\n";
    echo "-- 1. Mở phpMyAdmin\n";
    echo "-- 2. Chọn database: bhld_database\n";
    echo "-- 3. Vào tab SQL\n";
    echo "-- 4. Paste và Execute\n";
    echo "-- ===============================================\n";
    
} catch (Exception $e) {
    echo "-- ERROR: " . $e->getMessage() . "\n";
}
