-- =====================================================
-- Script kiểm tra và thêm dữ liệu mẫu cho BHLD
-- =====================================================

-- 1. KIỂM TRA CẤU TRÚC BẢNG
SHOW TABLES LIKE 'bhld_%';

-- 2. KIỂM TRA DỮ LIỆU HIỆN TẠI
SELECT COUNT(*) as 'Số NV' FROM bhld_nhanvien;
SELECT COUNT(*) as 'Số Phòng Ban' FROM bhld_phongban;
SELECT COUNT(*) as 'Số Vật Tư' FROM bhld_dmvattu;
SELECT COUNT(*) as 'Số Chứng Từ' FROM bhld_ctu;

-- 3. XEM CẤU TRÚC BẢNG NHÂN VIÊN
DESCRIBE bhld_nhanvien;

-- 4. XEM MẪU DỮ LIỆU (NẾU CÓ)
SELECT * FROM bhld_nhanvien LIMIT 5;
SELECT * FROM bhld_phongban LIMIT 5;

-- =====================================================
-- THÊM DỮ LIỆU MẪU (NẾU BẢNG RỖNG)
-- =====================================================

-- Thêm phòng ban mẫu (nếu chưa có)
-- INSERT INTO bhld_phongban (mapb, tenphong) VALUES
-- ('PB01', 'Phòng Kỹ Thuật'),
-- ('PB02', 'Phòng Sản Xuất'),
-- ('PB03', 'Phòng Kinh Doanh')
-- ON DUPLICATE KEY UPDATE tenphong = VALUES(tenphong);

-- Thêm nhân viên mẫu (cấu trúc: manv, tennhanvien, mapb, dinhmuc)
INSERT INTO bhld_nhanvien (manv, tennhanvien, mapb, dinhmuc) VALUES
('17542', 'Nguyễn Văn A', 'PB01', 'DM001'),
('17543', 'Trần Thị B', 'PB01', 'DM001'),
('17544', 'Lê Văn C', 'PB02', 'DM002'),
('17545', 'Phạm Thị D', 'PB02', 'DM002'),
('17546', 'Hoàng Văn E', 'PB03', 'DM001')
ON DUPLICATE KEY UPDATE tennhanvien = VALUES(tennhanvien), mapb = VALUES(mapb);

-- Thêm vật tư mẫu
INSERT INTO bhld_dmvattu (mavt, tenvt, dvt, ghichu) VALUES
(101, 'Khẩu trang N95', 'cái', 'Định mức: 3 tháng'),
(102, 'Găng tay bảo hộ', 'đôi', 'Định mức: 6 tháng'),
(103, 'Giày bảo hộ', 'đôi', 'Định mức: 12 tháng'),
(104, 'Áo phản quang', 'cái', 'Định mức: 12 tháng'),
(105, 'Mũ bảo hộ', 'cái', 'Định mức: 12 tháng')
ON DUPLICATE KEY UPDATE tenvt = VALUES(tenvt);

-- =====================================================
-- KIỂM TRA LẠI SAU KHI THÊM
-- =====================================================
SELECT 'Nhân viên' as Bảng, COUNT(*) as Số_lượng FROM bhld_nhanvien
UNION ALL
SELECT 'Phòng ban', COUNT(*) FROM bhld_phongban
UNION ALL
SELECT 'Vật tư', COUNT(*) FROM bhld_dmvattu
UNION ALL
SELECT 'Chứng từ', COUNT(*) FROM bhld_ctu;

-- Xem danh sách nhân viên đầy đủ
SELECT 
    nv.manv,
    nv.tennhanvien,
    nv.mapb,
    nv.dinhmuc,
    pb.tenphong as tenphongban
FROM bhld_nhanvien nv
LEFT JOIN bhld_phongban pb ON nv.mapb = pb.mapb
ORDER BY nv.tennhanvien;
