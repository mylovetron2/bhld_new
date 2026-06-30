# Thuật toán Dự báo Vật tư Cần Cấp Phát

## 1. Mục tiêu

Xác định danh sách vật tư cần cấp phát trong N tháng tới, so sánh với tồn kho hiện tại để tính số lượng thiếu hụt, hỗ trợ kế hoạch mua sắm.

---

## 2. Nguồn dữ liệu

| Bảng | Vai trò |
|---|---|
| `bhld_ctctu` | Chi tiết chứng từ — chứa trạng thái cấp phát (`sl`) và ngày nhận tiếp theo (`ngnhantt`) |
| `bhld_dmvattu` | Danh mục vật tư — tên, đơn vị tính |
| `bhld_tonkho` | Tồn kho hiện tại — tổng nhập và tổng đã cấp phát |

---

## 3. Các bước thuật toán

### Bước 1 — Xác định khoảng thời gian dự báo

```
today    = ngày hiện tại
deadline = today + N tháng   (N do người dùng chọn: 1, 2, 3, 6, 12)
```

### Bước 2 — Lọc bản ghi đang sử dụng và sắp đến hạn cấp lại

Điều kiện lọc từ bảng `bhld_ctctu`:

```
sl = 1                            -- Đang được cấp phát (nhân viên đang dùng)
ngnhantt != '1911-11-11'          -- Không phải giá trị null mặc định
ngnhantt >= today                 -- Chưa quá hạn
ngnhantt <= deadline              -- Trong khoảng dự báo
```

> `ngnhantt` (ngày nhận tiếp theo) = ngày nhận (`ngnhan`) + định mức thời gian (`dmtg` tháng).  
> Khi hết hạn, nhân viên cần nhận vật tư mới → đây là thời điểm cần cấp phát.

### Bước 3 — Nhóm theo vật tư

Mỗi vật tư có thể được cấp cho nhiều nhân viên. Gộp nhóm theo `mavt`:

```
so_luong_can_cap      = COUNT(*)           -- Số nhân viên cần nhận lại vật tư này
ngay_can_cap_som_nhat = MIN(ngnhantt)      -- Hạn cấp sớm nhất
ngay_can_cap_muon_nhat= MAX(ngnhantt)      -- Hạn cấp muộn nhất
```

### Bước 4 — Lấy tồn kho hiện tại

```
ton_hien_tai = so_luong_nhap - so_luong_cap_phat
```

Từ bảng `bhld_tonkho` (LEFT JOIN để giữ vật tư chưa nhập kho → ton = 0).

### Bước 5 — Tính số lượng thiếu

```
thieu = MAX(0, so_luong_can_cap - ton_hien_tai)
```

- Nếu tồn kho đủ → `thieu = 0`
- Nếu tồn kho không đủ → `thieu > 0` → cần mua thêm

### Bước 6 — Phân loại trạng thái từng vật tư

| Điều kiện | Trạng thái | Màu |
|---|---|---|
| `thieu > 0` | Thiếu tồn kho | 🔴 Đỏ |
| `ton_hien_tai = 0` và `thieu = 0` | Hết tồn | 🟡 Vàng |
| `ton_hien_tai > 0` và `thieu = 0` | Đủ tồn kho | 🟢 Xanh |

### Bước 7 — Tổng hợp số liệu báo cáo

```
tong_loai_vt = COUNT(mavt distinct)          -- Số loại vật tư cần cấp
tong_can_cap = SUM(so_luong_can_cap)         -- Tổng số lượng cần cấp
tong_thieu   = SUM(thieu)                    -- Tổng số lượng thiếu
loai_thieu   = COUNT(mavt) WHERE thieu > 0   -- Số loại vật tư thiếu tồn
```

---

## 4. Sơ đồ luồng

```
Người dùng chọn N tháng
        │
        ▼
Tính deadline = today + N tháng
        │
        ▼
Lọc bhld_ctctu: sl=1, ngnhantt trong [today, deadline]
        │
        ▼
GROUP BY mavt → đếm số lượng cần cấp, min/max ngày
        │
        ▼
LEFT JOIN bhld_tonkho → lấy tồn hiện tại
        │
        ▼
Tính thieu = MAX(0, so_luong_can_cap - ton_hien_tai)
        │
        ▼
Trả về danh sách + tổng hợp
        │
        ▼
Frontend hiển thị bảng + thẻ tóm tắt + cho phép xuất CSV
```

---

## 5. File liên quan

| File | Chức năng |
|---|---|
| `api/inventory_forecast.php` | API backend thực thi thuật toán |
| `api/inventory.php` | API tồn kho (cung cấp `ton_hien_tai`) |
| `api/allocate.php` | Cập nhật `so_luong_cap_phat` khi cấp phát |
| `api/deallocate.php` | Hoàn lại `so_luong_cap_phat` khi thu hồi |
| `js/app.js` | Hàm `loadForecast()`, `exportForecastCsv()` |
| `index.html` | Giao diện tab Tồn Kho → Báo cáo → Dự báo |

---

## 6. Giới hạn và lưu ý

- Thuật toán chỉ dự báo dựa trên **chu kỳ hiện tại** của từng nhân viên, không tính đến nhân viên mới phát sinh.
- `ton_hien_tai` phản ánh **tồn kho logic** (nhập − cấp phát theo hệ thống), không phải kiểm kho thực tế.
- Nếu vật tư chưa được nhập kho (`bhld_tonkho` không có bản ghi), `ton_hien_tai = 0` → mọi nhu cầu đều tính là thiếu.
- Dữ liệu chính xác phụ thuộc vào việc cập nhật `ngnhantt` đúng khi cấp phát qua `api/allocate.php`.
