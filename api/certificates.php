<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Get single certificate by mact
        if (isset($_GET['mact'])) {
            $mact = mysqli_real_escape_string($conn, $_GET['mact']);
            
            $sql = "SELECT 
                        ct.mact,
                        ct.ngct,
                        ct.mapb,
                        ct.manv,
                        ct.ghichu,
                        ct.madm,
                        nv.tennhanvien,
                        pb.tenphong as tenphongban
                    FROM bhld_ctu ct
                    LEFT JOIN bhld_nhanvien nv ON ct.manv = nv.manv
                    LEFT JOIN bhld_phongban pb ON ct.mapb = pb.mapb
                    WHERE ct.mact = '$mact'
                    LIMIT 1";
            
            $result = mysqli_query($conn, $sql);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $certificate = mysqli_fetch_assoc($result);
                sendSuccess($certificate, 'Lấy thông tin chứng từ thành công');
            } else {
                sendError('Không tìm thấy chứng từ', 404);
            }
        }
        // Get list of certificates with filters
        else {
            $manv = isset($_GET['manv']) ? mysqli_real_escape_string($conn, $_GET['manv']) : '';
            $emp_name_search = isset($_GET['emp_name_search']) ? mysqli_real_escape_string($conn, $_GET['emp_name_search']) : '';
            $mact_search = isset($_GET['mact_search']) ? mysqli_real_escape_string($conn, $_GET['mact_search']) : '';
            $from_date = isset($_GET['from_date']) ? mysqli_real_escape_string($conn, $_GET['from_date']) : '';
            $to_date = isset($_GET['to_date']) ? mysqli_real_escape_string($conn, $_GET['to_date']) : '';
            
            $sql = "SELECT 
                        ct.mact,
                        ct.ngct,
                        ct.mapb,
                        ct.manv,
                        ct.ghichu,
                        ct.madm,
                        nv.tennhanvien,
                        pb.tenphong as tenphongban
                    FROM bhld_ctu ct
                    LEFT JOIN bhld_nhanvien nv ON ct.manv = nv.manv
                    LEFT JOIN bhld_phongban pb ON ct.mapb = pb.mapb
                    WHERE 1=1";
            
            if (!empty($manv)) {
                $sql .= " AND ct.manv = '$manv'";
            }
            if (!empty($emp_name_search)) {
                $sql .= " AND nv.tennhanvien LIKE '%$emp_name_search%'";
            }
            if (!empty($mact_search)) {
                $sql .= " AND ct.mact LIKE '%$mact_search%'";
                // Khi search mã CT, bỏ qua filter ngày
                $from_date = '';
                $to_date = '';
            }
            if (!empty($from_date)) {
                $sql .= " AND ct.ngct >= '$from_date'";
            }
            if (!empty($to_date)) {
                $sql .= " AND ct.ngct <= '$to_date'";
            }
            
            // Get limit from query param, default 1000 (hoặc không giới hạn nếu limit=0)
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 1000;
            
            $sql .= " ORDER BY ct.ngct DESC";
            
            if ($limit > 0) {
                $sql .= " LIMIT $limit";
            }
            
            $result = mysqli_query($conn, $sql);
            $certificates = [];
            
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $certificates[] = $row;
                }
            }
            
            sendSuccess($certificates, 'Lấy danh sách chứng từ thành công');
        }
    } 
    else if ($method === 'POST') {
        // Create new certificate
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['mact']) || !isset($data['manv']) || !isset($data['ngct']) || !isset($data['mapb']) || !isset($data['madm'])) {
            sendError('Thiếu thông tin bắt buộc');
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        $manv = mysqli_real_escape_string($conn, $data['manv']);
        $ngct = mysqli_real_escape_string($conn, $data['ngct']);
        $mapb = mysqli_real_escape_string($conn, $data['mapb']);
        $madm = mysqli_real_escape_string($conn, $data['madm']);
        $ghichu = isset($data['ghichu']) ? mysqli_real_escape_string($conn, $data['ghichu']) : '';
        
        $sql = "INSERT INTO bhld_ctu (mact, manv, ngct, mapb, madm, ghichu) 
                VALUES ('$mact', '$manv', '$ngct', '$mapb', '$madm', '$ghichu')";
        
        if (mysqli_query($conn, $sql)) {
            sendSuccess(['mact' => $mact], 'Tạo chứng từ thành công');
        } else {
            sendError('Lỗi tạo chứng từ: ' . mysqli_error($conn));
        }
    }
    else if ($method === 'PUT') {
        // Update certificate
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['mact'])) {
            sendError('Thiếu mã chứng từ');
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        
        // Check if certificate exists
        $check = mysqli_query($conn, "SELECT mact FROM bhld_ctu WHERE mact = '$mact'");
        if (mysqli_num_rows($check) === 0) {
            sendError('Không tìm thấy chứng từ', 404);
        }
        
        $updates = [];
        if (isset($data['manv'])) {
            $manv = mysqli_real_escape_string($conn, $data['manv']);
            $updates[] = "manv = '$manv'";
        }
        if (isset($data['ngct'])) {
            $ngct = mysqli_real_escape_string($conn, $data['ngct']);
            $updates[] = "ngct = '$ngct'";
        }
        if (isset($data['mapb'])) {
            $mapb = mysqli_real_escape_string($conn, $data['mapb']);
            $updates[] = "mapb = '$mapb'";
        }
        if (isset($data['madm'])) {
            $madm = mysqli_real_escape_string($conn, $data['madm']);
            $updates[] = "madm = '$madm'";
        }
        if (isset($data['ghichu'])) {
            $ghichu = mysqli_real_escape_string($conn, $data['ghichu']);
            $updates[] = "ghichu = '$ghichu'";
        }
        
        if (empty($updates)) {
            sendError('Không có thông tin cần cập nhật');
        }
        
        $sql = "UPDATE bhld_ctu SET " . implode(', ', $updates) . " WHERE mact = '$mact'";
        
        if (mysqli_query($conn, $sql)) {
            sendSuccess(['mact' => $mact], 'Cập nhật chứng từ thành công');
        } else {
            sendError('Lỗi cập nhật chứng từ: ' . mysqli_error($conn));
        }
    }
    else if ($method === 'DELETE') {
        // Delete certificate
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['mact'])) {
            sendError('Thiếu mã chứng từ');
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        
        // Check if certificate exists
        $check = mysqli_query($conn, "SELECT mact FROM bhld_ctu WHERE mact = '$mact'");
        if (mysqli_num_rows($check) === 0) {
            sendError('Không tìm thấy chứng từ', 404);
        }
        
        // Delete related details first (if any)
        mysqli_query($conn, "DELETE FROM bhld_ctctu WHERE mact = '$mact'");
        
        // Delete the certificate
        $sql = "DELETE FROM bhld_ctu WHERE mact = '$mact'";
        
        if (mysqli_query($conn, $sql)) {
            sendSuccess(['mact' => $mact], 'Xóa chứng từ thành công');
        } else {
            sendError('Lỗi xóa chứng từ: ' . mysqli_error($conn));
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
