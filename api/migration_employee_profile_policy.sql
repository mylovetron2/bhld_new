-- Migration: employee profile + per-employee equipment policy
-- Collation target: utf8mb4_general_ci (match legacy tables)

START TRANSACTION;

CREATE TABLE IF NOT EXISTS bhld_nhanvien_hoso (
    manv VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL,
    giay_size VARCHAR(10) COLLATE utf8mb4_general_ci NULL,
    giay_loai VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    quanao_size VARCHAR(10) COLLATE utf8mb4_general_ci NULL,
    mu_mau VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    ghi_chu VARCHAR(255) COLLATE utf8mb4_general_ci NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (manv),
    CONSTRAINT fk_hoso_manv FOREIGN KEY (manv) REFERENCES bhld_nhanvien(manv)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS bhld_nhanvien_vattu_dm (
    id BIGINT NOT NULL AUTO_INCREMENT,
    manv VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL,
    mavt INT NOT NULL,
    dmuc_thang INT NOT NULL,
    so_luong INT NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    source_madm VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    ghi_chu VARCHAR(255) COLLATE utf8mb4_general_ci NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nv_mavt (manv, mavt),
    KEY idx_nv_active (manv, active),
    KEY idx_mavt (mavt),
    CONSTRAINT fk_nv_vattu_manv FOREIGN KEY (manv) REFERENCES bhld_nhanvien(manv)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_nv_vattu_mavt FOREIGN KEY (mavt) REFERENCES bhld_dmvattu(mavt)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Force collation for existing tables (important when table already exists)
ALTER TABLE bhld_nhanvien_hoso
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE bhld_nhanvien_hoso
    MODIFY manv VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL,
    MODIFY giay_size VARCHAR(10) COLLATE utf8mb4_general_ci NULL,
    MODIFY giay_loai VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    MODIFY quanao_size VARCHAR(10) COLLATE utf8mb4_general_ci NULL,
    MODIFY mu_mau VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    MODIFY ghi_chu VARCHAR(255) COLLATE utf8mb4_general_ci NULL;

ALTER TABLE bhld_nhanvien_vattu_dm
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE bhld_nhanvien_vattu_dm
    MODIFY manv VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL,
    MODIFY source_madm VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    MODIFY ghi_chu VARCHAR(255) COLLATE utf8mb4_general_ci NULL;

ALTER TABLE bhld_ctctu
    ADD COLUMN IF NOT EXISTS so_luong_yeu_cau INT NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS so_luong_cap INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS size_label VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    ADD COLUMN IF NOT EXISTS mau_label VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    ADD COLUMN IF NOT EXISTS loai_label VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    ADD COLUMN IF NOT EXISTS quycach_label VARCHAR(120) COLLATE utf8mb4_general_ci NULL;

-- Ensure new text columns in ctctu are aligned to utf8mb4_general_ci
ALTER TABLE bhld_ctctu
    MODIFY size_label VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    MODIFY mau_label VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    MODIFY loai_label VARCHAR(20) COLLATE utf8mb4_general_ci NULL,
    MODIFY quycach_label VARCHAR(120) COLLATE utf8mb4_general_ci NULL;

UPDATE bhld_ctctu
SET so_luong_yeu_cau = 1,
    so_luong_cap = CASE WHEN sl > 0 THEN 1 ELSE 0 END
WHERE so_luong_yeu_cau IS NULL OR so_luong_cap IS NULL;

INSERT INTO bhld_nhanvien_vattu_dm (manv, mavt, dmuc_thang, so_luong, active, source_madm)
SELECT nv.manv, ct.mavt, ct.dmuc, 1, 1, nv.dinhmuc
FROM bhld_nhanvien nv
INNER JOIN bhld_ctdmuc ct ON ct.madm = nv.dinhmuc
WHERE nv.dinhmuc IS NOT NULL
ON DUPLICATE KEY UPDATE
    dmuc_thang = VALUES(dmuc_thang),
    so_luong = VALUES(so_luong),
    active = VALUES(active),
    source_madm = VALUES(source_madm);

COMMIT;