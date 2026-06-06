# CẦN UPLOAD FILE LÊN SERVER

## File cần upload ngay:
**File:** allocation_history.php  
**Từ:** C:\xampp\htdocs\BHLD\api\allocation_history.php  
**Đến:** http://diavatly.com/BHLD/api/allocation_history.php

## Thay đổi trong phiên bản mới:
1. ✅ Giảm LIMIT từ 200 xuống 20 records (tăng tốc độ)
2. ✅ Sử dụng IFNULL thay vì COALESCE (tối ưu MySQL)
3. ✅ Sửa JOIN pb.mapb: sử dụng `ct.mapb` thay vì `nv.mapb`
4. ✅ Loại bỏ ORDER BY phức tạp, chỉ giữ ngct DESC
5. ✅ Tối ưu WHERE conditions

## Cách upload nhanh nhất:

### Bước 1: Mở cPanel File Manager
1. Truy cập http://diavatly.com/cpanel (hoặc địa chỉ cPanel của bạn)
2. Đăng nhập với tài khoản hosting
3. Mở **File Manager**

### Bước 2: Navigate đến thư mục
1. Vào thư mục `/public_html/BHLD/api/`
2. Tìm file `allocation_history.php` (nếu có)

### Bước 3: Upload file mới
1. Click nút **Upload** trên menu
2. Chọn file từ `C:\xampp\htdocs\BHLD\api\allocation_history.php`
3. Nếu hỏi overwrite, chọn **Yes** (ghi đè)

### Bước 4: Kiểm tra permissions
1. Right-click file `allocation_history.php`
2. Chọn **Permissions**
3. Đảm bảo là **644** (rw-r--r--)

### Bước 5: Test API
Mở trình duyệt và test:
```
http://diavatly.com/BHLD/api/allocation_history.php?status=allocated
```

Kết quả mong đợi (trả về trong vài giây):
```json
{
  "success": true,
  "message": "Lấy lịch sử cấp phát thành công",
  "data": [...]
}
```

## Nếu không có quyền truy cập cPanel:

### Phương án A: FTP Client (WinSCP)
1. Download WinSCP: https://winscp.net/
2. Kết nối:
   - Host: diavatly.com
   - Protocol: SFTP hoặc FTP
   - Username: (tài khoản hosting)
   - Password: (mật khẩu hosting)
3. Navigate: `/public_html/BHLD/api/`
4. Drag & drop file `allocation_history.php`

### Phương án B: Liên hệ admin server
Gửi email cho quản trị viên server với:
- Tiêu đề: "Upload file API allocation_history.php"
- Nội dung: "Cần upload file allocation_history.php vào thư mục /BHLD/api/"
- Đính kèm: File C:\xampp\htdocs\BHLD\api\allocation_history.php

## Sau khi upload:
1. ✅ Test API trên trình duyệt
2. ✅ Hot reload Flutter app (`r` trong terminal)
3. ✅ Vào tab "Lịch sử" để xem dữ liệu

## Nếu vẫn lỗi sau khi upload:
1. Kiểm tra file permissions (phải là 644)
2. Kiểm tra file config.php có tồn tại không
3. Test query trực tiếp trong phpMyAdmin
4. Kiểm tra MySQL error log trên server
