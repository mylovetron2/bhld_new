-- Migration: quy tac thuoc tinh theo ma vat tu
-- Chay sau khi backup database. File nay khong tu dong chay UPDATE.

CREATE TABLE IF NOT EXISTS bhld_vattu_thuoctinh (
    mavt INT NOT NULL PRIMARY KEY,
    cho_phep_size TINYINT(1) NOT NULL DEFAULT 0,
    cho_phep_mau TINYINT(1) NOT NULL DEFAULT 0,
    cho_phep_loai TINYINT(1) NOT NULL DEFAULT 0,
    cho_phep_quycach TINYINT(1) NOT NULL DEFAULT 0,
    ghi_chu VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vattu_thuoctinh_vattu
        FOREIGN KEY (mavt) REFERENCES bhld_dmvattu(mavt)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quy tac cho cac ma vat tu chuan cua he thong.
INSERT INTO bhld_vattu_thuoctinh
    (mavt, cho_phep_size, cho_phep_mau, cho_phep_loai, cho_phep_quycach, ghi_chu)
VALUES
    (500120, 1, 0, 1, 1, 'Giay bao ho: size va loai'),
    (500500, 0, 1, 1, 1, 'Mu bao ho: mau va loai'),
    (500860, 1, 0, 0, 1, 'Ao quan bao ho: size'),
    (501545, 0, 0, 0, 0, 'Kinh bao ho: khong quan ly thuoc tinh'),
    (501660, 0, 0, 0, 0, 'Ao bat di mua: khong quan ly thuoc tinh')
ON DUPLICATE KEY UPDATE
    cho_phep_size = VALUES(cho_phep_size),
    cho_phep_mau = VALUES(cho_phep_mau),
    cho_phep_loai = VALUES(cho_phep_loai),
    cho_phep_quycach = VALUES(cho_phep_quycach),
    ghi_chu = VALUES(ghi_chu);

-- Backup truoc khi lam sach du lieu cu.
CREATE TABLE IF NOT EXISTS bhld_ctctu_backup_thuoctinh_20260918 AS
SELECT * FROM bhld_ctctu;

-- Chuan hoa chuoi rong ve NULL.
UPDATE bhld_ctctu
SET
    size_label = NULLIF(TRIM(size_label), ''),
    mau_label = NULLIF(TRIM(mau_label), ''),
    loai_label = NULLIF(TRIM(loai_label), ''),
    quycach_label = NULLIF(TRIM(quycach_label), '');

-- Xoa cac thuoc tinh khong duoc phep theo ma vat tu.
UPDATE bhld_ctctu ct
INNER JOIN bhld_vattu_thuoctinh q ON q.mavt = ct.mavt
SET
    ct.size_label = CASE WHEN q.cho_phep_size = 1 THEN ct.size_label ELSE NULL END,
    ct.mau_label = CASE WHEN q.cho_phep_mau = 1 THEN ct.mau_label ELSE NULL END,
    ct.loai_label = CASE WHEN q.cho_phep_loai = 1 THEN ct.loai_label ELSE NULL END,
    ct.quycach_label = CASE WHEN q.cho_phep_quycach = 1 THEN ct.quycach_label ELSE NULL END;

-- Dong bo co kiem soat du lieu cu tu ho so nhan vien sang dong cap phat.
-- Chi ap dung cho thuoc tinh duoc phep; khong tao quycach_label tu dong.
UPDATE bhld_ctctu ct
INNER JOIN bhld_ctu ctu ON ctu.mact = ct.mact
INNER JOIN bhld_nhanvien_hoso hs ON hs.manv = ctu.manv
INNER JOIN bhld_vattu_thuoctinh q ON q.mavt = ct.mavt
SET ct.size_label = NULLIF(TRIM(hs.giay_size), '')
WHERE ct.mavt = 500120
    AND q.cho_phep_size = 1
    AND (ct.size_label IS NULL OR TRIM(ct.size_label) = '')
    AND hs.giay_size IS NOT NULL
    AND TRIM(hs.giay_size) <> '';

UPDATE bhld_ctctu ct
INNER JOIN bhld_ctu ctu ON ctu.mact = ct.mact
INNER JOIN bhld_nhanvien_hoso hs ON hs.manv = ctu.manv
INNER JOIN bhld_vattu_thuoctinh q ON q.mavt = ct.mavt
SET ct.size_label = NULLIF(TRIM(hs.quanao_size), '')
WHERE ct.mavt = 500860
    AND q.cho_phep_size = 1
    AND (ct.size_label IS NULL OR TRIM(ct.size_label) = '')
    AND hs.quanao_size IS NOT NULL
    AND TRIM(hs.quanao_size) <> '';

UPDATE bhld_ctctu ct
INNER JOIN bhld_ctu ctu ON ctu.mact = ct.mact
INNER JOIN bhld_nhanvien_hoso hs ON hs.manv = ctu.manv
INNER JOIN bhld_vattu_thuoctinh q ON q.mavt = ct.mavt
SET ct.mau_label = NULLIF(TRIM(hs.mu_mau), '')
WHERE ct.mavt = 500500
    AND q.cho_phep_mau = 1
    AND (ct.mau_label IS NULL OR TRIM(ct.mau_label) = '')
    AND hs.mu_mau IS NOT NULL
    AND TRIM(hs.mu_mau) <> '';

UPDATE bhld_ctctu ct
INNER JOIN bhld_ctu ctu ON ctu.mact = ct.mact
INNER JOIN bhld_nhanvien_hoso hs ON hs.manv = ctu.manv
INNER JOIN bhld_vattu_thuoctinh q ON q.mavt = ct.mavt
SET ct.loai_label = NULLIF(TRIM(hs.giay_loai), '')
WHERE ct.mavt = 500120
    AND q.cho_phep_loai = 1
    AND (ct.loai_label IS NULL OR TRIM(ct.loai_label) = '')
    AND hs.giay_loai IS NOT NULL
    AND TRIM(hs.giay_loai) <> '';

-- Kiem tra sau migration: hai ma nay phai tra ve 0 dong.
SELECT ct.mavt, vt.tenvt, COUNT(*) AS so_dong_con_thuoc_tinh
FROM bhld_ctctu ct
LEFT JOIN bhld_dmvattu vt ON vt.mavt = ct.mavt
WHERE ct.mavt IN (501545, 501660)
  AND (ct.size_label IS NOT NULL OR ct.mau_label IS NOT NULL
       OR ct.loai_label IS NOT NULL OR ct.quycach_label IS NOT NULL)
GROUP BY ct.mavt, vt.tenvt;

-- Liet ke ma vat tu chua co quy tac de bo sung thu cong.
SELECT vt.mavt, vt.tenvt, vt.dvt
FROM bhld_dmvattu vt
LEFT JOIN bhld_vattu_thuoctinh q ON q.mavt = vt.mavt
WHERE q.mavt IS NULL
ORDER BY vt.mavt;
