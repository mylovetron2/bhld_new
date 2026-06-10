# HƯỚNG DẪN UPLOAD FILE ĐÃ SỬA

## File cần upload lên server diavatly.cloud

### 1. File: api/export_word.php
**Đường dẫn trên server:** `diavatly.cloud/projectBHLD/api/export_word.php`

**Thay đổi:**
- Thêm logic gom phòng ban (dòng 87-147)
- Gom "Xưởng SC và CC ĐVL" + "Xưởng SC cơ khí chuyên dụng" → "Xưởng sửa chữa thiết bị ĐVL"
- Gom "Đội carota tổng hợp" + "Đội công nghệ cao" → "Đội Địa vật lý tổng hợp"

### 2. File: index.html
**Đường dẫn trên server:** `diavatly.cloud/projectBHLD/index.html`

**Thay đổi:**
- Sửa lỗi không scroll được trong tab Báo Cáo (dòng 280)
- Bỏ `overflow:hidden`, thêm `display:flex` trong style của #tab-reports

## Cách upload

### Cách 1: FTP/SFTP
1. Mở FileZilla (hoặc WinSCP)
2. Kết nối đến diavatly.cloud
3. Upload file:
   - `d:\projectBHLD\api\export_word.php` → server: `/public_html/projectBHLD/api/export_word.php`
   - `d:\projectBHLD\index.html` → server: `/public_html/projectBHLD/index.html`

### Cách 2: cPanel File Manager
1. Đăng nhập cPanel của diavatly.cloud
2. Vào File Manager
3. Navigate đến `/public_html/projectBHLD/api/`
4. Upload file `export_word.php` (replace existing)
5. Upload file `index.html` vào `/public_html/projectBHLD/`

## Kiểm tra sau khi upload

Truy cập: `https://diavatly.cloud/projectBHLD/`
- Vào tab Báo Cáo → nhấn "Xuất Word"
- Kiểm tra trong file Word:
  - Chỉ có 1 phòng: "Xưởng sửa chữa thiết bị ĐVL" (thay vì 2 phòng riêng)
  - Chỉ có 1 phòng: "Đội Địa vật lý tổng hợp" (thay vì 2 phòng riêng)
