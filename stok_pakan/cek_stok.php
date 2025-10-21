<?php
include '../config/database.php';

header('Content-Type: application/json');

$id_kandang = $_GET['id_kandang'] ?? null;
$tanggal_beli = $_GET['tanggal_beli'] ?? null;

if (!$id_kandang || !$tanggal_beli) {
    echo json_encode(['exists' => false, 'error' => 'Parameter tidak lengkap.']);
    exit();
}

$stmt = $koneksi->prepare("SELECT id_stok FROM stok_pakan WHERE id_kandang = ? AND tanggal_beli = ?");
$stmt->bind_param("is", $id_kandang, $tanggal_beli);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['exists' => true]);
} else {
    echo json_encode(['exists' => false]);
}

$stmt->close();
$koneksi->close();
?>