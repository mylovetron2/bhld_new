-- ============================================================
-- Tạo bảng quản lý tồn kho BHLD
-- Chạy một lần để khởi tạo cấu trúc
-- ============================================================

-- Bảng tồn kho (tổng hợp theo mã vật tư)
CREATE TABLE IF NOT EXISTS bhld_tonkho (
    mavt        INT NOT NULL PRIMARY KEY,
    so_luong_nhap      INT NOT NULL DEFAULT 0,
    so_luong_cap_phat  INT NOT NULL DEFAULT 0,
    ngay_cap_nhat      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ghi_chu     TEXT,
    CONSTRAINT fk_tonkho_mavt FOREIGN KEY (mavt) REFERENCES bhld_dmvattu(mavt)
        ON UPDATE CASCADE ON DELETE CASCADE
);

-- Bảng lịch sử nhập kho
CREATE TABLE IF NOT EXISTS bhld_nhapkho (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    mavt        INT NOT NULL,
    so_luong    INT NOT NULL,
    ngay_nhap   DATE NOT NULL,
    nguon_nhap  VARCHAR(255),
    nguoi_nhap  VARCHAR(100),
    ghi_chu     TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_nhapkho_mavt FOREIGN KEY (mavt) REFERENCES bhld_dmvattu(mavt)
        ON UPDATE CASCADE ON DELETE CASCADE
);
