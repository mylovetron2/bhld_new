# Hướng dẫn Upload File monthly_report.php

## ⚠️ QUAN TRỌNG
File `monthly_report.php` trong thư mục này đã được sửa và hoạt động tốt.
Nhưng file trên server (http://diavatly.com/BHLD/api/) vẫn là bản cũ bị lỗi 500.

## 🔧 Cần làm gì

### Bước 1: Upload file lên server
1. Mở FTP client (FileZilla, WinSCP, hoặc cPanel File Manager)
2. Kết nối đến server: `diavatly.com`
3. Tìm thư mục: `/BHLD/api/`
4. Upload file: `monthly_report.php` (file này trong thư mục hiện tại)
5. Ghi đè (overwrite) file cũ trên server

### Bước 2: Kiểm tra trong trình duyệt
Mở URL này để kiểm tra:
```
http://diavatly.com/BHLD/api/monthly_report.php?month=12/2024
```

Phải thấy JSON bắt đầu bằng:
```json
{"success":true,"message":"Lấy báo cáo thành công",...}
```

### Bước 3: Test trong app
1. Trong terminal Flutter, nhấn `r` để hot reload
2. Vào tab "Báo cáo"
3. Chọn tháng 12/2024
4. Sẽ thấy danh sách nhân viên và thiết bị

## 📋 File cần upload
- **File local:** `d:\BHLD_flutter\api\monthly_report.php`
- **Đích trên server:** `/BHLD/api/monthly_report.php`
- **URL kiểm tra:** http://diavatly.com/BHLD/api/monthly_report.php?month=12/2024

## ✅ Đã sửa gì trong file này
- Sử dụng `require 'db_connection.php'` thay vì `config.php`
- Sửa tên bảng: `bhld_ctu` (không phải `bhld_chungtu`)
- Sửa tên bảng: `bhld_ctctu` (không phải `bhld_chungtu_chitiet`)
- Sửa tên cột: `pb.tenphong` (không phải `pb.tenpb`)
- Sửa logic cập nhật equipment: dùng index thay vì reference trong foreach

## 🔍 Nếu vẫn lỗi sau khi upload
Kiểm tra file trên server có đúng nội dung không:
- File phải có dòng: `require 'db_connection.php';`
- File phải query từ bảng: `bhld_ctu`, `bhld_ctctu`
- File phải có logic: `$departments[$deptCode]['employees'][$i]`
