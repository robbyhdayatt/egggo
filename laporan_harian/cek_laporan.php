<?php
include '../config/database.php';

header('Content-Type: application/json');

$id_kandang = $_GET['id_kandang'] ?? 0;
$tanggal = $_GET['tanggal'] ?? '';

if (empty($id_kandang) || empty($tanggal)) {
    echo json_encode(['exists' => false, 'error' => 'Parameter tidak lengkap']);
    exit();
}

$stmt = $koneksi->prepare("SELECT id_laporan FROM laporan_harian WHERE id_kandang = ? AND tanggal = ?");
$stmt->bind_param("is", $id_kandang, $tanggal);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['exists' => true]);
} else {
    echo json_encode(['exists' => false]);
}

exit();
?>