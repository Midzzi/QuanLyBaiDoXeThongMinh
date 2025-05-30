<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "baidoxe_tm";

$conn = new mysqli($servername, $username, $password, $dbname, 3307);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $bien_so = $_POST['plate_register'] ?? '';
    $chu_xe = $_POST['owner_name'] ?? '';
    $ma_the = $_POST['card_number'] ?? '';

    if (empty($bien_so) || empty($chu_xe) || empty($ma_the)) {
        echo "<script>alert('Vui lòng điền đủ thông tin!');</script>";
    } else {
        $bien_so = $conn->real_escape_string($bien_so);
        $chu_xe = $conn->real_escape_string($chu_xe);
        $ma_the = $conn->real_escape_string($ma_the);

        $stmt = $conn->prepare("INSERT INTO dang_ky (ma_the, chu_xe, bien_so, ngay_dang_ky) VALUES (?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("sss", $ma_the, $chu_xe, $bien_so);
            $stmt->execute();
            $stmt->close();
            echo "<script>alert('Đăng ký thành công!');</script>";
        } else {
            echo "<script>alert('Đã có lỗi xảy ra khi đăng ký!');</script>";
        }
    }
}

$tim_kiem_phuong_tien = $_POST['vehicle_search'] ?? '';
$tim_kiem_dang_ky = $_POST['register_search'] ?? '';

$truy_van_phuong_tien = "SELECT * FROM vehicles";
if (!empty($tim_kiem_phuong_tien)) {
    $tim_kiem_phuong_tien = $conn->real_escape_string($tim_kiem_phuong_tien);
    $truy_van_phuong_tien .= " WHERE bien_so LIKE '%$tim_kiem_phuong_tien%'";
}
$truy_van_phuong_tien .= " ORDER BY vao DESC";
$danh_sach_phuong_tien = $conn->query($truy_van_phuong_tien);
if (!$danh_sach_phuong_tien) {
    die("Lỗi truy vấn phương tiện: " . $conn->error);
}

$truy_van_dang_ky = "SELECT * FROM dang_ky";
if (!empty($tim_kiem_dang_ky)) {
    $tim_kiem_dang_ky = $conn->real_escape_string($tim_kiem_dang_ky);
    $truy_van_dang_ky .= " WHERE bien_so LIKE '%$tim_kiem_dang_ky%' OR chu_xe LIKE '%$tim_kiem_dang_ky%'";
}
$truy_van_dang_ky .= " ORDER BY ngay_dang_ky DESC";
$danh_sach_dang_ky = $conn->query($truy_van_dang_ky);
if (!$danh_sach_dang_ky) {
    die("Lỗi truy vấn đăng ký: " . $conn->error);
}

$bien_so_ra = file_exists('last_plate.txt') ? explode('|', trim(file_get_contents('last_plate.txt')))[0] : '';
$bien_so_vao = file_exists('last_plate1.txt') ? explode('|', trim(file_get_contents('last_plate1.txt')))[0] : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>Giao Diện Bãi Xe</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Meta refresh trang sau 7 giây -->
    <meta http-equiv="refresh" content="10" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        header { background-color: #8B5E3C; color: white; padding: 20px; text-align: center; border-bottom: 5px solid #5E3C1B; }
        .card { margin-top: 20px; border: 1px solid #d2b48c; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .card-header { background-color: #5E3C1B; color: white; font-weight: bold; font-size: 16px; }
        .nav-tabs .nav-link.active { background-color: #8B5E3C; color: white; }
        .nav-tabs .nav-link { color: #5E3C1B; }
        .form-control, .btn { margin-top: 10px; }
        .btn-outline-primary { color: #5E3C1B; border-color: #8B5E3C; }
        .btn-outline-primary:hover { background-color: #8B5E3C; color: white; }
        .btn-success { background-color: #8B5E3C; border-color: #8B5E3C; }
        .btn-success:hover { background-color: #5E3C1B; }
        img.cam-stream { width: 100%; height: 400px; border-radius: 10px; border: 2px solid #8B5E3C; object-fit: cover; }
        footer { margin-top: 40px; padding: 15px; background-color: #5E3C1B; color: white; }
    </style>
</head>
<body>
<header>
    <h1><i class="bi bi-car-front-fill"></i> Quản Lý Bãi Đỗ Xe Thông Minh</h1>
</header>
<div class="container py-4">
    <div class="row">
        <div class="col-md-8">
            <div class="row">
                <!-- Camera Ra -->
                <div class="col-6 mb-3">
                    <div class="card">
                        <div class="card-header"><i class="bi bi-camera-video-fill"></i> Camera ESP32 - 2 (Ra)</div>
                        <div class="card-body">
                            <img id="cam2" class="cam-stream" src="http://172.20.10.9/stream" alt="Camera Ra" />
                            <p class="mt-2"><strong>Biển số gần nhất (Ra):</strong> <span id="plate-camera2"><?= htmlspecialchars($bien_so_ra) ?></span></p>
                        </div>
                    </div>
                </div>
                <!-- Camera Vào -->
                <div class="col-6 mb-3">
                    <div class="card">
                        <div class="card-header"><i class="bi bi-camera-video-fill"></i> Camera ESP32 - 1 (Vào)</div>
                        <div class="card-body">
                            <img id="cam1" class="cam-stream" src="http://172.20.10.10/stream" alt="Camera Vào" />
                            <p class="mt-2"><strong>Biển số gần nhất (Vào):</strong> <span id="plate-camera1"><?= htmlspecialchars($bien_so_vao) ?></span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Tab bên phải -->
        <div class="col-md-4">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#vehicles" type="button">Phương Tiện</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#registrations" type="button">Đăng Ký</button>
                </li>
            </ul>
            <div class="tab-content mt-3">
                <!-- Tab Phương Tiện -->
                <div class="tab-pane fade show active" id="vehicles" role="tabpanel">
                    <form method="post" class="d-flex justify-content-between">
                        <input type="text" name="vehicle_search" class="form-control w-75" placeholder="Tìm kiếm phương tiện..." value="<?= htmlspecialchars($tim_kiem_phuong_tien) ?>" />
                        <button type="submit" class="btn btn-outline-primary w-25">Tìm</button>
                    </form>
                    <div class="table-responsive mt-3">
                        <table class="table table-striped">
                            <thead>
                                <tr><th>Biển số</th><th>Vào</th><th>Ra</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $danh_sach_phuong_tien->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['bien_so']) ?></td>
                                        <td><?= htmlspecialchars($row['vao']) ?></td>
                                        <td><?= htmlspecialchars($row['ra'] ?? 'Đang đỗ') ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Tab Đăng Ký -->
                <div class="tab-pane fade" id="registrations" role="tabpanel">
                    <form method="post">
                        <input name="plate_register" class="form-control" placeholder="Biển số xe" required />
                        <input name="owner_name" class="form-control" placeholder="Chủ xe" required />
                        <input name="card_number" class="form-control" placeholder="Mã thẻ" required />
                        <button name="register" class="btn btn-success w-100">Đăng ký</button>
                    </form>
                    <div class="table-responsive mt-3">
                        <table class="table table-striped">
                            <thead>
                                <tr><th>Biển số</th><th>Chủ xe</th><th>Mã thẻ</th><th>Ngày</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $danh_sach_dang_ky->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['bien_so']) ?></td>
                                        <td><?= htmlspecialchars($row['chu_xe']) ?></td>
                                        <td><?= htmlspecialchars($row['ma_the']) ?></td>
                                        <td><?= htmlspecialchars($row['ngay_dang_ky']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<footer class="text-center">
    <p>&copy; 2025 Hệ thống quản lý bãi đỗ xe</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
