<?php
// get_plates.php
header('Content-Type: application/json');

$plate_out = '';
$plate_in = '';

if (file_exists('last_plate.txt')) {
    $content = trim(file_get_contents('last_plate.txt'));
    $plate_out = explode('|', $content)[0] ?? '';
}

if (file_exists('last_plate1.txt')) {
    $content = trim(file_get_contents('last_plate1.txt'));
    $plate_in = explode('|', $content)[0] ?? '';
}

echo json_encode([
    'plate_out' => $plate_out,
    'plate_in' => $plate_in,
]);
