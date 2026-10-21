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
- Đã tích hợp Prometheus, Grafana, Loki và Promtail.
- Đã áp dụng non-root cho app, hạn chế quyền và tách mạng.

## Triển khai đầy đủ trên Ubuntu

Tạo `.env` từ `.env.example` nếu chưa có, rồi điền mật khẩu riêng.
Không ghi đè `.env` đang sử dụng và không đưa file này lên GitHub.

Kiểm tra cấu hình:

```bash
docker compose -f compose.yaml -f compose.monitoring.yaml config -q
```

Khởi động:

```bash
docker compose -f compose.yaml -f compose.monitoring.yaml up -d --build
```

Xem trạng thái:

```bash
docker compose -f compose.yaml -f compose.monitoring.yaml ps
```

### Địa chỉ truy cập trong Ubuntu

- Website: http://localhost:8082/recipe/
- phpMyAdmin: http://localhost:8081
- Prometheus: http://localhost:9091/targets
- Grafana: http://localhost:3001

Grafana dùng tài khoản và mật khẩu khai báo trong `.env`.
Dashboard Bếp Nhà được nạp tự động từ thư mục
`monitoring/grafana/dashboards`.

### Giám sát và log

- Prometheus thu thập số liệu Nginx, MySQL và container.
- Grafana hiển thị trạng thái, lưu lượng, CPU và RAM.
- Promtail chuyển log container sang Loki.

Ví dụ LogQL:

```logql
{moi_truong="bep-nha", service="nginx"}
```

```logql
{moi_truong="bep-nha", service="nginx"} |= " 200 "
```

```logql
sum(count_over_time({moi_truong="bep-nha", service="nginx"}[5m]))
```

### Hardening đã áp dụng

- PHP/Apache chạy bằng user www-data.
- App bỏ toàn bộ capabilities và bật no-new-privileges.
- Mạng frontend tách khỏi backend; backend là mạng internal.
- Database không công bố cổng ra máy chủ.
- Mã nguồn được mount chỉ đọc.
- Nginx có security headers cơ bản.

### Dừng hệ thống và giữ dữ liệu

```bash
docker compose -f compose.yaml -f compose.monitoring.yaml stop
```

Không dùng `down -v` khi cần giữ dữ liệu.

### Tạo tài khoản MySQL exporter khi cài mới

Khởi động database:

```bash
docker compose -f compose.yaml -f compose.monitoring.yaml up -d db
```

Đăng nhập bằng MYSQL_ROOT_PASSWORD trong `.env`:

```bash
docker compose -f compose.yaml -f compose.monitoring.yaml exec db mysql -u root -p
```

Trong MySQL, chạy các câu sau. Thay REPLACE_WITH_EXPORTER_PASSWORD
bằng đúng MYSQL_EXPORTER_PASSWORD đã đặt trong `.env`:

```sql
CREATE USER 'bepnha_exporter'@'%'
IDENTIFIED BY 'REPLACE_WITH_EXPORTER_PASSWORD'
WITH MAX_USER_CONNECTIONS 3;

GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.*
TO 'bepnha_exporter'@'%';

exit;
```

Chỉ tạo tài khoản một lần trên database mới.
Đây là tài khoản giám sát, không phải tài khoản đăng nhập website.
Sau đó khởi động toàn bộ hệ thống bằng hai file Compose.
