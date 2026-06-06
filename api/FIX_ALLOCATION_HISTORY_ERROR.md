# HƯỚNG DẪN KHẮC PHỤC LỖI "Lỗi tải dữ liệu"

## Nguyên nhân
File `allocation_history.php` chưa được upload lên server http://diavatly.com/BHLD/api/

## Giải pháp

### Bước 1: Upload file lên server
Cần upload file sau lên server:
- **File local:** `C:\xampp\htdocs\BHLD\api\allocation_history.php`
- **Vị trí trên server:** `http://diavatly.com/BHLD/api/allocation_history.php`

### Bước 2: Test API
Sau khi upload, test bằng cách truy cập:
```
http://diavatly.com/BHLD/api/allocation_history.php
```

Kết quả mong đợi:
```json
{
  "success": true,
  "message": "Lấy lịch sử cấp phát thành công",
  "data": [...]
}
```

### Bước 3: Chạy lại app
Sau khi API hoạt động, mở lại app Flutter và vào tab "Lịch sử"

## Cách upload file

### Phương án 1: FTP Client (FileZilla, WinSCP)
1. Mở FTP client
2. Kết nối đến server diavatly.com
3. Navigate đến thư mục `/BHLD/api/`
4. Upload file `allocation_history.php`

### Phương án 2: cPanel File Manager
1. Đăng nhập cPanel
2. Mở File Manager
3. Navigate đến `/public_html/BHLD/api/`
4. Click "Upload" và chọn file `allocation_history.php`

### Phương án 3: Command line (nếu có SSH access)
```bash
scp C:\xampp\htdocs\BHLD\api\allocation_history.php user@diavatly.com:/path/to/BHLD/api/
```

## Test nhanh với curl
```bash
curl http://diavatly.com/BHLD/api/allocation_history.php?status=allocated
```

## Lỗi thường gặp

### 1. 404 Not Found
- File chưa được upload
- Đường dẫn file sai
- Quyền truy cập file không đúng

### 2. 500 Internal Server Error
- Lỗi PHP syntax
- Thiếu file config.php
- Lỗi kết nối database

### 3. Lỗi CORS
- Thêm header CORS trong file allocation_history.php:
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
```

## Liên hệ
Nếu không thể upload file, liên hệ quản trị viên server để được hỗ trợ.
