# HƯỚNG DẪN UPLOAD FILE MỚI

## Vấn đề
API allocation_history.php query quá chậm do JOIN nhiều bảng lớn.

## Giải pháp tạm thời
Sử dụng API đơn giản hơn: **allocation_history_fast.php**

## CẦN UPLOAD FILE NÀY:

### File: allocation_history_fast.php
**Từ:** C:\xampp\htdocs\BHLD\api\allocation_history_fast.php  
**Đến:** http://diavatly.com/BHLD/api/allocation_history_fast.php

## Đặc điểm của version fast:
✅ Chỉ JOIN 2 bảng (ctctu + ctu)  
✅ Không load tennhanvien, tenphongban (trả về empty string)  
✅ LIMIT 10 records  
✅ Thời gian response < 1 giây  

## Cách upload (chọn 1 trong 3):

### 1. cPanel File Manager (KHUYÊN DÙNG)
```
1. Truy cập: http://diavatly.com/cpanel
2. Mở "File Manager"
3. Vào: /public_html/BHLD/api/
4. Click "Upload"
5. Chọn: C:\xampp\htdocs\BHLD\api\allocation_history_fast.php
6. Upload xong!
```

### 2. FTP (WinSCP/FileZilla)
```
Host: diavatly.com
Path: /public_html/BHLD/api/
Upload: allocation_history_fast.php
```

### 3. Copy qua command line
```bash
# Nếu có SSH access
scp C:\xampp\htdocs\BHLD\api\allocation_history_fast.php user@diavatly.com:/path/to/BHLD/api/
```

## Sau khi upload:

### 1. Test API
Mở browser:
```
http://diavatly.com/BHLD/api/allocation_history_fast.php?status=allocated
```

Kết quả mong đợi (< 1 giây):
```json
{
  "success": true,
  "message": "Lấy 10 records (ultra fast mode)",
  "data": [
    {
      "mact": "...",
      "manv": "...",
      "mavt": 123,
      "sl": 1,
      "tennhanvien": "",
      "tenphongban": "",
      ...
    }
  ]
}
```

### 2. App Flutter
App đã được cấu hình sử dụng API mới.  
**Hot reload:** Gõ `r` trong terminal

### 3. Kiểm tra
Vào tab "Lịch sử" sẽ thấy danh sách (nhưng không có tên nhân viên/phòng ban)

## Lưu ý
- Version fast này chỉ hiển thị mã nhân viên (manv), không có tên
- Phòng ban cũng không hiển thị
- Chỉ hiển thị 10 records gần nhất
- Nếu cần full data, phải tối ưu database (thêm index)

## Tối ưu lâu dài (làm sau)
1. Thêm index cho bảng bhld_ctctu:
   ```sql
   CREATE INDEX idx_sl_mact ON bhld_ctctu(sl, mact);
   CREATE INDEX idx_ngnhan ON bhld_ctctu(ngnhan);
   ```

2. Thêm index cho bảng bhld_ctu:
   ```sql
   CREATE INDEX idx_ngct ON bhld_ctu(ngct);
   CREATE INDEX idx_manv ON bhld_ctu(manv);
   ```

3. Sau khi có index, có thể quay lại dùng allocation_history.php (version đầy đủ)
