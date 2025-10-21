<?php
include '../config/database.php';

header('Content-Type: application/json');

$id_kandang = $_GET['id_kandang'] ?? null;
$tanggal = $_GET['tanggal'] ?? null;
$harga_pakan_formatted = 'Rp 0'; // Default value

if ($id_kandang && $tanggal) {
    // Cari harga pakan per kg TERBARU pada atau SEBELUM tanggal laporan untuk kandang yang dipilih
    $stmt = $koneksi->prepare("
        SELECT harga_per_kg 
        FROM stok_pakan 
        WHERE id_kandang = ? AND tanggal_beli <= ? 
        ORDER BY tanggal_beli DESC, id_stok DESC 
        LIMIT 1
    ");
    $stmt->bind_param("is", $id_kandang, $tanggal);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $harga_pakan = $row['harga_per_kg'];
        // Format harga
        $harga_pakan_formatted = 'Rp ' . number_format($harga_pakan, 0, ',', '.');
    }
    $stmt->close();
}

echo json_encode(['harga_pakan' => $harga_pakan_formatted]);

$koneksi->close();
?>