# BHLD API Endpoints

API REST cho ứng dụng mobile BHLD (Bảo Hộ Lao Động)

## Base URL
```
http://localhost/BHLD/api
```

## Endpoints

### 1. Employees (Nhân viên)

#### GET /employees.php
Lấy danh sách nhân viên

**Query Parameters:**
- `search` (optional): Tìm kiếm theo mã hoặc tên nhân viên

**Response:**
```json
{
  "success": true,
  "message": "Lấy danh sách nhân viên thành công",
  "data": [
    {
      "manv": "17542",
      "tennhanvien": "Nguyễn Văn A",
      "mapb": "PB01",
      "tenphongban": "Phòng Kỹ thuật"
    }
  ]
}
```

#### GET /employees.php?manv={manv}
Lấy thông tin chi tiết nhân viên

**Response:**
```json
{
  "success": true,
  "message": "Lấy thông tin nhân viên thành công",
  "data": {
    "manv": "17542",
    "tennhanvien": "Nguyễn Văn A",
    "mapb": "PB01",
    "tenphongban": "Phòng Kỹ thuật"
  }
}
```

### 2. Certificates (Chứng từ)

#### GET /certificates.php
Lấy danh sách chứng từ

**Query Parameters:**
- `manv` (optional): Lọc theo mã nhân viên
- `from_date` (optional): Từ ngày (YYYY-MM-DD)
- `to_date` (optional): Đến ngày (YYYY-MM-DD)

**Response:**
```json
{
  "success": true,
  "message": "Lấy danh sách chứng từ thành công",
  "data": [
    {
      "mact": "2024-11-PB01-17542",
      "ngct": "2024-11-15",
      "mapb": "PB01",
      "manv": "17542",
      "ghichu": "",
      "madm": "DM001",
      "tennhanvien": "Nguyễn Văn A",
      "tenphongban": "Phòng Kỹ thuật"
    }
  ]
}
```

#### POST /certificates.php
Tạo chứng từ mới

**Request Body:**
```json
{
  "mact": "2024-12-PB01-17542",
  "manv": "17542",
  "ngct": "2024-12-01",
  "mapb": "PB01",
  "madm": "DM001",
  "ghichu": ""
}
```

### 3. Certificate Details (Chi tiết chứng từ)

#### GET /certificate_details.php?mact={mact}
Lấy chi tiết chứng từ

**Response:**
```json
{
  "success": true,
  "message": "Lấy chi tiết chứng từ thành công",
  "data": [
    {
      "mact": "2024-11-PB01-17542",
      "mavt": "101",
      "dmtg": "3",
      "sl": "1",
      "ngnhan": "2024-11-15",
      "ngnhantt": "2025-02-15",
      "tenvt": "Khẩu trang N95",
      "dvt": "cái"
    }
  ]
}
```

### 4. Equipment (Thiết bị)

#### GET /equipment.php
Lấy danh sách thiết bị

**Query Parameters:**
- `search` (optional): Tìm kiếm theo tên thiết bị

**Response:**
```json
{
  "success": true,
  "message": "Lấy danh sách thiết bị thành công",
  "data": [
    {
      "mavt": "101",
      "tenvt": "Khẩu trang N95",
      "dvt": "cái",
      "ghichu": ""
    }
  ]
}
```

### 5. Allocate (Cấp phát)

#### POST /allocate.php
Cấp phát thiết bị cho nhân viên

**Request Body:**
```json
{
  "mact": "2024-11-PB01-17542",
  "mavt": 101,
  "ngnhan": "2024-11-15"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Cấp phát thiết bị thành công",
  "data": {
    "mact": "2024-11-PB01-17542",
    "mavt": 101,
    "ngnhan": "2024-11-15",
    "ngnhantt": "2025-02-15"
  }
}
```

### 6. Deallocate (Trả thiết bị)

#### POST /deallocate.php
Thu hồi thiết bị từ nhân viên

**Request Body:**
```json
{
  "mact": "2024-11-PB01-17542",
  "mavt": 101
}
```

**Response:**
```json
{
  "success": true,
  "message": "Trả thiết bị thành công",
  "data": {
    "mact": "2024-11-PB01-17542",
    "mavt": 101
  }
}
```

## Error Response Format

```json
{
  "success": false,
  "message": "Lỗi: Chi tiết lỗi",
  "data": null
}
```

## CORS Headers
Tất cả API đều hỗ trợ CORS để mobile app có thể gọi được:
- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type, Authorization`

## Testing với cURL

```bash
# Get employees
curl http://localhost/BHLD/api/employees.php

# Get employee by code
curl http://localhost/BHLD/api/employees.php?manv=17542

# Get certificates
curl http://localhost/BHLD/api/certificates.php?manv=17542

# Allocate equipment
curl -X POST http://localhost/BHLD/api/allocate.php \
  -H "Content-Type: application/json" \
  -d '{"mact":"2024-11-PB01-17542","mavt":101,"ngnhan":"2024-11-15"}'
```
