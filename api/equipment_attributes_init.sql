-- Chay 1 lan trong phpMyAdmin de khoi tao bang danh muc thuoc tinh.
CREATE TABLE IF NOT EXISTS bhld_danhmuc_thuoctinh (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nhom VARCHAR(30) NOT NULL,
    ten VARCHAR(100) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nhom_ten (nhom, ten),
    INDEX idx_nhom_active (nhom, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO bhld_danhmuc_thuoctinh (nhom, ten, active) VALUES
    ('shoe_type', 'Da', 1),
    ('shoe_type', 'Thể thao', 1),
    ('shoe_type', 'Cao cổ', 1),
    ('shoe_type', 'Thấp cổ', 1),
    ('helmet_color', 'Trắng', 1),
    ('helmet_color', 'Vàng', 1),
    ('helmet_color', 'Xanh', 1),
    ('helmet_color', 'Đỏ', 1),
    ('helmet_color', 'Cam', 1)
ON DUPLICATE KEY UPDATE active = 1;
