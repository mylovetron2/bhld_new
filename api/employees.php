<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

function tableExists($conn, $tableName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$tableName' LIMIT 1";
    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}

function buildProfileUpdateSql($conn, $input) {
    $allowed = ['giay_size', 'giay_loai', 'quanao_size', 'mu_mau', 'ghi_chu'];
    $cols = [];
    $vals = [];
    $updates = [];

    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) {
            $cols[] = $f;
            if ($input[$f] === null || $input[$f] === '') {
                $vals[] = 'NULL';
                $updates[] = "$f = NULL";
            } else {
                $v = mysqli_real_escape_string($conn, trim((string)$input[$f]));
                $vals[] = "'$v'";
                $updates[] = "$f = '$v'";
            }
        }
    }

    return [
        'has_fields' => !empty($cols),
        'columns' => $cols,
        'values' => $vals,
        'updates' => $updates,
    ];
}

try {
    if ($method === 'GET') {
        $hasProfile = tableExists($conn, 'bhld_nhanvien_hoso');
        $profileSelect = $hasProfile
            ? ", hs.giay_size, hs.giay_loai, hs.quanao_size, hs.mu_mau, hs.ghi_chu as hoso_ghichu"
            : ", NULL as giay_size, NULL as giay_loai, NULL as quanao_size, NULL as mu_mau, NULL as hoso_ghichu";
        $profileJoin = $hasProfile
            ? " LEFT JOIN bhld_nhanvien_hoso hs ON nv.manv = hs.manv"
            : '';

        // Get single employee by manv
        if (isset($_GET['manv'])) {
            $manv = mysqli_real_escape_string($conn, $_GET['manv']);
            
            $sql = "SELECT 
                        nv.manv,
                        nv.tennhanvien,
                        nv.mapb,
                        nv.dinhmuc,
                        pb.tenphong as tenphongban
                        $profileSelect
                    FROM bhld_nhanvien nv
                    LEFT JOIN bhld_phongban pb ON nv.mapb = pb.mapb
                    $profileJoin
                    WHERE nv.manv = '$manv'
                    LIMIT 1";
            
            $result = mysqli_query($conn, $sql);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $employee = mysqli_fetch_assoc($result);
                sendSuccess($employee, 'Lấy thông tin nhân viên thành công');
            } else {
                sendError('Không tìm thấy nhân viên', 404);
            }
        }
        // Get list of employees with optional search
        else {
            $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
            
            $sql = "SELECT 
                        nv.manv,
                        nv.tennhanvien,
                        nv.mapb,
                        nv.dinhmuc,
                        pb.tenphong as tenphongban
                        $profileSelect
                    FROM bhld_nhanvien nv
                    LEFT JOIN bhld_phongban pb ON nv.mapb = pb.mapb
                    $profileJoin
                    WHERE 1=1";
            
            if (!empty($search)) {
                $sql .= " AND (nv.manv LIKE '%$search%' 
                          OR nv.tennhanvien LIKE '%$search%')";
            }
            
            $sql .= " ORDER BY nv.manv ASC";
            
            $result = mysqli_query($conn, $sql);
            $employees = [];
            
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $employees[] = $row;
                }
            }
            
            sendSuccess($employees, 'Lấy danh sách nhân viên thành công');
        }
    } 
    elseif ($method === 'POST') {
        // Add new employee
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['manv']) || !isset($input['tennhanvien']) || !isset($input['mapb'])) {
            sendError('Thiếu thông tin bắt buộc: manv, tennhanvien, mapb', 400);
        }

        $manv       = mysqli_real_escape_string($conn, trim($input['manv']));
        $tennhanvien = mysqli_real_escape_string($conn, trim($input['tennhanvien']));
        $mapb       = mysqli_real_escape_string($conn, trim($input['mapb']));
        $dinhmuc    = isset($input['dinhmuc']) ? mysqli_real_escape_string($conn, trim($input['dinhmuc'])) : null;
        $hasProfile = tableExists($conn, 'bhld_nhanvien_hoso');

        // Check duplicate manv
        $check = mysqli_query($conn, "SELECT manv FROM bhld_nhanvien WHERE manv = '$manv' LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            sendError('Mã nhân viên đã tồn tại', 409);
        }

        $dinhmucSql = $dinhmuc !== null ? "'$dinhmuc'" : 'NULL';
        $sql = "INSERT INTO bhld_nhanvien (manv, tennhanvien, mapb, dinhmuc)
                VALUES ('$manv', '$tennhanvien', '$mapb', $dinhmucSql)";

        if (mysqli_query($conn, $sql)) {
            if ($hasProfile) {
                $profileParts = buildProfileUpdateSql($conn, $input);
                if ($profileParts['has_fields']) {
                    $cols = implode(', ', array_merge(['manv'], $profileParts['columns']));
                    $vals = implode(', ', array_merge(["'$manv'"], $profileParts['values']));
                    $ups = implode(', ', $profileParts['updates']);
                    $profileSql = "INSERT INTO bhld_nhanvien_hoso ($cols) VALUES ($vals) ON DUPLICATE KEY UPDATE $ups";
                    mysqli_query($conn, $profileSql);
                }
            }

            sendSuccess(
                ['manv' => $manv, 'tennhanvien' => $tennhanvien, 'mapb' => $mapb, 'dinhmuc' => $dinhmuc],
                'Thêm nhân viên thành công'
            );
        } else {
            sendError('Lỗi thêm nhân viên: ' . mysqli_error($conn), 500);
        }
    }
    elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['manv'])) {
            sendError('Thiếu manv', 400);
        }
        
        $manv = mysqli_real_escape_string($conn, $input['manv']);
        $hasProfile = tableExists($conn, 'bhld_nhanvien_hoso');
        $sets = [];
        if (isset($input['tennhanvien'])) $sets[] = "tennhanvien = '" . mysqli_real_escape_string($conn, $input['tennhanvien']) . "'";
        if (isset($input['mapb']))        $sets[] = "mapb = '"        . mysqli_real_escape_string($conn, $input['mapb'])        . "'";
        if (array_key_exists('dinhmuc', $input)) {
            $dm = $input['dinhmuc'] !== '' && $input['dinhmuc'] !== null
                ? "'" . mysqli_real_escape_string($conn, $input['dinhmuc']) . "'"
                : 'NULL';
            $sets[] = "dinhmuc = $dm";
        }
        $profileParts = $hasProfile ? buildProfileUpdateSql($conn, $input) : ['has_fields' => false];

        if (empty($sets) && !$profileParts['has_fields']) sendError('Không có trường nào để cập nhật', 400);
        
        $ok = true;
        if (!empty($sets)) {
            $sql = "UPDATE bhld_nhanvien SET " . implode(', ', $sets) . " WHERE manv = '$manv'";
            $ok = mysqli_query($conn, $sql);
        }

        if ($ok && $profileParts['has_fields']) {
            $cols = implode(', ', array_merge(['manv'], $profileParts['columns']));
            $vals = implode(', ', array_merge(["'$manv'"], $profileParts['values']));
            $ups = implode(', ', $profileParts['updates']);
            $profileSql = "INSERT INTO bhld_nhanvien_hoso ($cols) VALUES ($vals) ON DUPLICATE KEY UPDATE $ups";
            $ok = mysqli_query($conn, $profileSql);
        }

        if ($ok) {
            sendSuccess(['manv' => $manv], 'Cập nhật nhân viên thành công');
        } else {
            sendError('Lỗi cập nhật: ' . mysqli_error($conn), 500);
        }
    }
    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['manv'])) {
            sendError('Thiếu manv', 400);
        }
        
        $manv = mysqli_real_escape_string($conn, $input['manv']);
        
        // Check if employee has certificates
        $checkCerts = mysqli_query($conn, "SELECT COUNT(*) as count FROM bhld_ctu WHERE manv = '$manv'");
        if ($checkCerts) {
            $result = mysqli_fetch_assoc($checkCerts);
            if ($result['count'] > 0) {
                sendError('Không thể xóa nhân viên này vì còn ' . $result['count'] . ' chứng từ liên quan', 400);
            }
        }
        
        $sql = "DELETE FROM bhld_nhanvien WHERE manv = '$manv'";
        
        if (mysqli_query($conn, $sql)) {
            if (mysqli_affected_rows($conn) > 0) {
                sendSuccess(['manv' => $manv], 'Xóa nhân viên thành công');
            } else {
                sendError('Không tìm thấy nhân viên', 404);
            }
        } else {
            sendError('Lỗi xóa nhân viên: ' . mysqli_error($conn), 500);
        }
    }
    else {
        sendError('Method không được hỗ trợ', 405);
    }
} catch (Exception $e) {
    sendError('Lỗi server: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
