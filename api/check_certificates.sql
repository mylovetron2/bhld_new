-- Kiểm tra dữ liệu trong bảng bhld_ctu
SELECT COUNT(*) as 'Tổng số chứng từ' FROM bhld_ctu;

-- Xem 10 chứng từ mẫu
SELECT 
    ct.mact,
    ct.ngct,
    ct.mapb,
    ct.manv,
    ct.madm,
    ct.ghichu,
    nv.tennhanvien,
    pb.tenphong as tenphongban
FROM bhld_ctu ct
LEFT JOIN bhld_nhanvien nv ON ct.manv = nv.manv
LEFT JOIN bhld_phongban pb ON ct.mapb = pb.mapb
ORDER BY ct.ngct DESC
LIMIT 10;

-- Kiểm tra cấu trúc bảng
DESCRIBE bhld_ctu;
