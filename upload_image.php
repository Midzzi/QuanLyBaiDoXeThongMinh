<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imageFile'])) {
    $target_dir = "anhvao/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $filename = "img_" . date("Ymd_His") . ".jpg";
    $target_file = $target_dir . $filename;

    if (move_uploaded_file($_FILES["imageFile"]["tmp_name"], $target_file)) {
        echo "✅ Ảnh đã được tải lên: $filename";
    } else {
        echo "❌ Lỗi khi tải ảnh lên.";
    }
} else {
    echo "⚠️ Không có ảnh nào được gửi.";
}
?>
