# Bếp Nhà — Website công thức nấu ăn

Mã sinh viên: DTC245200656  
Môn học: Triển khai và quản trị hệ thống phần mềm

## Chức năng

- Xem danh sách và chi tiết công thức.
- Tìm kiếm và lọc món theo danh mục.
- Đăng nhập, đăng xuất quản trị.
- Thêm, sửa và xóa công thức có xác nhận.
- Lưu nguyên liệu và các bước nấu.

## Kiến trúc hiện tại

Trình duyệt → Nginx → PHP/Apache → MySQL

phpMyAdmin kết nối MySQL để quản lý dữ liệu.

## Yêu cầu

- Docker Engine đang chạy.
- Docker Compose.
- Cổng 8080 và 8081 chưa được ứng dụng khác sử dụng.

## Cách chạy trên Windows PowerShell

Tại thư mục dự án:

```powershell
Copy-Item .env.example .env
```

Sửa hai mật khẩu trong `.env` thành giá trị riêng, rồi chạy:

```powershell
docker compose up -d --build
docker compose ps
```

Không ghi đè `.env` nếu đã cấu hình trước đó.

## Khởi tạo dữ liệu

Database mới chưa có các bảng ứng dụng.

1. Truy cập http://localhost:8081.
2. Đăng nhập bằng MYSQL_USER và MYSQL_PASSWORD trong `.env`.
3. Chọn database `recipe_db`.
4. Dùng chức năng Import để nhập bản sao lưu `recipe_db.sql`.

Bản sao lưu có tài khoản quản trị được giữ riêng, không đưa lên GitHub.
Sau khi nhập, dùng tài khoản quản trị có trong bản sao lưu.

Hiện cần bản sao lưu riêng để khởi tạo đầy đủ dữ liệu.
Script khởi tạo dùng chung sẽ được bổ sung sau.

## Địa chỉ truy cập

- Website: http://localhost:8080/recipe/
- Quản trị: http://localhost:8080/recipe/admin/
- phpMyAdmin: http://localhost:8081/

## Kiểm tra Nginx

```powershell
docker compose exec nginx nginx -t
curl.exe -I http://localhost:8080/recipe/
```

Website được truy cập qua Nginx và có các headers:

- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- Referrer-Policy: strict-origin-when-cross-origin

Hiện chạy HTTP trên máy cá nhân, chưa cấu hình HTTPS.

## Dừng và chạy lại

```powershell
docker compose stop
docker compose start
```

Dữ liệu MySQL lưu trong named volume `mysql_data`.
Không dùng `docker compose down -v` nếu muốn giữ database.

## Tiến độ

- Hoàn thành website và quản trị công thức.
- Hoàn thành Docker Compose: PHP, MySQL, phpMyAdmin.
- Hoàn thành Nginx reverse proxy và security headers cơ bản.
- Chưa hoàn thành Prometheus, Grafana, Loki, Promtail
  và các biện pháp hardening còn lại.