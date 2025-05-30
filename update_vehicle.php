<?php
// update_vehicle.php

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "baidoxe_tm";
$port       = 3307;

// 1. Kết nối DB
$conn = new mysqli($servername, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    http_response_code(500);
    exit("Lỗi kết nối DB: " . $conn->connect_error);
}

// 2. Nhận dữ liệu
$plate = $_POST['plate'] ?? '';
$type  = $_POST['type']  ?? '';  // 'vao' hoặc 'ra'

if (!$plate || !in_array($type, ['vao','ra'])) {
    http_response_code(400);
    exit("Dữ liệu không hợp lệ");
}

$plate = $conn->real_escape_string($plate);

// --- XỬ LÝ VÀO ---
if ($type === 'vao') {
    // Kiểm tra xem đã có record chưa ra (ra IS NULL) chưa
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM vehicles
        WHERE bien_so = ? AND ra IS NULL
    ");
    $stmt->bind_param("s", $plate);
    $stmt->execute();
    $stmt->bind_result($countOpen);
    $stmt->fetch();
    $stmt->close();

    if ($countOpen == 0) {
        // Chưa có phiên mở -> insert mới với thời gian hiện tại
        $stmt = $conn->prepare("
            INSERT INTO vehicles (bien_so, vao)
            VALUES (?, NOW())
        ");
        $stmt->bind_param("s", $plate);
        $stmt->execute();
        $insertId = $stmt->insert_id; // Lấy ID vừa insert
        $stmt->close();

        // Lấy lại thời gian vao
        $stmt = $conn->prepare("
            SELECT vao FROM vehicles WHERE id = ?
        ");
        $stmt->bind_param("i", $insertId);
        $stmt->execute();
        $stmt->bind_result($vaoTime);
        $stmt->fetch();
        $stmt->close();

        echo json_encode([
            'status' => 'inserted_entry',
            'message' => "Đã thêm bản ghi VÀO cho $plate",
            'vao_time' => $vaoTime
        ]);
    } else {
        // Đã có phiên mở -> không chèn hay cập nhật gì
        echo json_encode([
            'status' => 'exists_entry',
            'message' => "Đã tồn tại bản ghi VÀO chưa có RA cho $plate"
        ]);
    }

// --- XỬ LÝ RA ---
} else {
    // Tìm bản ghi mở gần nhất (ra IS NULL)
    $stmt = $conn->prepare("
        SELECT id FROM vehicles
        WHERE bien_so = ? AND ra IS NULL
        ORDER BY vao DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $plate);
    $stmt->execute();
    $stmt->bind_result($openId);

    if ($stmt->fetch()) {
        // Tồn tại phiên mở -> update ra
        $stmt->close();
        $upd = $conn->prepare("
            UPDATE vehicles SET ra = NOW() 
            WHERE id = ?
        ");
        $upd->bind_param("i", $openId);
        $upd->execute();
        $upd->close();
        echo json_encode([
            'status' => 'updated_exit',
            'message' => "Đã cập nhật RA cho phiên #$openId của $plate"
        ]);
    } else {
        // Không có phiên mở -> insert bản ghi chỉ có ra
        $stmt->close();
        $ins = $conn->prepare("
            INSERT INTO vehicles (bien_so, ra)
            VALUES (?, NOW())
        ");
        $ins->bind_param("s", $plate);
        $ins->execute();
        $ins->close();
        echo json_encode([
            'status' => 'inserted_exit',
            'message' => "Thêm bản ghi chỉ RA cho $plate"
        ]);
    }
}

$conn->close();
