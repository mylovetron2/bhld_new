<?php
/**
 * API Thu hoi thiet bi - recall.php
 * GET  ?manv=&month=YYYY-MM           -> vat tu da cap phat (sl>0) trong thang
 * GET  ?history=1&manv=&limit=        -> lich su thu hoi
 * POST {mact, mavt, ngay_thuhoi, ly_do} -> thuc hien thu hoi + luu lich su
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') { http_response_code(200); exit; }

try {
    if ($method === 'GET') {
        // Lich su thu hoi
        if (isset($_GET['history'])) {
            $manv  = isset($_GET['manv'])  ? mysqli_real_escape_string($conn, $_GET['manv']) : '';
            $limit = isset($_GET['limit']) ? max(1, min(500, intval($_GET['limit']))) : 100;
            $where = "WHERE 1=1";
            if ($manv) $where .= " AND th.manv = '$manv'";
            $sql = "SELECT th.id, th.manv, nv.tennhanvien, th.mact, th.mavt,
                           vt.tenvt, vt.dvt, th.ngay_cap_phat, th.ngay_thuhoi, th.ly_do, th.created_at
                    FROM bhld_thuhoi th
                    LEFT JOIN bhld_nhanvien nv ON nv.manv = th.manv
                    LEFT JOIN bhld_dmvattu vt  ON vt.mavt  = th.mavt
                    $where ORDER BY th.ngay_thuhoi DESC, th.id DESC LIMIT $limit";
            $res = mysqli_query($conn, $sql);
            if (!$res) throw new Exception(mysqli_error($conn));
            $rows = [];
            while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
            sendSuccess($rows, 'Lich su thu hoi'); exit;
        }

        // Vat tu da cap phat cua NV trong thang
        $manv  = isset($_GET['manv'])  ? mysqli_real_escape_string($conn, $_GET['manv'])  : '';
        $month = isset($_GET['month']) ? mysqli_real_escape_string($conn, $_GET['month']) : date('Y-m');
        if (!$manv) sendError('Thieu manv', 400);
        $start = $month . '-01';
        $nextM = date('Y-m-d', strtotime($start . ' +1 month'));
        $sql = "SELECT ct.mact, ct.manv, ct.mapb, ct.ngct,
                       nv.tennhanvien, pb.tenphong AS tenphongban,
                       d.mavt, d.dmtg, d.sl, d.ngnhan, vt.tenvt, vt.dvt
                FROM bhld_ctu ct
                JOIN bhld_ctctu d       ON d.mact  = ct.mact
                JOIN bhld_dmvattu vt    ON vt.mavt  = d.mavt
                LEFT JOIN bhld_nhanvien nv ON nv.manv = ct.manv
                LEFT JOIN bhld_phongban pb ON pb.mapb = ct.mapb
                WHERE ct.manv = '$manv'
                  AND d.ngnhan >= '$start' AND d.ngnhan < '$nextM'
                  AND d.sl > 0 AND d.ngnhan != '1911-11-11'
                ORDER BY ct.ngct DESC, vt.tenvt ASC";
        $res = mysqli_query($conn, $sql);
        if (!$res) throw new Exception(mysqli_error($conn));
        $rows = [];
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        sendSuccess($rows, 'Danh sach da cap phat'); exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['mact'], $data['mavt'])) sendError('Thieu mact, mavt', 400);
        $mact        = mysqli_real_escape_string($conn, $data['mact']);
        $mavt        = intval($data['mavt']);
        $ngay_thuhoi = isset($data['ngay_thuhoi']) ? mysqli_real_escape_string($conn, $data['ngay_thuhoi']) : date('Y-m-d');
        $ly_do       = isset($data['ly_do']) ? mysqli_real_escape_string($conn, trim($data['ly_do'])) : '';

        $chk = mysqli_query($conn, "SELECT d.sl, d.ngnhan, d.dmtg, ct.manv, ct.mapb FROM bhld_ctctu d
                                    JOIN bhld_ctu ct ON ct.mact = d.mact
                                    WHERE d.mact = '$mact' AND d.mavt = $mavt LIMIT 1");
        if (!$chk || mysqli_num_rows($chk) === 0) sendError('Khong tim thay ban ghi', 404);
        $row = mysqli_fetch_assoc($chk);
        if (intval($row['sl']) == 0) sendError('Vat tu nay chua duoc cap phat', 400);
        $manv_owner    = mysqli_real_escape_string($conn, $row['manv']);
        $mapb_owner    = mysqli_real_escape_string($conn, $row['mapb']);
        $ngay_cap_phat = $row['ngnhan'];
        $dmtg          = intval($row['dmtg']);

        $upd = mysqli_query($conn, "UPDATE bhld_ctctu
                                    SET sl = 0, ngnhan = '1911-11-11', ngnhantt = '1911-11-11'
                                    WHERE mact = '$mact' AND mavt = $mavt AND sl > 0");
        if (!$upd) throw new Exception(mysqli_error($conn));
        if (mysqli_affected_rows($conn) === 0) sendError('Khong cap nhat duoc (da thu hoi roi?)', 400);

        mysqli_query($conn, "UPDATE bhld_tonkho
                             SET so_luong_cap_phat = GREATEST(0, so_luong_cap_phat - 1)
                             WHERE mavt = $mavt");

        // Xoa chung tu ky sau (detail + master neu rong)
        if ($dmtg > 0 && $ngay_cap_phat && $ngay_cap_phat !== '1911-11-11') {
            $ngct_next  = date('Y-m-d', strtotime($ngay_cap_phat . ' + ' . $dmtg . ' month'));
            $year_month = date('Y-m', strtotime($ngct_next));
            $manv_fmt   = (is_numeric($manv_owner) && strlen($manv_owner) == 4) ? '0' . $manv_owner : $manv_owner;
            $mact_next  = $year_month . '-' . $mapb_owner . '-' . $manv_fmt;

            // Xoa detail ky sau
            mysqli_query($conn, "DELETE FROM bhld_ctctu WHERE mact = '$mact_next' AND mavt = $mavt");

            // Neu master ky sau khong con detail nao -> xoa luon master
            $cntRes = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM bhld_ctctu WHERE mact = '$mact_next'");
            if ($cntRes) {
                $cnt = mysqli_fetch_assoc($cntRes);
                if (intval($cnt['cnt']) === 0) {
                    mysqli_query($conn, "DELETE FROM bhld_ctu WHERE mact = '$mact_next'");
                }
            }
        }

        $hasTable = mysqli_query($conn, "SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'bhld_thuhoi' LIMIT 1");
        if ($hasTable && mysqli_num_rows($hasTable) > 0) {
            $escNgayCap = mysqli_real_escape_string($conn, $ngay_cap_phat ?: '1911-11-11');
            mysqli_query($conn, "INSERT INTO bhld_thuhoi (manv, mact, mavt, ngay_cap_phat, ngay_thuhoi, ly_do)
                                 VALUES ('$manv_owner', '$mact', $mavt, '$escNgayCap', '$ngay_thuhoi', '$ly_do')");
        }
        sendSuccess(['mact' => $mact, 'mavt' => $mavt], 'Thu hoi thanh cong'); exit;
    }

    sendError('Method khong ho tro', 405);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
